<?php

/**
 * ProtectionDetector
 *
 * Detects known copy-protection schemes on Amstrad CPC floppy disks (Extended DSK format).
 * Each detected protection is returned as an associative array with:
 *   - label  : display name
 *   - icon   : emoji / character
 *   - color  : hex colour for the UI
 *   - desc   : short description
 *
 * @package DskToolPhp\Service
 */
class ProtectionDetector
{
    /**
     * Runs all protection detectors against the computed disk data.
     *
     * @param  array $diskData  Result of DiskStats::compute()
     * @return array            List of detected protection entries
     */
    public function detect(array $diskData): array
    {
        $protections = [];

        foreach ($this->getDetectors() as $detector) {
            if ($detector['match']($diskData)) {
                $protections[] = [
                    'label' => $detector['label'],
                    'icon'  => $detector['icon'],
                    'color' => $detector['color'],
                    'desc'  => $detector['desc'],
                ];
            }
        }

        return $protections;
    }

    // ----------------------------------------------------------------
    // Detector definitions
    // ----------------------------------------------------------------

    /**
     * Returns the list of protection detector definitions.
     * Each entry is an associative array with keys: label, icon, color, desc, match (callable).
     *
     * @return array[] List of detector definition arrays
     */
    private function getDetectors(): array
    {
        return [

            // ── Hexagon Disk Protection - 1989 - Type 3 ──────────────────────
            // Signature: 9 sectors on track 0 (#C1-#C9) + SectorSize6 + erased sectors
            [
                'label' => 'Hexagon Type 3',
                'icon'  => '🛡',
                'color' => '#FFB300',
                'desc'  => 'Hexagon Disk Protection — 1989 — Type 3 (A.R.P). 9 sectors track 0 + N=6 + erased.',
                'match' => fn(array $d) =>
                    ($d['sizeCounts'][2] ?? 0) > 0 &&
                    ($d['sizeCounts'][6] ?? 0) > 0 &&
                    ($d['erasedSectors'] ?? 0) > 0 &&
                    ($d['track0Spt']    ?? 0) === 9,
            ],

            // ── Hexagon Disk Protection - 1989 - Type 2 ──────────────────────
            // Signature: 10 sectors on track 0 (#C1-#CA) + SectorSize6 + erased sectors
            [
                'label' => 'Hexagon Type 2',
                'icon'  => '🛡',
                'color' => '#FF8C00',
                'desc'  => 'Hexagon Disk Protection — 1989 — Type 2 (A.R.P). 10 sectors track 0 + N=6 + erased.',
                'match' => fn(array $d) =>
                    ($d['sizeCounts'][2] ?? 0) > 0 &&
                    ($d['sizeCounts'][6] ?? 0) > 0 &&
                    ($d['erasedSectors'] ?? 0) > 0 &&
                    ($d['track0Spt']    ?? 0) === 10,
            ],

            // ── Hexagon Disk Protection - 1989 - Type 1 ──────────────────────
            // Signature: SectorSize6 with no erased sectors
            [
                'label' => 'Hexagon Type 1',
                'icon'  => '🛡',
                'color' => '#FF6600',
                'desc'  => 'Hexagon Disk Protection — 1989 — Type 1 (A.R.P). N=6 sectors, no erased sectors.',
                'match' => fn(array $d) =>
                    ($d['sizeCounts'][6] ?? 0) > 0 &&
                    ($d['erasedSectors'] ?? 0) === 0 &&
                    ($d['sizeCounts'][2] ?? 0) > 0,
            ],

            // ── Weak Sectors ─────────────────────────────────────────────────
            [
                'label' => 'Weak Sectors',
                'icon'  => '⚠',
                'color' => '#FF4444',
                'desc'  => 'Weak sectors (multi-read data). Used by various protection schemes.',
                'match' => fn(array $d) => ($d['weakSectors'] ?? 0) > 0,
            ],

            // ── Track 41 in use ───────────────────────────────────────────────
            // Some protections use a 42nd track (index 41) beyond the standard 40-track layout
            [
                'label' => 'Track 41 used',
                'icon'  => '📍',
                'color' => '#00E5FF',
                'desc'  => 'Track 41 in use (beyond the standard 40-track CPC layout).',
                'match' => fn(array $d) => ($d['nbTracks'] ?? 0) > 41,
            ],

        ];
    }
}
