<?php

/**
 * DiskStats
 *
 * Computes all aggregated metrics from the raw data produced by DskParser
 * and returns a single flat array consumed by the view templates.
 *
 * Computed fields include:
 *   - Disk identity  : format, creator, track/side counts
 *   - Sector counts  : total, used, weak, erased, incomplete, FDC errors
 *   - Size breakdown : one count per sector size code N (0–8, plus ">8")
 *   - Byte totals    : declared bytes, real bytes, sum-data
 *   - Track sizes    : declared track size table vs real data
 *   - Map stats      : pre-computed values for the MAP tab statistics bar
 *   - Track data     : per-track summary for the TRACKS tab
 *
 * @package DskToolPhp\Domain
 */
class DiskStats
{
    /**
     * Computes all disk metrics from raw parsed data.
     *
     * @param  array $raw Result of DskParser::parse(), enriched with 'files' from CpmDirectoryParser
     * @return array      Flat array of all computed metrics, ready for the view layer
     */
    public function compute(array $raw): array
    {
        $sectors = $raw['rawSectors'];
        $tracks  = $raw['tracks'];

        // Sectors per track on track 0 (used to distinguish Hexagon Type 2 vs Type 3)
        $track0Spt = 0;
        foreach ($raw['tracks'] as $t) {
            if ($t['num'] === 0) { $track0Spt = $t['spt']; break; }
        }

        // Number of actually formatted tracks (with at least one sector)
        $tracksFormatted = count($raw['tracks']);

        $totalSectors       = count($sectors);
        $usedSectors        = 0;
        $weakSectors        = 0;
        $erasedSectors      = 0;
        $incompleteSectors  = 0;
        $totalWeakSectors   = 0;
        $fdcErrors          = 0;
        $sizeCounts         = array_fill(0, 10, 0); // indices 0–8 = size N; index 9 = N > 8
        $totalDeclaredBytes = 0;
        $totalRealBytes     = 0;
        $totalSumData       = 0;

        foreach ($sectors as $s) {
            if ($s['isUsed'])       $usedSectors++;
            if ($s['isWeak'])     { $weakSectors++; $totalWeakSectors++; }
            if ($s['isErased'])     $erasedSectors++;
            if ($s['isWeak'] && $s['isErased']) $totalWeakSectors++;
            if ($s['isIncomplete']) $incompleteSectors++;
            if ($s['isFdcErr'] ?? false) $fdcErrors++;

            $n = $s['N'] <= 8 ? $s['N'] : 9;
            $sizeCounts[$n]++;

            $totalDeclaredBytes += $s['declSize'];
            $totalRealBytes     += $s['realSize'];
            $totalSumData       += $s['sumData'];
        }

        // Declared track data size = sum of raw track sizes minus 256-byte header per track
        // (aligns with CPC-Power convention: data bytes only, not the track header block)
        $rawTrackSizes      = array_filter($raw['trackSizes']);
        $tracksDeclaredSize = array_sum($rawTrackSizes) - (count($rawTrackSizes) * 256);

        return [
            // Disk identity
            'path'               => $raw['path'],
            'fileSize'           => $raw['fileSize'],
            'format'             => $raw['header']['format'],
            'creator'            => $raw['header']['creator'],
            'nbTracks'           => $raw['header']['nbTracks'],
            'nbSides'            => $raw['header']['nbSides'],

            // Raw data for tabs
            'tracks'             => $this->computeTracksData($tracks),
            'sectors'            => $sectors,
            'files'              => $raw['files'] ?? [],

            // Global sector counts
            'totalSectors'       => $totalSectors,
            'usedSectors'        => $usedSectors,
            'weakSectors'        => $weakSectors,
            'erasedSectors'      => $erasedSectors,
            'incompleteSectors'  => $incompleteSectors,
            'totalWeakSectors'   => $totalWeakSectors,
            'fdcErrors'          => $fdcErrors,
            'sizeCounts'         => $sizeCounts,

            // Byte totals
            'totalDeclaredBytes' => $totalDeclaredBytes,
            'totalRealBytes'     => $totalRealBytes,
            'totalSumData'       => $totalSumData,
            'tracksDeclaredSize' => $tracksDeclaredSize,

            // Misc
            'track0Spt'          => $track0Spt,
            'tracksFormatted'    => $tracksFormatted,

            // Pre-computed stats for the MAP tab
            'mapStats'           => $this->computeMapStats($sectors),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Pre-computes the statistics displayed in the MAP tab statistics bar.
     *
     * @param  array[] $sectors Flat sector list
     * @return array            Associative array of map statistics
     */
    private function computeMapStats(array $sectors): array
    {
        $sizeCounts = array_fill(0, 10, 0);
        $erased     = 0;
        $weak       = 0;
        $weakTotal  = 0;
        $incomplete = 0;

        foreach ($sectors as $s) {
            $n = $s['N'] <= 8 ? $s['N'] : 9;
            $sizeCounts[$n]++;
            if ($s['isErased'])   $erased++;
            if ($s['isWeak'])   { $weak++; $weakTotal++; }
            if ($s['isWeak'] && $s['isErased']) $weakTotal++;
            if ($s['isIncomplete']) $incomplete++;
        }

        return [
            'sizeCounts' => $sizeCounts,
            'erased'     => $erased,
            'weak'       => $weak,
            'weakTotal'  => $weakTotal,
            'incomplete' => $incomplete,
            'gaps'       => 0,
            'gap2'       => 0,
        ];
    }

    /**
     * Builds the per-track summary array consumed by the TRACKS tab.
     *
     * @param  array[] $tracks Track array from DskParser
     * @return array[]         Per-track summary entries
     */
    private function computeTracksData(array $tracks): array
    {
        $result = [];
        foreach ($tracks as $t) {
            $totalBytes = 0;
            foreach ($t['sectorInfos'] as $si) {
                $totalBytes += 128 << $si['N'];
            }

            $result[] = [
                'num'        => $t['num'],
                'side'       => $t['side'],
                'spt'        => $t['spt'],
                'gap'        => $t['gap'],
                'filler'     => $t['filler'],
                'totalBytes' => $totalBytes,
                'sumData'    => array_sum(array_column($t['sectors'], 'sumData')),
            ];
        }
        return $result;
    }
}
