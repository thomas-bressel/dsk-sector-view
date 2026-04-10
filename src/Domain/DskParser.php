<?php

/**
 * DskParser
 *
 * Reads an Extended CPC DSK binary file and returns a structured array
 * of tracks and sectors ready for consumption by DiskStats and DskWriter.
 *
 * Supported formats:
 *   - Extended CPC DSK  (signature "EXTENDED CPC DSK File")
 *   - Standard MV-CPCEMU DSK (signature "MV - CPCEMU")
 *
 * Returned structure:
 * <code>
 * [
 *   'path'       => string,           // absolute path of the source file
 *   'fileSize'   => int,              // file size in bytes
 *   'header'     => array,            // parsed disk header
 *   'trackSizes' => int[],            // raw track size table (bytes, multiples of 256)
 *   'tracks'     => array[],          // one entry per formatted track
 *   'rawSectors' => array[],          // flat list of all sectors across all tracks
 * ]
 * </code>
 *
 * Each sector entry contains:
 * <code>
 * [
 *   'track'       => int,   // physical track number (C field)
 *   'side'        => int,   // side number (H field)
 *   'C'           => int,   // cylinder (track) address
 *   'H'           => int,   // head (side) address
 *   'R'           => int,   // sector ID (logical record number)
 *   'N'           => int,   // sector size code (128 << N = bytes)
 *   'declSize'    => int,   // declared size in bytes (128 << N)
 *   'realSize'    => int,   // actual data size stored in the DSK
 *   'sumData'     => int,   // byte sum of sector data (used for comparison)
 *   'sr1'         => int,   // FDC Status Register 1 byte
 *   'sr2'         => int,   // FDC Status Register 2 byte
 *   'isWeak'      => bool,  // true if realSize > declSize (multi-read weak sector)
 *   'isErased'    => bool,  // true if SR2 bit 6 is set (deleted data address mark)
 *   'isFdcErr'    => bool,  // true if SR1 or SR2 bit 5 is set (CRC error)
 *   'isUsed'      => bool,  // true if sector data differs from the track filler byte
 *   'isIncomplete'=> bool,  // true if realSize != declSize
 *   'data'        => string, // raw binary sector data
 * ]
 * </code>
 *
 * @package DskToolPhp\Domain
 */
class DskParser
{
    /**
     * Parses an Extended DSK file and returns structured raw data.
     *
     * @param  string $path Absolute path to the .dsk file
     * @return array        Structured disk data (see class docblock)
     * @throws \RuntimeException If the file cannot be opened
     */
    public function parse(string $path): array
    {
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new \RuntimeException("Cannot open file: $path");
        }

        $header     = $this->parseHeader($h);
        $trackSizes = $this->readTrackSizeTable($header['raw'], $header['nbTracks'], $header['nbSides']);

        $tracks     = [];
        $rawSectors = [];
        $pos        = 256;

        for ($t = 0; $t < $header['nbTracks'] * $header['nbSides']; $t++) {
            $tSize = $trackSizes[$t] ?? 0;
            if ($tSize === 0) continue;

            fseek($h, $pos);
            $trackHdr = fread($h, 256);

            if (substr($trackHdr, 0, 10) !== 'Track-Info') {
                $pos += $tSize;
                continue;
            }

            $track = $this->parseTrackHeader($trackHdr);
            $track['sectors'] = $this->parseSectors($h, $pos, $track['spt'], $track['sectorInfos'], $track['filler']);

            $tracks[]   = $track;
            $rawSectors = array_merge($rawSectors, $track['sectors']);

            $pos += $tSize;
        }

        fclose($h);

