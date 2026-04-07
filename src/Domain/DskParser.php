<?php

class DskParser
{
    /**
     * Parse un fichier Extended DSK et retourne les données brutes structurées.
     *
     * @return array{header: array, tracks: array, rawSectors: array}
     */
    public function parse(string $path): array
    {
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier : $path");
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

            $tracks[]     = $track;
            $rawSectors   = array_merge($rawSectors, $track['sectors']);

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

    // ----------------------------------------------------------------
    // Privé
    // ----------------------------------------------------------------

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

    private function readTrackSizeTable(string $raw, int $nbTracks, int $nbSides): array
    {
        $sizes = [];
        for ($i = 0; $i < $nbTracks * $nbSides; $i++) {
            $sizes[] = ord($raw[0x34 + $i]) * 256;
        }
        return $sizes;
    }

    private function parseTrackHeader(string $hdr): array
    {
        $spt         = ord($hdr[0x15]);
        $sectorInfos = [];

        for ($s = 0; $s < $spt; $s++) {
            $base     = 0x18 + $s * 8;
            $sN       = ord($hdr[$base + 3]);
            $declSize = 128 << $sN;

            // Extended DSK : realSize sur 2 octets little-endian
            // Certains dumpers stockent la valeur en multiple de 256 (high byte seul)
            $lo       = ord($hdr[$base + 6]);
            $hi       = ord($hdr[$base + 7]);
            $realSize = $lo | ($hi << 8);

            // Si realSize est 0 → taille déclarée par N
            if ($realSize === 0) {
                $realSize = $declSize;
            }
            // Détection dumpers qui stockent uniquement le high byte (ex: 0x20 0x00 pour 8192)
            // → $lo serait un multiple de 256 déguisé, $hi = 0, résultat incohérent
            // Si realSize < declSize ET hi == 0 ET lo * 256 == declSize → c'est un high-byte seul
            elseif ($hi === 0 && $lo !== 0 && ($lo * 256) === $declSize) {
                $realSize = $declSize;
            }
            // Sécurité finale : realSize ne peut pas dépasser 2× la taille déclarée
            elseif ($realSize > $declSize * 2) {
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

            // WEAK  : realSize > taille déclarée (données multi-lecture pour simuler l'aléatoire)
            // ERASED: bit 6 de SR2 (deleted data address mark)
            // FDC error : bit 5 de SR1 ou SR2 (CRC error) — stocké séparément
            $isWeak    = ($si['realSize'] > $declSize);
            $isErased  = (bool)($si['sr2'] & 0x40);
            $isFdcErr  = (bool)(($si['sr1'] & 0x20) || ($si['sr2'] & 0x20));
            $isUsed   = $this->isSectorUsed($data, $filler);

            $sectors[] = [
                'track'      => $si['C'],
                'side'       => $si['H'],
                'C'          => $si['C'],
                'H'          => $si['H'],
                'R'          => $si['R'],
                'N'          => $si['N'],
                'declSize'   => $declSize,
                'realSize'   => $si['realSize'],
                'sumData'    => $sumData,
                'sr1'        => $si['sr1'],
                'sr2'        => $si['sr2'],
                'isWeak'     => $isWeak,
                'isErased'   => $isErased,
                'isFdcErr'   => $isFdcErr,
                'isUsed'     => $isUsed,
                'isIncomplete' => ($si['realSize'] !== $declSize),
                'data'       => $data,
            ];
        }

        return $sectors;
    }

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
