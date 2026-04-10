<?php

/**
 * DskWriter
 *
 * Écrit un fichier binaire au format Extended CPC DSK depuis les données
 * structurées produites par DskParser::parse().
 *
 * Principes :
 *  - Reconstruction from scratch : le binaire produit est garanti différent
 *    de l'original même si les données secteur sont identiques.
 *  - Les zones de padding des headers (disque + pistes) sont remplies avec
 *    des octets pseudo-aléatoires déterministes (seed fixe DskToolPHP).
 *  - Les protections sont préservées intégralement :
 *      · Weak sectors  : realSize > declSize, données multi-lecture conservées
 *      · Erased sectors: flags SR1/SR2 réécrits tels quels
 *      · Secteurs N=6/7/8 : tailles réelles conservées bit pour bit
 *  - Le champ creator est remplacé par le creator passé en paramètre.
 *
 * Format Extended DSK (référence CPCEMU) :
 *  Offset 0x00 : signature 34 octets  "EXTENDED CPC DSK File\r\nDisk-Info\r\n"
 *  Offset 0x22 : creator    14 octets  (padded avec 0x00)
 *  Offset 0x30 : nbTracks    1 octet
 *  Offset 0x31 : nbSides     1 octet
 *  Offset 0x32 : unused      2 octets  (0x00 en Standard, ignoré en Extended)
 *  Offset 0x34 : track size table  (1 octet × nbTracks × nbSides) × 256
 *  Offset 0xFF : fin du header disque (256 octets total)
 *
 *  Pour chaque piste :
 *    Offset +0x00 : "Track-Info\r\n"  10 octets
 *    Offset +0x10 : track number       1 octet
 *    Offset +0x11 : side number        1 octet
 *    Offset +0x12 : unused             2 octets
 *    Offset +0x14 : sector size (N)    1 octet  (taille majoritaire)
 *    Offset +0x15 : sectors per track  1 octet
 *    Offset +0x16 : GAP3              1 octet
 *    Offset +0x17 : filler byte        1 octet
 *    Offset +0x18 : sector info list  8 octets × spt
 *      +0 C, +1 H, +2 R, +3 N, +4 SR1, +5 SR2, +6 realSize lo, +7 realSize hi
 *    Padding pseudo-aléatoire jusqu'à 256 octets
 *    Puis données secteurs concaténées
 */
class DskWriter
{
    /** Signature Extended DSK (34 octets) */
    private const DISK_SIG = "EXTENDED CPC DSK File\r\nDisk-Info\r\n";

    /** Signature Track-Info (12 octets) */
    private const TRACK_SIG = "Track-Info\r\n";

    /** Seed pour le padding pseudo-aléatoire — déterministe, propre à cet outil */
    private const PAD_SEED = 0x41726967; // "Arig" en ASCII

    // ----------------------------------------------------------------
    // Interface publique
    // ----------------------------------------------------------------

    /**
     * Écrit le DSK reconstruit dans $destPath.
     *
     * @param  array  $parsed   Résultat de DskParser::parse()
     * @param  string $destPath Chemin du fichier de sortie
     * @param  string $creator  Nom du créateur (max 14 chars)
     * @throws \RuntimeException
     */
    public function write(array $parsed, string $destPath, string $creator = 'DskToolPHP'): void
    {
        $fp = fopen($destPath, 'wb');
        if (!$fp) {
            throw new \RuntimeException("Impossible de créer le fichier : $destPath");
        }

        try {
            $header  = $parsed['header'];
            $tracks  = $parsed['tracks'];
            $nbSlots = $header['nbTracks'] * $header['nbSides'];

            // Indexer les pistes parsées par leur slot (t * nbSides + side)
            $trackIndex = $this->indexTracks($tracks, $header['nbSides']);

            // Réutiliser les tailles originales du DSK source : évite la troncature
            // sur les pistes N=6 (taille > 65280 octets, non encodable sur 1 octet × 256).
            $trackSizes = $parsed['trackSizes'];

            // ── Header disque ────────────────────────────────────────────
            fwrite($fp, $this->buildDiskHeader($header, $creator, $trackSizes));

            // ── Pistes ──────────────────────────────────────────────────
            for ($slot = 0; $slot < $nbSlots; $slot++) {
                if (!isset($trackIndex[$slot])) {
                    continue; // piste non formatée → aucun bloc écrit
                }
                fwrite($fp, $this->buildTrack($trackIndex[$slot], $trackSizes[$slot] ?? 0));
            }
        } finally {
            fclose($fp);
        }
    }

    // ----------------------------------------------------------------
    // Construction du header disque (256 octets)
    // ----------------------------------------------------------------

    private function buildDiskHeader(array $header, string $creator, array $trackSizes): string
    {
        $buf = str_repeat("\x00", 256);

        // Signature (0x00, 34 octets)
        $buf = $this->writeBytes($buf, 0x00, self::DISK_SIG);

        // Creator (0x22, 14 octets, padded 0x00)
        $creatorPad = substr($creator, 0, 14);
        $creatorPad = str_pad($creatorPad, 14, "\x00");
        $buf = $this->writeBytes($buf, 0x22, $creatorPad);

        // nbTracks (0x30) + nbSides (0x31)
        $buf[0x30] = chr($header['nbTracks']);
        $buf[0x31] = chr($header['nbSides']);

        // Unused 0x32-0x33 : on met du pseudo-aléatoire
        $buf[0x32] = chr($this->pseudoRand(0x32));
        $buf[0x33] = chr($this->pseudoRand(0x33));

        // Table des tailles de piste (0x34 … 0x34 + nbSlots - 1)
        foreach ($trackSizes as $i => $size) {
            $buf[0x34 + $i] = chr($size >> 8); // stocké en multiple de 256
        }

        // Padding pseudo-aléatoire sur les octets restants (0x34 + nbSlots … 0xFF)
        $nbSlots  = $header['nbTracks'] * $header['nbSides'];
        $padStart = 0x34 + $nbSlots;
        for ($i = $padStart; $i < 256; $i++) {
            $buf[$i] = chr($this->pseudoRand($i));
        }

        return $buf;
    }

