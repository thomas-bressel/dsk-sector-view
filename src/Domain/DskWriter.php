<?php

/**
 * DskWriter
 *
 * Writes a binary file in Extended CPC DSK format from the structured data
 * produced by DskParser::parse().
 *
 * Design principles:
 *  - Rebuilt from scratch: the output binary is guaranteed to differ from the
 *    original even when sector payloads are identical.
 *  - Padding areas in the disk and track headers are filled with deterministic
 *    pseudo-random bytes (fixed seed "DskToolPHP").
 *  - All protections are fully preserved:
 *      · Weak sectors   : realSize > declSize, multi-read data kept verbatim
 *      · Erased sectors : SR1/SR2 flags rewritten as-is
 *      · Oversized sectors (N=6/7/8): real sizes preserved bit-for-bit
 *  - The creator field is replaced by the creator string passed as a parameter.
 *
 * Extended DSK format (CPCEMU reference):
 *  Offset 0x00 : signature  34 bytes  "EXTENDED CPC DSK File\r\nDisk-Info\r\n"
 *  Offset 0x22 : creator    14 bytes  (NUL-padded)
 *  Offset 0x30 : nbTracks    1 byte
 *  Offset 0x31 : nbSides     1 byte
 *  Offset 0x32 : unused      2 bytes  (0x00 in Standard, ignored in Extended)
 *  Offset 0x34 : track size table  (1 byte × nbTracks × nbSides) × 256
 *  Offset 0xFF : end of disk header (256 bytes total)
 *
 *  Per track:
 *    Offset +0x00 : "Track-Info\r\n"   12 bytes
 *    Offset +0x10 : track number        1 byte
 *    Offset +0x11 : side number         1 byte
 *    Offset +0x12 : unused              2 bytes
 *    Offset +0x14 : sector size code N  1 byte  (majority value)
 *    Offset +0x15 : sectors per track   1 byte
 *    Offset +0x16 : GAP3               1 byte
 *    Offset +0x17 : filler byte         1 byte
 *    Offset +0x18 : sector info list    8 bytes × spt
 *      +0 C, +1 H, +2 R, +3 N, +4 SR1, +5 SR2, +6 realSize lo, +7 realSize hi
 *    Pseudo-random padding up to 256 bytes
 *    Followed by concatenated sector data
 *
 * @package DskToolPhp\Domain
 */
class DskWriter
{
    /** Extended DSK disk signature (34 bytes) */
    private const DISK_SIG = "EXTENDED CPC DSK File\r\nDisk-Info\r\n";

    /** Track header signature (12 bytes) */
    private const TRACK_SIG = "Track-Info\r\n";

    /** Seed for deterministic pseudo-random padding — unique to this tool */
    private const PAD_SEED = 0x41726967; // "Arig" in ASCII

    // ----------------------------------------------------------------
    // Public interface
    // ----------------------------------------------------------------

    /**
     * Writes the reconstructed DSK binary to $destPath.
     *
     * @param  array  $parsed   Result of DskParser::parse()
     * @param  string $destPath Output file path
     * @param  string $creator  Creator string (max 14 chars, NUL-padded)
     * @throws \RuntimeException If the output file cannot be created
     */
    public function write(array $parsed, string $destPath, string $creator = 'DskToolPHP'): void
    {
        $fp = fopen($destPath, 'wb');
        if (!$fp) {
            throw new \RuntimeException("Cannot create file: $destPath");
        }

        try {
            $header  = $parsed['header'];
            $tracks  = $parsed['tracks'];
            $nbSlots = $header['nbTracks'] * $header['nbSides'];

            // Index parsed tracks by their slot number (track × nbSides + side)
            $trackIndex = $this->indexTracks($tracks, $header['nbSides']);

            // Reuse original track sizes from the source DSK to avoid truncation
            // on N=6 tracks (size > 65280 bytes, not encodable as 1 byte × 256).
            $trackSizes = $parsed['trackSizes'];

            // ── Disk header ──────────────────────────────────────────────
            fwrite($fp, $this->buildDiskHeader($header, $creator, $trackSizes));

            // ── Tracks ──────────────────────────────────────────────────
            for ($slot = 0; $slot < $nbSlots; $slot++) {
                if (!isset($trackIndex[$slot])) {
                    continue; // Unformatted track — no block written
                }
                fwrite($fp, $this->buildTrack($trackIndex[$slot], $trackSizes[$slot] ?? 0));
            }
        } finally {
            fclose($fp);
        }
    }

