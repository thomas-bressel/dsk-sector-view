<?php

/**
 * ProtectionDetector
 *
 * Détecte les protections connues sur les disquettes Amstrad CPC (format Extended DSK).
 * Chaque protection retourne un tableau avec :
 *   - label  : nom affiché
 *   - icon   : emoji/caractère
 *   - color  : couleur hex pour l'UI
 *   - desc   : short description
 *
 * @package DskToolPhp\Service
 */
class ProtectionDetector
{
    /**
     * Lance la détection sur les données du disque parsé.
     *
     * @param  array $diskData  Résultat de DiskStats::compute()
     * @return array            Liste de protections détectées
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
    // Détecteurs déclarés
    // ----------------------------------------------------------------

    private function getDetectors(): array
    {
        return [

            // ── Hexagon Disk Protection - 1989 - Type 3 ──────────────────────
            // Signature : 9 secteurs track 0 (#C1-#C9) + SectorSize6 + SectorErased
            [
                'label' => 'Hexagon Type 3',
                'icon'  => '🛡',
                'color' => '#FFB300',
                'desc'  => 'Hexagon Disk Protection — 1989 — Type 3 (A.R.P). 9 secteurs track 0 + N=6 + effacés.',
                'match' => fn(array $d) =>
                    ($d['sizeCounts'][2] ?? 0) > 0 &&
                    ($d['sizeCounts'][6] ?? 0) > 0 &&
                    ($d['erasedSectors'] ?? 0) > 0 &&
                    ($d['track0Spt']    ?? 0) === 9,
            ],

            // ── Hexagon Disk Protection - 1989 - Type 2 ──────────────────────
            // Signature : 10 secteurs track 0 (#C1-#CA) + SectorSize6 + SectorErased
            [
                'label' => 'Hexagon Type 2',
                'icon'  => '🛡',
                'color' => '#FF8C00',
                'desc'  => 'Hexagon Disk Protection — 1989 — Type 2 (A.R.P). 10 secteurs track 0 + N=6 + effacés.',
                'match' => fn(array $d) =>
                    ($d['sizeCounts'][2] ?? 0) > 0 &&
                    ($d['sizeCounts'][6] ?? 0) > 0 &&
                    ($d['erasedSectors'] ?? 0) > 0 &&
                    ($d['track0Spt']    ?? 0) === 10,
            ],

            // ── Hexagon Disk Protection - 1989 - Type 1 ──────────────────────
            // Signature : SectorSize6 sans secteurs effacés
            [
                'label' => 'Hexagon Type 1',
                'icon'  => '🛡',
                'color' => '#FF6600',
                'desc'  => 'Hexagon Disk Protection — 1989 — Type 1 (A.R.P). Secteurs N=6 sans secteurs effacés.',
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
                'desc'  => 'Secteurs faibles (données multi-lecture). Utilisé par diverses protections.',
                'match' => fn(array $d) => ($d['weakSectors'] ?? 0) > 0,
            ],

            // ── Track 41 utilisée ─────────────────────────────────────────────
            // Certaines protections utilisent une 42ème piste (index 41) hors norme
            [
                'label' => 'Track 41 used',
                'icon'  => '📍',
                'color' => '#00E5FF',
                'desc'  => 'Piste 41 utilisée (hors norme CPC standard à 40 pistes).',
                'match' => fn(array $d) => ($d['nbTracks'] ?? 0) > 41,
            ],

        ];
    }
}
