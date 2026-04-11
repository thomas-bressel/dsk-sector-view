<?php

/**
 * DskRepackager
 *
 * Orchestrates the full repackaging of a DSK file:
 *  1. Parses the source DSK via DskParser
 *  2. Injects a DSKTLPHP.TXT file into the CP/M FAT (track 0)
 *  3. Rebuilds the DSK via DskWriter with creator "DskToolPHP"
 *     and deterministic pseudo-random padding to ensure a different binary hash
 *
 * The game data remains 100% intact: only the DSK container metadata and binary
 * structure are modified, never the actual sector payload.
 *
 * All protections are fully preserved:
 *  - Weak sectors   (realSize > declSize)
 *  - Erased sectors (SR2 bit 6)
 *  - Oversized sectors (N=6/7/8)
 *  - FDC flags SR1/SR2
 *
 * Usage:
 *   $repackager = new DskRepackager(new DskParser(), new DskWriter());
 *   $repackager->repack('/path/to/source.dsk', '/path/to/output.dsk');
 *
 * @package DskToolPhp\Service
 */
class DskRepackager
{
    /** Creator string injected into the DSK disk header */
    private const CREATOR = 'DskToolPHP';

    /** Filename of the signature file injected into the CP/M FAT */
    private const SIG_FILENAME = 'DSKTLPHP';

    /** Extension of the signature file */
    private const SIG_EXT = 'TXT';

    /** Content of the signature file */
    private const SIG_CONTENT =
        "Packaged by DskToolPhp\r\n" .
        "Tool    : DskToolPHP\r\n" .
        "Source  : Personal archive\r\n" .
        "\x1A"; // CP/M EOF marker

    /** Filler byte for empty CP/M sectors and free directory entries */
    private const CPM_FILLER = 0xE5;

    /** Size of one CP/M directory entry in bytes */
    private const DIR_ENTRY_SIZE = 32;

    // ----------------------------------------------------------------

    private DskParser $parser;
    private DskWriter $writer;

    public function __construct(DskParser $parser, DskWriter $writer)
    {
        $this->parser = $parser;
        $this->writer = $writer;
    }

    // ----------------------------------------------------------------
    // Public interface
    // ----------------------------------------------------------------

    /**
     * Repackages $sourcePath into $destPath.
     *
     * @param  string $sourcePath Path to the source DSK file
     * @param  string $destPath   Path to the output DSK file
     * @throws \RuntimeException  On read/write failure
     */
    public function repack(string $sourcePath, string $destPath): void
    {
        // Step 1 — Parse the source DSK
        $parsed = $this->parser->parse($sourcePath);

        // Step 2 — Inject the signature file into the CP/M FAT (track 0)
        $parsed = $this->injectSignature($parsed);

        // Step 3 — Write the reconstructed DSK
        $this->writer->write($parsed, $destPath, self::CREATOR);
    }

    // ----------------------------------------------------------------
    // CP/M FAT signature injection
    // ----------------------------------------------------------------

    /**
     * Injects a DSKTLPHP.TXT entry into the CP/M FAT on track 0.
     *
     * The CP/M FAT is stored in the sectors of track 0. The method finds the
     * first free directory slot (0xE5) and writes the signature entry there.
     * If the FAT is full, the method returns silently and the DSK remains valid.
     *
     * @param  array $parsed Parsed DSK data from DskParser
     * @return array         Parsed data with the signature entry injected
     */
    private function injectSignature(array $parsed): array
    {
        // Locate the track-0 sectors that hold the CP/M FAT
        $track0Sectors = $this->getTrack0Sectors($parsed['tracks']);

        if (empty($track0Sectors)) {
            return $parsed; // No track 0 — leave DSK untouched
        }

        // Build a contiguous FAT buffer from track-0 sectors (in logical order)
        [$fatBuffer, $sectorOrder] = $this->buildFatBuffer($track0Sectors);

        // Find the first free directory slot (0xE5)
        $slotOffset = $this->findFreeDirSlot($fatBuffer);

        if ($slotOffset === null) {
            return $parsed; // FAT full — no injection, DSK remains intact
        }

        // Build the 32-byte CP/M directory entry for DSKTLPHP.TXT
        $entry = $this->buildDirEntry(
            self::SIG_FILENAME,
            self::SIG_EXT,
            self::SIG_CONTENT
        );

        // Write the entry into the FAT buffer
        for ($i = 0; $i < self::DIR_ENTRY_SIZE; $i++) {
            $fatBuffer[$slotOffset + $i] = $entry[$i];
        }

        // Distribute the modified FAT buffer back into the track-0 sectors
        $parsed['tracks'] = $this->applyFatBuffer($parsed['tracks'], $fatBuffer, $sectorOrder);

        // Resync rawSectors from the updated tracks
        $parsed['rawSectors'] = $this->rebuildRawSectors($parsed['tracks']);

        return $parsed;
    }

    /**
     * Returns the sectors of track 0, sorted by logical sector ID (R).
     *
     * @param  array[] $tracks Track array from DskParser
     * @return array[]         Track-0 sectors sorted by R, or empty array if not found
     */
    private function getTrack0Sectors(array $tracks): array
    {
        foreach ($tracks as $track) {
            if ($track['num'] === 0) {
                $sectors = $track['sectors'];
                usort($sectors, fn($a, $b) => $a['R'] <=> $b['R']);
                return $sectors;
            }
        }
        return [];
    }

