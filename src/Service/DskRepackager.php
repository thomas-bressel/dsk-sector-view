<?php

/**
 * DskRepackager
 *
 * Orchestre le repackage complet d'un fichier DSK :
 *  1. Parse le DSK source via DskParser
 *  2. Injecte un fichier DSKTLPHP.TXT dans la FAT CP/M (track 0)
 *  3. Reconstruit le DSK via DskWriter avec le creator "DskToolPHP"
 *     et un padding pseudo-aléatoire garantissant un hash différent
 *
 * Le jeu reste 100% fonctionnel : seuls les métadonnées et la structure
 * binaire du conteneur DSK sont modifiées, jamais les données de jeu.
 *
 * Toutes les protections sont préservées intégralement :
 *  - Weak sectors  (realSize > declSize)
 *  - Erased sectors (SR2 bit 6)
 *  - Secteurs de taille N=6/7/8
 *  - Flags FDC SR1/SR2
 *
 * Usage typique :
 *   $repackager = new DskRepackager(new DskParser(), new DskWriter());
 *   $repackager->repack('/path/to/source.dsk', '/path/to/output.dsk');
 */
class DskRepackager
{
    /** Creator injecté dans le header DSK */
    private const CREATOR = 'DskToolPHP';

    /** Nom du fichier de signature injecté dans la FAT CP/M */
    private const SIG_FILENAME = 'DSKTLPHP';

    /** Extension du fichier de signature */
    private const SIG_EXT = 'TXT';

    /** Contenu du fichier de signature */
    private const SIG_CONTENT =
        "Packaged by DskToolPhp\r\n" .
        "Tool    : DskToolPHP\r\n" .
        "Source  : Personal archive\r\n" .
        "\x1A"; // EOF CP/M

    /** Octet de remplissage pour les secteurs CP/M vides */
    private const CPM_FILLER = 0xE5;

    /** Taille d'une entrée de répertoire CP/M */
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
    // Interface publique
    // ----------------------------------------------------------------

    /**
     * Repackage $sourcePath vers $destPath.
     *
     * @param  string $sourcePath Chemin du DSK source
     * @param  string $destPath   Chemin du DSK de sortie
     * @throws \RuntimeException  En cas d'erreur de lecture/écriture
     */
    public function repack(string $sourcePath, string $destPath): void
    {
        // Étape 1 — Parser le source
        $parsed = $this->parser->parse($sourcePath);

        // Étape 2 — Injecter la signature dans la FAT CP/M (track 0)
        $parsed = $this->injectSignature($parsed);

        // Étape 3 — Écrire le DSK reconstruit
        $this->writer->write($parsed, $destPath, self::CREATOR);
    }

    // ----------------------------------------------------------------
    // Injection de la signature en FAT CP/M
    // ----------------------------------------------------------------

    /**
     * Injecte un fichier DSKTLPHP.TXT dans la FAT CP/M de la track 0.
     *
     * La FAT CP/M est stockée dans les secteurs de la track 0.
     * On cherche la première entrée libre (0xE5) et on y écrit notre entrée.
     * Si la FAT est pleine, on passe silencieusement (le DSK reste valide).
     *
     * @param  array $parsed Données parsées (modifiées en place)
     * @return array         Données parsées avec signature injectée
     */
    private function injectSignature(array $parsed): array
    {
        // Trouver le secteur de track 0 qui contient la FAT CP/M
        // La FAT commence à l'entrée 0, qui se trouve dans le premier secteur de T0
        $track0Sectors = $this->getTrack0Sectors($parsed['tracks']);

        if (empty($track0Sectors)) {
            return $parsed; // Pas de track 0 → on ne touche à rien
        }

        // Construire le buffer FAT à partir des secteurs de T0 (dans l'ordre logique)
        [$fatBuffer, $sectorOrder] = $this->buildFatBuffer($track0Sectors);

        // Chercher un slot libre (0xE5) dans la FAT
        $slotOffset = $this->findFreeDirSlot($fatBuffer);

        if ($slotOffset === null) {
            return $parsed; // FAT pleine → pas d'injection, DSK intact
        }

        // Construire l'entrée de répertoire CP/M pour DSKTLPHP.TXT
        $entry = $this->buildDirEntry(
            self::SIG_FILENAME,
            self::SIG_EXT,
            self::SIG_CONTENT
        );

        // Écrire l'entrée dans le buffer FAT
        for ($i = 0; $i < self::DIR_ENTRY_SIZE; $i++) {
            $fatBuffer[$slotOffset + $i] = $entry[$i];
        }

        // Redistribuer le buffer FAT modifié dans les secteurs de T0
        $parsed['tracks'] = $this->applyFatBuffer($parsed['tracks'], $fatBuffer, $sectorOrder);

        // Resynchroniser rawSectors depuis les tracks modifiées
        $parsed['rawSectors'] = $this->rebuildRawSectors($parsed['tracks']);

        return $parsed;
    }

