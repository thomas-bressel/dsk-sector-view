<?php

class CpmDirectoryParser
{
    /**
     * Parse le répertoire CP/M depuis les secteurs de la piste 0.
     *
     * @param  array $rawSectors  Tous les secteurs bruts du disque
     * @return array              Liste de fichiers CP/M trouvés
     */
    public function parse(array $rawSectors): array
    {
        $dirData = $this->extractTrack0Data($rawSectors);
        if (strlen($dirData) < 32) return [];

        $extents = $this->readExtents($dirData);
        return $this->mergeExtents($extents);
    }

    // ----------------------------------------------------------------
    // Privé
    // ----------------------------------------------------------------

    private function extractTrack0Data(array $rawSectors): string
    {
        // Collecter les secteurs de la track 0, indexés par leur ID logique (R)
        $track0 = [];
        foreach ($rawSectors as $s) {
            if ($s['track'] === 0) {
                $track0[$s['R']] = $s['data'];
            }
        }

        // Trier par ID logique (ordre croissant) pour reconstituer
        // l'ordre CP/M correct (#C1, #C2... ou #01, #02...)
        ksort($track0);

        return implode('', $track0);
    }

    private function readExtents(string $dirData): array
    {
        $extents = [];
        $total   = intdiv(strlen($dirData), 32);

        for ($i = 0; $i < $total; $i++) {
            $entry = substr($dirData, $i * 32, 32);
            $user  = ord($entry[0]);

            // 0xE5 = entrée supprimée/vide, user > 15 = pas CP/M standard
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

            // Valider que le nom contient des caractères ASCII imprimables
            // Sinon on ne lit pas un répertoire CP/M — on ignore
            if (!$this->isValidCpmName($name)) continue;

            // Blocs alloués (octets 16–31), chaque octet = numéro de bloc
            // On ignore les blocs > 200 (valeurs aberrantes = entrée corrompue/code)
            $blocks = [];
            for ($b = 16; $b <= 31; $b++) {
                $blk = ord($entry[$b]);
                if ($blk !== 0 && $blk <= 200) $blocks[] = $blk;
            }

            // Si l'extent 0 contient des blocs aberrants, ignorer cette entrée
            $extentNum = ord($entry[12]) | (ord($entry[14]) << 5);
            if ($extentNum === 0 && empty($blocks) && ord($entry[15]) > 0) continue;

            $extents[] = [
                'user'     => $user,
                'name'     => $name,
                'ext'      => $ext,
                'readonly' => (bool)(ord($entry[9])  & 0x80),
                'hidden'   => (bool)(ord($entry[10]) & 0x80),
                'extent'   => ord($entry[12]) | (ord($entry[14]) << 5),
                'rc'       => ord($entry[15]),
                'blocks'   => $blocks,
            ];
        }

        return $extents;
    }

    private function isValidCpmName(string $name): bool
    {
        $name = rtrim($name);
        if ($name === '') return false;

        // Un nom CP/M valide : lettres, chiffres, et caractères spéciaux courants
        // Pas de caractères de contrôle, pas de bytes > 0x7E
        for ($i = 0; $i < strlen($name); $i++) {
            $c = ord($name[$i]);
            // Espace autorisé (padding), mais caractères de contrôle interdits
            if ($c < 0x20 || $c > 0x7E) return false;
        }

        // Au moins un caractère non-espace
        if (trim($name) === '') return false;

        // Vérifier que le nom ne contient que des caractères CP/M valides
        // (alphanum + ! # $ % & ' ( ) - @ ^ _ ` { } ~)
        return (bool) preg_match('/^[A-Za-z0-9!#\$%&\'()\-@^_`{}~ ]+$/', $name);
    }

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

            // Seul l'extent 0 avec RC > 0 donne les infos fiables
            // On ne le traite qu'une seule fois (première occurrence)
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