        return [
            'path'       => $path,
            'fileSize'   => filesize($path),
            'header'     => $header,
            'trackSizes' => $trackSizes,
            'tracks'     => $tracks,
            'rawSectors' => $rawSectors,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Reads and parses the 256-byte disk header.
     *
     * @param  resource $h Open file handle positioned at offset 0
     * @return array       Parsed header fields
     */
    private function parseHeader($h): array
    {
        $raw     = fread($h, 256);
        $creator = rtrim(substr($raw, 0x22, 14));
        $format  = rtrim(substr($raw, 0, 34));

        return [
            'raw'      => $raw,
            'format'   => $format,
            'creator'  => $creator,
            'nbTracks' => ord($raw[0x30]),
            'nbSides'  => ord($raw[0x31]),
        ];
    }

    /**
     * Reads the track size table from the disk header.
     * Each byte at offset 0x34+i encodes the track block size as byte × 256.
     *
     * @param  string $raw      Raw 256-byte disk header
     * @param  int    $nbTracks Number of tracks per side
     * @param  int    $nbSides  Number of sides
     * @return int[]            Track sizes in bytes, indexed by slot (track × sides + side)
     */
    private function readTrackSizeTable(string $raw, int $nbTracks, int $nbSides): array
    {
        $sizes = [];
        for ($i = 0; $i < $nbTracks * $nbSides; $i++) {
            $sizes[] = ord($raw[0x34 + $i]) * 256;
        }
        return $sizes;
    }

    /**
     * Parses the 256-byte track header block.
     *
     * Handles the realSize field edge cases:
     *   - Zero value → falls back to declared size (128 << N)
     *   - Single high-byte encoding (some dumpers) → normalised to declSize
     *   - Values exceeding 2× declSize → clamped to declSize
     *
     * @param  string $hdr 256-byte track header block
     * @return array       Track metadata including sectorInfos array
     */
    private function parseTrackHeader(string $hdr): array
    {
        $spt         = ord($hdr[0x15]);
        $sectorInfos = [];

        for ($s = 0; $s < $spt; $s++) {
            $base     = 0x18 + $s * 8;
            $sN       = ord($hdr[$base + 3]);
            $declSize = 128 << $sN;

            // Extended DSK: realSize is a 16-bit little-endian value
            $lo       = ord($hdr[$base + 6]);
            $hi       = ord($hdr[$base + 7]);
            $realSize = $lo | ($hi << 8);

            if ($realSize === 0) {
                // No realSize stored → use declared size
                $realSize = $declSize;
            } elseif ($hi === 0 && $lo !== 0 && ($lo * 256) === $declSize) {
                // Some dumpers store only the high byte (e.g. 0x20 for 8192 bytes)
                $realSize = $declSize;
            } elseif ($realSize > $declSize * 2) {
                // Safety clamp: realSize cannot exceed twice the declared size
                $realSize = $declSize;
            }

            $sectorInfos[] = [
                'C'        => ord($hdr[$base]),
                'H'        => ord($hdr[$base + 1]),
                'R'        => ord($hdr[$base + 2]),
                'N'        => $sN,
                'sr1'      => ord($hdr[$base + 4]),
                'sr2'      => ord($hdr[$base + 5]),
                'realSize' => $realSize,
            ];
        }

        return [
            'num'         => ord($hdr[0x10]),
            'side'        => ord($hdr[0x11]),
            'sectorN'     => ord($hdr[0x14]),
            'spt'         => $spt,
            'gap'         => ord($hdr[0x16]),
            'filler'      => ord($hdr[0x17]),
            'sectorInfos' => $sectorInfos,
        ];
    }

    /**
     * Reads and decodes all sector data blocks for a given track.
     *
     * Weak sectors are identified by realSize > declSize (the DSK stores
     * multiple reads of the sector consecutively to simulate random data).
     * Erased sectors are identified by SR2 bit 6 (deleted data address mark).
     *
     * @param  resource $h           Open file handle
     * @param  int      $trackPos    Byte offset of the track block in the file
     * @param  int      $spt         Sectors per track
     * @param  array    $sectorInfos Sector metadata from the track header
     * @param  int      $filler      Filler byte used to detect empty sectors
     * @return array[]               Array of decoded sector entries
     */
    private function parseSectors($h, int $trackPos, int $spt, array $sectorInfos, int $filler): array
    {
        $dataPos = $trackPos + 256;
        $sectors = [];

        foreach ($sectorInfos as $si) {
            fseek($h, $dataPos);
            $data    = fread($h, $si['realSize']);
            $dataPos += $si['realSize'];

            $declSize = 128 << $si['N'];
            $len      = strlen($data);
            $sumData  = 0;
            for ($b = 0; $b < $len; $b++) {
                $sumData += ord($data[$b]);
            }

            $isWeak   = ($si['realSize'] > $declSize);
            $isErased = (bool)($si['sr2'] & 0x40);
            $isFdcErr = (bool)(($si['sr1'] & 0x20) || ($si['sr2'] & 0x20));
            $isUsed   = $this->isSectorUsed($data, $filler);

            $sectors[] = [
                'track'       => $si['C'],
                'side'        => $si['H'],
                'C'           => $si['C'],
                'H'           => $si['H'],
                'R'           => $si['R'],
                'N'           => $si['N'],
                'declSize'    => $declSize,
                'realSize'    => $si['realSize'],
                'sumData'     => $sumData,
                'sr1'         => $si['sr1'],
                'sr2'         => $si['sr2'],
                'isWeak'      => $isWeak,
                'isErased'    => $isErased,
                'isFdcErr'    => $isFdcErr,
                'isUsed'      => $isUsed,
                'isIncomplete'=> ($si['realSize'] !== $declSize),
                'data'        => $data,
            ];
        }

        return $sectors;
    }

    /**
     * Determines whether a sector contains meaningful data.
     * A sector is considered empty if every byte equals the track filler byte.
     *
     * @param  string $data   Raw sector bytes
     * @param  int    $filler Track filler byte value
     * @return bool           True if the sector contains data other than filler
     */
    private function isSectorUsed(string $data, int $filler): bool
    {
        $len = strlen($data);
        if ($len === 0) return false;
        for ($b = 0; $b < $len; $b++) {
            if (ord($data[$b]) !== $filler) return true;
        }
        return false;
    }
}