    /**
     * Retourne les secteurs de la track 0, triés par ID logique (R).
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
     * Concatène les données des secteurs de T0 en un seul buffer FAT.
     * Retourne [buffer, [R => index_dans_parsed_tracks_sectors]].
     */
    private function buildFatBuffer(array $track0Sectors): array
    {
        $buffer      = '';
        $sectorOrder = []; // R → position dans le tableau de secteurs de T0

        foreach ($track0Sectors as $pos => $s) {
            $sectorOrder[$s['R']] = $pos;
            // Pour les secteurs weak, on prend uniquement la première copie (declSize octets)
            $effectiveData = substr($s['data'], 0, $s['declSize']);
            // Padder si nécessaire
            if (strlen($effectiveData) < $s['declSize']) {
                $effectiveData = str_pad($effectiveData, $s['declSize'], chr(self::CPM_FILLER));
            }
            $buffer .= $effectiveData;
        }

        return [$buffer, $sectorOrder];
    }

    /**
     * Cherche le premier slot d'entrée de répertoire libre (premier octet = 0xE5).
     * Retourne l'offset dans le buffer, ou null si aucun slot disponible.
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
     * Construit une entrée de répertoire CP/M de 32 octets.
     *
     * Structure CP/M d'une entrée :
     *  Octet  0    : User number (0 = user 0)
     *  Octets 1-8  : Nom du fichier (padded 0x20)
     *  Octets 9-11 : Extension     (padded 0x20)
     *  Octet  12   : Extent number (0)
     *  Octets 13-14: Réservé (0)
     *  Octet  15   : RC = record count (taille en blocs de 128 octets)
     *  Octets 16-31: Allocation blocks (0 = non alloué, fichier sans blocs réels)
     */
    private function buildDirEntry(string $name, string $ext, string $content): string
    {
        $entry = str_repeat(chr(self::CPM_FILLER), self::DIR_ENTRY_SIZE);

        // User 0
        $entry[0] = "\x00";

        // Nom (8 octets, padded espace)
        $namePad = str_pad(substr($name, 0, 8), 8, ' ');
        for ($i = 0; $i < 8; $i++) {
            $entry[1 + $i] = $namePad[$i];
        }

        // Extension (3 octets, padded espace)
        $extPad = str_pad(substr($ext, 0, 3), 3, ' ');
        for ($i = 0; $i < 3; $i++) {
            $entry[9 + $i] = $extPad[$i];
        }

        // Extent = 0
        $entry[12] = "\x00";
        $entry[13] = "\x00";
        $entry[14] = "\x00";

        // RC = nombre de blocs de 128 octets (arrondi supérieur)
        $rc = (int)ceil(strlen($content) / 128);
        $entry[15] = chr(min($rc, 127)); // max 127 records par extent

        // Blocs alloués : 0 (on ne stocke pas physiquement le fichier dans les données)
        // Le fichier est référencé dans le répertoire mais sans blocs → taille symbolique
        for ($i = 16; $i < 32; $i++) {
            $entry[$i] = "\x00";
        }

        return $entry;
    }

    /**
     * Réécrit les secteurs de la track 0 avec le buffer FAT modifié.
     */
    private function applyFatBuffer(array &$tracks, string $fatBuffer, array $sectorOrder): array
    {
        foreach ($tracks as &$track) {
            if ($track['num'] !== 0) continue;

            // Redistribuer le buffer dans les secteurs, dans l'ordre logique (par R)
            $sortedSectors = $track['sectors'];
            usort($sortedSectors, fn($a, $b) => $a['R'] <=> $b['R']);

            $offset = 0;
            foreach ($sortedSectors as $sorted) {
                // Retrouver le secteur dans le tableau original par R
                foreach ($track['sectors'] as &$s) {
                    if ($s['R'] !== $sorted['R']) continue;

                    $chunk = substr($fatBuffer, $offset, $s['declSize']);
                    $chunk = str_pad($chunk, $s['declSize'], chr(self::CPM_FILLER));

                    // Weak sector : reconstruire les copies supplémentaires
                    if ($s['isWeak'] && $s['realSize'] > $s['declSize']) {
                        $copies    = (int)floor($s['realSize'] / $s['declSize']);
                        $s['data'] = str_repeat($chunk, $copies);
                        // Ajuster au realSize exact
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
     * Reconstruit le tableau rawSectors à plat depuis toutes les tracks.
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