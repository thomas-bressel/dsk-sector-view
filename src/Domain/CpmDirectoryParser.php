<?php

/**
 * CpmDirectoryParser
 *
 * Parses the CP/M directory (FAT) stored in the sectors of track 0 and
 * returns a list of files present on the disk.
 *
 * CP/M directory structure overview:
 *   - Each directory entry is 32 bytes (an "extent").
 *   - Up to 64 entries fit in 4 sectors of 512 bytes each.
 *   - Byte 0          : user number (0–15; 0xE5 = deleted/free entry)
 *   - Bytes 1–8       : filename, padded with spaces (bit 7 may be set for attributes)
 *   - Bytes 9–11      : extension, padded with spaces (bit 7 = read-only / hidden / archive)
 *   - Byte 12         : extent number (low 5 bits)
 *   - Byte 13         : reserved
 *   - Byte 14         : extent number (high bits, shifted by 5)
 *   - Byte 15         : RC — record count (number of 128-byte records in this extent)
 *   - Bytes 16–31     : allocation block numbers
 *
 * Multiple extents with the same user/name/ext are merged into a single file entry.
 *
 * @package DskToolPhp\Domain
 */
class CpmDirectoryParser
{
    /**
     * Parses the CP/M directory from the raw sectors of track 0.
     *
     * @param  array $rawSectors All raw sectors from the disk (flat list)
     * @return array             List of CP/M file entries; empty if no valid directory found
     */
    public function parse(array $rawSectors): array
    {
        $dirData = $this->extractTrack0Data($rawSectors);
        if (strlen($dirData) < 32) return [];

        $extents = $this->readExtents($dirData);
        return $this->mergeExtents($extents);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Concatenates the data of all track-0 sectors in logical order (by sector ID R).
     *
     * @param  array  $rawSectors Full flat sector list
     * @return string             Concatenated track-0 data (the raw directory buffer)
     */
    private function extractTrack0Data(array $rawSectors): string
    {
        $track0 = [];
        foreach ($rawSectors as $s) {
            if ($s['track'] === 0) {
                $track0[$s['R']] = $s['data'];
            }
        }

        // Sort by logical ID to reconstruct CP/M order (#C1, #C2… or #01, #02…)
        ksort($track0);

        return implode('', $track0);
    }

    /**
     * Reads all valid 32-byte CP/M directory extents from the directory buffer.
     * Skips deleted entries (user = 0xE5) and non-standard user numbers (> 15).
     * Also skips entries with non-printable ASCII characters in the filename.
     *
     * @param  string $dirData Raw directory buffer
     * @return array[]         List of raw extent arrays
     */
    private function readExtents(string $dirData): array
    {
        $extents = [];
        $total   = intdiv(strlen($dirData), 32);

        for ($i = 0; $i < $total; $i++) {
            $entry = substr($dirData, $i * 32, 32);
            $user  = ord($entry[0]);

            // 0xE5 = deleted/free slot; user > 15 = not standard CP/M
            if ($user === 0xE5 || $user > 15) continue;

            $name = '';
            for ($c = 1; $c <= 8; $c++) {
                $name .= chr(ord($entry[$c]) & 0x7F);
            }
            $name = rtrim($name);

            $ext = '';
            for ($c = 9; $c <= 11; $c++) {
                $ext .= chr(ord($entry[$c]) & 0x7F);
            }
            $ext = rtrim($ext);

            // Reject entries with non-printable characters (not a CP/M directory)
            if (!$this->isValidCpmName($name)) continue;

            // Allocation blocks (bytes 16–31); ignore block numbers > 200 (corrupt)
            $blocks = [];
            for ($b = 16; $b <= 31; $b++) {
                $blk = ord($entry[$b]);
                if ($blk !== 0 && $blk <= 200) $blocks[] = $blk;
            }

            $extentNum = ord($entry[12]) | (ord($entry[14]) << 5);
            if ($extentNum === 0 && empty($blocks) && ord($entry[15]) > 0) continue;

            $extents[] = [
                'user'     => $user,
                'name'     => $name,
                'ext'      => $ext,
                'readonly' => (bool)(ord($entry[9])  & 0x80),
                'hidden'   => (bool)(ord($entry[10]) & 0x80),
                'extent'   => $extentNum,
                'rc'       => ord($entry[15]),
                'blocks'   => $blocks,
            ];
        }

        return $extents;
    }

    /**
     * Validates that a filename contains only printable CP/M-compatible ASCII characters.
     * Accepted characters: alphanumeric and ! # $ % & ' ( ) - @ ^ _ ` { } ~ space.
     *
     * @param  string $name Filename string (attributes bit already masked off)
     * @return bool         True if the name is a valid CP/M filename
     */
    private function isValidCpmName(string $name): bool
    {
        $name = rtrim($name);
        if ($name === '') return false;

        for ($i = 0; $i < strlen($name); $i++) {
            $c = ord($name[$i]);
            if ($c < 0x20 || $c > 0x7E) return false;
        }

        if (trim($name) === '') return false;

        return (bool) preg_match('/^[A-Za-z0-9!#\$%&\'()\-@^_`{}~ ]+$/', $name);
    }

    /**
     * Merges multiple extents belonging to the same file into a single file entry.
     * Only extent 0 with RC > 0 is used to determine the file size.
     *
     * Returned file entry structure:
     * <code>
     * [
     *   'user'       => int,     // CP/M user number (0–15)
     *   'name'       => string,  // filename without extension
     *   'ext'        => string,  // extension
     *   'readonly'   => bool,    // read-only attribute (bit 7 of ext byte 0)
     *   'hidden'     => bool,    // hidden attribute (bit 7 of ext byte 1)
     *   'sizeKo'     => int,     // approximate size in KB (RC × 128 / 1024)
     *   'firstBlock' => int|null,// first allocation block number, or null
     *   'allBlocks'  => int[],   // all allocation block numbers from extent 0
     * ]
     * </code>
     *
     * @param  array[] $extents Raw extents from readExtents()
     * @return array[]          Merged file entries
     */
    private function mergeExtents(array $extents): array
    {
        $seen = [];

        foreach ($extents as $e) {
            $key = $e['user'] . '/' . $e['name'] . '.' . $e['ext'];

            if (!isset($seen[$key])) {
                $seen[$key] = [
                    'user'      => $e['user'],
                    'name'      => $e['name'],
                    'ext'       => $e['ext'],
                    'readonly'  => $e['readonly'],
                    'hidden'    => $e['hidden'],
                    'rc'        => 0,
                    'allBlocks' => [],
                ];
            }

            if ($e['extent'] === 0 && $e['rc'] > 0 && empty($seen[$key]['allBlocks'])) {
                $seen[$key]['readonly']  = $e['readonly'];
                $seen[$key]['hidden']    = $e['hidden'];
                $seen[$key]['allBlocks'] = $e['blocks'];
                $seen[$key]['rc']        = $e['rc'];
            } elseif ($e['extent'] > 0) {
                $seen[$key]['rc'] += $e['rc'];
            }
        }

        $files = [];
        foreach ($seen as $e) {
            $allBlocks  = $e['allBlocks'] ?? [];
            $firstBlock = $allBlocks[0] ?? null;

            $files[] = [
                'user'       => $e['user'],
                'name'       => $e['name'],
                'ext'        => $e['ext'],
                'readonly'   => $e['readonly'],
                'hidden'     => $e['hidden'],
                'sizeKo'     => max(1, (int)floor($e['rc'] * 128 / 1024)),
                'firstBlock' => $firstBlock,
                'allBlocks'  => $e['allBlocks'] ?? [],
            ];
        }

        return $files;
    }
}