    // ----------------------------------------------------------------
    // Disk header (256 bytes)
    // ----------------------------------------------------------------

    /**
     * Builds the 256-byte disk header block.
     *
     * @param  array  $header     Parsed disk header from DskParser
     * @param  string $creator    Creator string (max 14 chars, NUL-padded)
     * @param  int[]  $trackSizes Track size table (bytes per slot)
     * @return string             256-byte binary disk header
     */
    private function buildDiskHeader(array $header, string $creator, array $trackSizes): string
    {
        $buf = str_repeat("\x00", 256);

        // Signature (0x00, 34 bytes)
        $buf = $this->writeBytes($buf, 0x00, self::DISK_SIG);

        // Creator (0x22, 14 bytes, NUL-padded)
        $creatorPad = str_pad(substr($creator, 0, 14), 14, "\x00");
        $buf = $this->writeBytes($buf, 0x22, $creatorPad);

        // nbTracks (0x30) + nbSides (0x31)
        $buf[0x30] = chr($header['nbTracks']);
        $buf[0x31] = chr($header['nbSides']);

        // Unused 0x32-0x33: pseudo-random padding
        $buf[0x32] = chr($this->pseudoRand(0x32));
        $buf[0x33] = chr($this->pseudoRand(0x33));

        // Track size table (0x34 … 0x34 + nbSlots - 1), stored as size >> 8
        foreach ($trackSizes as $i => $size) {
            $buf[0x34 + $i] = chr($size >> 8);
        }

        // Pseudo-random padding for remaining bytes (0x34 + nbSlots … 0xFF)
        $nbSlots  = $header['nbTracks'] * $header['nbSides'];
        $padStart = 0x34 + $nbSlots;
        for ($i = $padStart; $i < 256; $i++) {
            $buf[$i] = chr($this->pseudoRand($i));
        }

        return $buf;
    }

    // ----------------------------------------------------------------
    // Track block (256-byte header + sector data)
    // ----------------------------------------------------------------

    /**
     * Builds the binary block for one track: 256-byte track header + sector data.
     * Weak sector data (realSize > declSize) is preserved verbatim.
     * The block is zero-padded to match the declared size in the disk header.
     *
     * @param  array $track        Track entry from DskParser (includes sectors array)
     * @param  int   $declaredSize Expected block size in bytes (from track size table)
     * @return string              Binary track block
     */
    private function buildTrack(array $track, int $declaredSize = 0): string
    {
        $spt = count($track['sectors']);
        $hdr = str_repeat("\x00", 256);

        // Track-Info signature (0x00, 12 bytes)
        $hdr = $this->writeBytes($hdr, 0x00, self::TRACK_SIG);

        // Unused 0x0C-0x0F: pseudo-random padding
        for ($i = 0x0C; $i <= 0x0F; $i++) {
            $hdr[$i] = chr($this->pseudoRand(0x100 + $track['num'] * 2 + $i));
        }

        // Track number (0x10) + side (0x11)
        $hdr[0x10] = chr($track['num']);
        $hdr[0x11] = chr($track['side']);

        // Unused 0x12-0x13: pseudo-random padding
        $hdr[0x12] = chr($this->pseudoRand(0x200 + $track['num']));
        $hdr[0x13] = chr($this->pseudoRand(0x201 + $track['num']));

        // Majority sector size code N (0x14)
        $hdr[0x14] = chr($track['sectorN'] ?? $this->majorityN($track['sectors']));

        // Sectors per track (0x15)
        $hdr[0x15] = chr($spt);

        // GAP3 (0x16) + filler byte (0x17)
        $hdr[0x16] = chr($track['gap']);
        $hdr[0x17] = chr($track['filler']);

        // Sector info list (0x18, 8 bytes × spt)
        foreach ($track['sectors'] as $idx => $s) {
            $base = 0x18 + $idx * 8;
            $hdr[$base + 0] = chr($s['C']);
            $hdr[$base + 1] = chr($s['H']);
            $hdr[$base + 2] = chr($s['R']);
            $hdr[$base + 3] = chr($s['N']);
            $hdr[$base + 4] = chr($s['sr1']);
            $hdr[$base + 5] = chr($s['sr2']);
            // realSize as 16-bit little-endian
            $hdr[$base + 6] = chr($s['realSize'] & 0xFF);
            $hdr[$base + 7] = chr(($s['realSize'] >> 8) & 0xFF);
        }

        // Pseudo-random padding for the remainder of the track header
        $infoEnd = 0x18 + $spt * 8;
        for ($i = $infoEnd; $i < 256; $i++) {
            $hdr[$i] = chr($this->pseudoRand(0x300 + $track['num'] * 256 + $i));
        }

        // Concatenated sector data (weak sectors: full realSize data preserved)
        $data = '';
        foreach ($track['sectors'] as $s) {
            $data .= $s['data'];
        }

        $block = $hdr . $data;

        // Zero-pad to exactly match the declared track block size
        if ($declaredSize > 0 && strlen($block) < $declaredSize) {
            $block = str_pad($block, $declaredSize, "\x00");
        }

        return $block;
    }