    // ----------------------------------------------------------------
    // Construction d'une piste (header 256 octets + données secteurs)
    // ----------------------------------------------------------------

    private function buildTrack(array $track, int $declaredSize = 0): string
    {
        $spt     = count($track['sectors']);
        $hdr     = str_repeat("\x00", 256);

        // Signature Track-Info (0x00, 12 octets)
        $hdr = $this->writeBytes($hdr, 0x00, self::TRACK_SIG);

        // Unused 0x0C-0x0F : pseudo-aléatoire
        for ($i = 0x0C; $i <= 0x0F; $i++) {
            $hdr[$i] = chr($this->pseudoRand(0x100 + $track['num'] * 2 + $i));
        }

        // Track number (0x10) + Side (0x11)
        $hdr[0x10] = chr($track['num']);
        $hdr[0x11] = chr($track['side']);

        // Unused 0x12-0x13 : pseudo-aléatoire
        $hdr[0x12] = chr($this->pseudoRand(0x200 + $track['num']));
        $hdr[0x13] = chr($this->pseudoRand(0x201 + $track['num']));

        // Sector size majoritaire (0x14)
        $hdr[0x14] = chr($track['sectorN'] ?? $this->majorityN($track['sectors']));

        // SPT (0x15)
        $hdr[0x15] = chr($spt);

        // GAP3 (0x16) + Filler (0x17)
        $hdr[0x16] = chr($track['gap']);
        $hdr[0x17] = chr($track['filler']);

        // Sector info list (0x18, 8 octets × spt)
        foreach ($track['sectors'] as $idx => $s) {
            $base = 0x18 + $idx * 8;
            $hdr[$base + 0] = chr($s['C']);
            $hdr[$base + 1] = chr($s['H']);
            $hdr[$base + 2] = chr($s['R']);
            $hdr[$base + 3] = chr($s['N']);
            $hdr[$base + 4] = chr($s['sr1']);
            $hdr[$base + 5] = chr($s['sr2']);
            // realSize en little-endian 2 octets
            $hdr[$base + 6] = chr($s['realSize'] & 0xFF);
            $hdr[$base + 7] = chr(($s['realSize'] >> 8) & 0xFF);
        }

        // Padding pseudo-aléatoire sur le reste du header piste
        $infoEnd = 0x18 + $spt * 8;
        for ($i = $infoEnd; $i < 256; $i++) {
            $hdr[$i] = chr($this->pseudoRand(0x300 + $track['num'] * 256 + $i));
        }

        // Données secteurs concaténées (weak sectors = données complètes préservées)
        $data = '';
        foreach ($track['sectors'] as $s) {
            $data .= $s['data'];
        }

        $block = $hdr . $data;

        // Padding pour respecter exactement la taille déclarée dans le header disque
        if ($declaredSize > 0 && strlen($block) < $declaredSize) {
            $block = str_pad($block, $declaredSize, "\x00");
        }

        return $block;
    }

    // ----------------------------------------------------------------
    // Utilitaires
    // ----------------------------------------------------------------

    /**
     * Calcule la taille de chaque slot de piste (header 256 + données secteurs).
     * Retourne un tableau indexé par slot, valeur en octets (multiple de 256).
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
                $dataSize += $s['realSize']; // weak sectors : taille réelle complète
            }
            // Header piste (256) + données. Doit être un multiple de 256 exact,
            // buildTrack ajoute le padding nécessaire.
            $sizes[$slot] = 256 + $dataSize;
        }
        return $sizes;
    }

    /**
     * Indexe les pistes par leur numéro de slot (track * nbSides + side).
     */
    private function indexTracks(array $tracks, int $nbSides): array
    {
        $index = [];
        foreach ($tracks as $t) {
            $slot          = $t['num'] * $nbSides + $t['side'];
            $index[$slot]  = $t;
        }
        return $index;
    }

    /**
     * Détermine la valeur N majoritaire parmi les secteurs d'une piste.
     */
    private function majorityN(array $sectors): int
    {
        if (empty($sectors)) return 2; // 512 octets par défaut
        $counts = [];
        foreach ($sectors as $s) {
            $n = $s['N'];
            $counts[$n] = ($counts[$n] ?? 0) + 1;
        }
        arsort($counts);
        return (int)array_key_first($counts);
    }

    /**
     * Écrit une chaîne dans un buffer à un offset donné.
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
     * Génère un octet pseudo-aléatoire déterministe basé sur le seed DskToolPHP.
     * Même seed + même position → même octet : reproductible, mais différent des zéros d'origine.
     */
    private function pseudoRand(int $position): int
    {
        // LCG simple : (seed × position + 0x6C617269) mod 256
        return (int)(((self::PAD_SEED * ($position + 1)) + 0x6C617269) & 0xFF);
    }
}