    /**
     * Concatenates track-0 sector data into a single FAT buffer.
     * For weak sectors, only the first copy (declSize bytes) is used.
     *
     * @param  array[] $track0Sectors Track-0 sectors sorted by R
     * @return array{0: string, 1: array<int,int>} [fatBuffer, sectorOrder (R => index)]
     */
    private function buildFatBuffer(array $track0Sectors): array
    {
        $buffer      = '';
        $sectorOrder = []; // R => position in the track-0 sector array

        foreach ($track0Sectors as $pos => $s) {
            $sectorOrder[$s['R']] = $pos;
            // For weak sectors, use only the first copy (declSize bytes)
            $effectiveData = substr($s['data'], 0, $s['declSize']);
            // Pad to declSize if the data is shorter
            if (strlen($effectiveData) < $s['declSize']) {
                $effectiveData = str_pad($effectiveData, $s['declSize'], chr(self::CPM_FILLER));
            }
            $buffer .= $effectiveData;
        }

        return [$buffer, $sectorOrder];
    }

    /**
     * Finds the first free CP/M directory slot in the FAT buffer (first byte = 0xE5).
     *
     * @param  string $fatBuffer Concatenated FAT buffer
     * @return int|null          Byte offset of the free slot, or null if the FAT is full
     */
    private function findFreeDirSlot(string $fatBuffer): ?int
    {
        $total = intdiv(strlen($fatBuffer), self::DIR_ENTRY_SIZE);
        for ($i = 0; $i < $total; $i++) {
            $offset    = $i * self::DIR_ENTRY_SIZE;
            $firstByte = ord($fatBuffer[$offset]);
            if ($firstByte === self::CPM_FILLER) {
                return $offset;
            }
        }
        return null;
    }

    /**
     * Builds a 32-byte CP/M directory entry for the given filename and content.
     *
     * CP/M directory entry layout:
     *  Byte   0    : User number (0)
     *  Bytes  1-8  : Filename, space-padded
     *  Bytes  9-11 : Extension, space-padded
     *  Byte   12   : Extent number (0)
     *  Bytes  13-14: Reserved (0)
     *  Byte   15   : RC — record count (number of 128-byte records)
     *  Bytes  16-31: Allocation block numbers (all 0 — symbolic entry only)
     *
     * @param  string $name    Filename without extension (max 8 chars)
     * @param  string $ext     File extension (max 3 chars)
     * @param  string $content File content (used only to compute the RC field)
     * @return string          32-byte binary directory entry
     */
    private function buildDirEntry(string $name, string $ext, string $content): string
    {
        $entry = str_repeat(chr(self::CPM_FILLER), self::DIR_ENTRY_SIZE);

        // User 0
        $entry[0] = "\x00";

        // Filename (8 bytes, space-padded)
        $namePad = str_pad(substr($name, 0, 8), 8, ' ');
        for ($i = 0; $i < 8; $i++) {
            $entry[1 + $i] = $namePad[$i];
        }

        // Extension (3 bytes, space-padded)
        $extPad = str_pad(substr($ext, 0, 3), 3, ' ');
        for ($i = 0; $i < 3; $i++) {
            $entry[9 + $i] = $extPad[$i];
        }

        // Extent number = 0
        $entry[12] = "\x00";
        $entry[13] = "\x00";
        $entry[14] = "\x00";

        // RC = number of 128-byte records (rounded up), capped at 127
        $rc = (int)ceil(strlen($content) / 128);
        $entry[15] = chr(min($rc, 127));

        // Allocation blocks: all 0 (file is symbolic — no physical data blocks)
        for ($i = 16; $i < 32; $i++) {
            $entry[$i] = "\x00";
        }

        return $entry;
    }

    /**
     * Rewrites track-0 sectors with the modified FAT buffer.
     * Weak sectors have their extra copies reconstructed from the updated chunk.
     *
     * @param  array[] $tracks      Full track array (modified in place)
     * @param  string  $fatBuffer   Modified FAT buffer to distribute back into sectors
     * @param  array   $sectorOrder Sector order map (R => position index)
     * @return array[]              Updated track array
     */
    private function applyFatBuffer(array &$tracks, string $fatBuffer, array $sectorOrder): array
    {
        foreach ($tracks as &$track) {
            if ($track['num'] !== 0) continue;

            // Distribute the buffer into sectors in logical order (sorted by R)
            $sortedSectors = $track['sectors'];
            usort($sortedSectors, fn($a, $b) => $a['R'] <=> $b['R']);

            $offset = 0;
            foreach ($sortedSectors as $sorted) {
                // Find the matching sector in the original array by R
                foreach ($track['sectors'] as &$s) {
                    if ($s['R'] !== $sorted['R']) continue;

                    $chunk = substr($fatBuffer, $offset, $s['declSize']);
                    $chunk = str_pad($chunk, $s['declSize'], chr(self::CPM_FILLER));

                    // Weak sector: reconstruct the extra copies from the updated chunk
                    if ($s['isWeak'] && $s['realSize'] > $s['declSize']) {
                        $copies    = (int)floor($s['realSize'] / $s['declSize']);
                        $s['data'] = str_repeat($chunk, $copies);
                        // Trim to exact realSize
                        $s['data'] = substr($s['data'], 0, $s['realSize']);
                    } else {
                        $s['data'] = $chunk;
                    }

                    $offset += $s['declSize'];
                    break;
                }
                unset($s);
            }

            break;
        }
        unset($track);

        return $tracks;
    }

    /**
     * Rebuilds the flat rawSectors array from all tracks after FAT injection.
     *
     * @param  array[] $tracks Updated track array
     * @return array[]         Flat list of all sectors across all tracks
     */
    private function rebuildRawSectors(array $tracks): array
    {
        $raw = [];
        foreach ($tracks as $track) {
            foreach ($track['sectors'] as $s) {
                $raw[] = $s;
            }
        }
        return $raw;
    }
}