    // ----------------------------------------------------------------
    // Utilities
    // ----------------------------------------------------------------

    /**
     * Computes the byte size of each track slot (256-byte header + sector data).
     * Weak sectors contribute their full realSize to the slot size.
     *
     * @param  array $trackIndex Track entries indexed by slot number
     * @param  int   $nbSlots    Total number of slots (nbTracks × nbSides)
     * @return int[]             Slot sizes in bytes, indexed by slot number
     */
    private function computeTrackSizes(array $trackIndex, int $nbSlots): array
    {
        $sizes = [];
        for ($slot = 0; $slot < $nbSlots; $slot++) {
            if (!isset($trackIndex[$slot])) {
                $sizes[$slot] = 0;
                continue;
            }
            $track    = $trackIndex[$slot];
            $dataSize = 0;
            foreach ($track['sectors'] as $s) {
                $dataSize += $s['realSize']; // weak sectors: full real size
            }
            // Track header (256) + data. buildTrack handles padding to exact multiple of 256.
            $sizes[$slot] = 256 + $dataSize;
        }
        return $sizes;
    }

    /**
     * Indexes tracks by their slot number (track × nbSides + side).
     *
     * @param  array $tracks  Track array from DskParser
     * @param  int   $nbSides Number of disk sides
     * @return array          Track entries keyed by slot number
     */
    private function indexTracks(array $tracks, int $nbSides): array
    {
        $index = [];
        foreach ($tracks as $t) {
            $slot         = $t['num'] * $nbSides + $t['side'];
            $index[$slot] = $t;
        }
        return $index;
    }

    /**
     * Determines the most common sector size code N across all sectors of a track.
     * Defaults to 2 (512 bytes) if the sector list is empty.
     *
     * @param  array $sectors Sector entries for one track
     * @return int            Most frequent N value
     */
    private function majorityN(array $sectors): int
    {
        if (empty($sectors)) return 2; // 512 bytes default
        $counts = [];
        foreach ($sectors as $s) {
            $n = $s['N'];
            $counts[$n] = ($counts[$n] ?? 0) + 1;
        }
        arsort($counts);
        return (int)array_key_first($counts);
    }

    /**
     * Writes a binary string into a buffer at the given byte offset.
     *
     * @param  string $buf    Buffer to write into
     * @param  int    $offset Byte offset at which to start writing
     * @param  string $data   Binary string to write
     * @return string         Updated buffer
     */
    private function writeBytes(string $buf, int $offset, string $data): string
    {
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $buf[$offset + $i] = $data[$i];
        }
        return $buf;
    }

    /**
     * Generates a deterministic pseudo-random byte from PAD_SEED and a position.
     * Same seed + same position always yields the same byte, ensuring the output
     * binary differs from the original without introducing true randomness.
     *
     * @param  int $position Byte position used as input to the LCG formula
     * @return int           Value in range [0, 255]
     */
    private function pseudoRand(int $position): int
    {
        // Simple LCG: (seed × (position + 1) + 0x6C617269) mod 256
        return (int)(((self::PAD_SEED * ($position + 1)) + 0x6C617269) & 0xFF);
    }
}
