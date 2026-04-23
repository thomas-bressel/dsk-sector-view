<?php

/**
 * CdtParser
 *
 * Analyse binaire des fichiers .cdt (Amstrad CPC) et .tzx (ZX Spectrum).
 * Les deux formats partagent le même conteneur TZX ("ZXTape!\x1a").
 *
 * Référence : https://worldofspectrum.org/TZXformat.html
 */
class CdtParser
{
    /** T-states/seconde pour l'Amstrad CPC (4 MHz) */
    const CLOCK_CPC = 4_000_000;

    /** T-states/seconde pour le ZX Spectrum (3,5 MHz) */
    const CLOCK_ZX  = 3_500_000;

    /** Noms lisibles des types de blocs TZX */
    const BLOCK_NAMES = [
        0x10 => 'STANDARD LOADING DATA',
        0x11 => 'TURBO LOADING DATA',
        0x12 => 'PURE TONE',
        0x13 => 'SEQUENCE OF PULSES',
        0x14 => 'PURE DATA',
        0x15 => 'DIRECT RECORDING',
        0x18 => 'CSW RECORDING',
        0x19 => 'GENERALIZED DATA',
        0x20 => 'PAUSE',
        0x21 => 'GROUP START',
        0x22 => 'GROUP END',
        0x23 => 'JUMP TO BLOCK',
        0x24 => 'LOOP START',
        0x25 => 'LOOP END',
        0x28 => 'SELECT BLOCK',
        0x2A => 'STOP IF 48K',
        0x2B => 'SET SIGNAL LEVEL',
        0x30 => 'TEXT DESCRIPTION',
        0x31 => 'MESSAGE',
        0x32 => 'ARCHIVE INFO',
        0x33 => 'HARDWARE TYPE',
        0x35 => 'CUSTOM INFO',
        0x5A => 'GLUE BLOCK',
    ];

    /** Classes CSS pour les couleurs de blocs */
    const BLOCK_CSS = [
        0x10 => 'type16',
        0x11 => 'type17',
        0x12 => 'type18',
        0x13 => 'type19',
        0x14 => 'type20',
        0x15 => 'type21',
        0x18 => 'type24',
        0x19 => 'type25',
        0x20 => 'type32',
        0x21 => 'type33',
        0x22 => 'type34',
        0x30 => 'type48',
        0x31 => 'type49',
        0x32 => 'type50',
        0x33 => 'type51',
        0x35 => 'type43',
    ];

    // ── Entrée principale ────────────────────────────────────────────────────

    /**
     * Parse un fichier CDT ou TZX et retourne toutes les données structurées.
     *
     * @throws \RuntimeException si le fichier est invalide
     */
    public function parse(string $path): array
    {
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier.");
        }

        try {
            $header = $this->parseHeader($h);
            $ext    = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $clock  = ($ext === 'cdt') ? self::CLOCK_CPC : self::CLOCK_ZX;
            $blocks = $this->parseBlocks($h, $clock);
        } finally {
            fclose($h);
        }

        return [
            'path'     => $path,
            'fileSize' => filesize($path),
            'ext'      => $ext,
            'clock'    => $clock,
            'format'   => ($ext === 'cdt') ? 'Amstrad CPC CDT' : 'ZX Spectrum TZX',
            'header'   => $header,
            'blocks'   => $blocks,
        ];
    }

    // ── Header ───────────────────────────────────────────────────────────────

    private function parseHeader($h): array
    {
        $raw = fread($h, 10);
        if (strlen($raw) < 10 || substr($raw, 0, 8) !== "ZXTape!\x1a") {
            throw new \RuntimeException("Signature CDT/TZX invalide.");
        }

        return [
            'creator'      => "ZXTape!",
            'majorVersion' => ord($raw[8]),
            'minorVersion' => ord($raw[9]),
        ];
    }

    // ── Dispatch des blocs ───────────────────────────────────────────────────

    private function parseBlocks($h, int $clock): array
    {
        $blocks = [];
        $index  = 0;

        while (!feof($h)) {
            $tb = fread($h, 1);
            if ($tb === false || $tb === '') break;

            $type  = ord($tb);
            $block = $this->parseBlock($h, $type, $clock, $index);
            if ($block !== null) {
                $blocks[] = $block;
                $index++;
            }
        }

        return $blocks;
    }

    private function parseBlock($h, int $type, int $clock, int $index): ?array
    {
        $base = [
            'index'      => $index,
            'type'       => $type,
            'typeName'   => self::BLOCK_NAMES[$type] ?? sprintf('UNKNOWN (0x%02X)', $type),
            'cssClass'   => self::BLOCK_CSS[$type] ?? 'type43',
            'pause'      => 0,
            'durationMs' => 0,
            'totalMs'    => 0,
            'dataLen'    => 0,
            'data'       => '',
            'sumData'    => 0,
            'usedBits'   => 8,
            'lastByte'   => 0xFF,
            'cpcHeader'  => null,
            'zxHeader'   => null,
        ];

        switch ($type) {
            case 0x10: return $this->parseBlock10($h, $clock, $base);
            case 0x11: return $this->parseBlock11($h, $clock, $base);
            case 0x12: return $this->parseBlock12($h, $clock, $base);
            case 0x13: return $this->parseBlock13($h, $clock, $base);
            case 0x14: return $this->parseBlock14($h, $clock, $base);
            case 0x15: return $this->parseBlock15($h, $clock, $base);
            case 0x18: return $this->parseBlock18($h, $clock, $base);
            case 0x19: return $this->parseBlock19($h, $clock, $base);
            case 0x20: return $this->parseBlock20($h, $base);
            case 0x21: return $this->parseBlock21($h, $base);
            case 0x22: return $base; // Group End — pas de données
            case 0x23: fread($h, 2); return $base;
            case 0x24: fread($h, 2); return $base;
            case 0x25: return $base;
            case 0x2A: fread($h, 2); return $base;
            case 0x2B: fread($h, 1); return $base;
            case 0x30: return $this->parseBlock30($h, $base);
            case 0x31: return $this->parseBlock31($h, $base);
            case 0x32: return $this->parseBlock32($h, $base);
            case 0x33: return $this->parseBlock33($h, $base);
            case 0x35: return $this->parseBlock35($h, $base);
            case 0x5A: fread($h, 9); return $base;
            default:   return $this->parseGenericBlock($h, $type, $base);
        }
    }

    // ── Blocs de données ─────────────────────────────────────────────────────

    /** 0x10 — Standard Speed Data */
    private function parseBlock10($h, int $clock, array $b): array
    {
        $raw     = fread($h, 4);
        $pause   = $this->u16($raw, 0);
        $dataLen = $this->u16($raw, 2);
        $data    = ($dataLen > 0) ? fread($h, $dataLen) : '';

        // Durée approchée basée sur les timings standards ZX
        $pilotPulses = (strlen($data) > 0 && ord($data[0]) === 0x00) ? 8063 : 3223;
        $totalT      = 2168 * $pilotPulses + 667 + 735 + $dataLen * 8 * 2 * 1282;
        $durationMs  = (int)($totalT * 1000 / $clock);

        return array_merge($b, [
            'pause'      => $pause,
            'durationMs' => $durationMs,
            'totalMs'    => $durationMs + $pause,
            'dataLen'    => $dataLen,
            'data'       => $data,
            'sumData'    => $this->sumBytes($data),
            'lastByte'   => (strlen($data) > 0) ? ord($data[strlen($data) - 1]) : 0,
            'cpcHeader'  => $this->parseCpcHeader($data),
            'zxHeader'   => $this->parseZxHeader($data),
        ]);
    }

    /** 0x11 — Turbo Speed Data */
    private function parseBlock11($h, int $clock, array $b): array
    {
        $raw        = fread($h, 18);
        $pilotPulse = $this->u16($raw, 0);
        $sync1      = $this->u16($raw, 2);
        $sync2      = $this->u16($raw, 4);
        $zeroPulse  = $this->u16($raw, 6);
        $onePulse   = $this->u16($raw, 8);
        $pilotCount = $this->u16($raw, 10);
        $usedBits   = ord($raw[12]);
        $pause      = $this->u16($raw, 13);
        $dataLen    = $this->u24($raw, 15);
        $data       = ($dataLen > 0) ? fread($h, $dataLen) : '';

        // Calcul précis (approximation 50/50 bits 0 et 1)
        $totalBits  = $dataLen * 8 - (8 - $usedBits);
        $avgBitT    = ($zeroPulse + $onePulse); // 2 pulses par bit, moyenne des 2
        $totalT     = $pilotPulse * $pilotCount + $sync1 + $sync2 + $totalBits * $avgBitT;
        $durationMs = (int)($totalT * 1000 / $clock);

        return array_merge($b, [
            'pilotPulse' => $pilotPulse,
            'sync1'      => $sync1,
            'sync2'      => $sync2,
            'zeroPulse'  => $zeroPulse,
            'onePulse'   => $onePulse,
            'pilotCount' => $pilotCount,
            'usedBits'   => $usedBits,
            'pause'      => $pause,
            'durationMs' => $durationMs,
            'totalMs'    => $durationMs + $pause,
            'dataLen'    => $dataLen,
            'data'       => $data,
            'sumData'    => $this->sumBytes($data),
            'lastByte'   => (strlen($data) > 0) ? ord($data[strlen($data) - 1]) : 0,
            'cpcHeader'  => $this->parseCpcHeader($data),
            'zxHeader'   => $this->parseZxHeader($data),
        ]);
    }

    /** 0x12 — Pure Tone */
    private function parseBlock12($h, int $clock, array $b): array
    {
        $raw        = fread($h, 4);
        $pulseLen   = $this->u16($raw, 0);
        $numPulses  = $this->u16($raw, 2);
        $durationMs = (int)($pulseLen * $numPulses * 1000 / $clock);

        return array_merge($b, [
            'pulseLen'   => $pulseLen,
            'numPulses'  => $numPulses,
            'durationMs' => $durationMs,
            'totalMs'    => $durationMs,
        ]);
    }

    /** 0x13 — Sequence of Pulses */
    private function parseBlock13($h, int $clock, array $b): array
    {
        $n      = ord(fread($h, 1));
        $raw    = fread($h, $n * 2);
        $totalT = 0;
        $pulses = [];
        for ($i = 0; $i < $n; $i++) {
            $p = $this->u16($raw, $i * 2);
            $pulses[] = $p;
            $totalT  += $p;
        }
        $durationMs = (int)($totalT * 1000 / $clock);

        return array_merge($b, [
            'pulses'     => $pulses,
            'durationMs' => $durationMs,
            'totalMs'    => $durationMs,
        ]);
    }

    /** 0x14 — Pure Data */
    private function parseBlock14($h, int $clock, array $b): array
    {
        $raw       = fread($h, 10);
        $zeroPulse = $this->u16($raw, 0);
        $onePulse  = $this->u16($raw, 2);
        $usedBits  = ord($raw[4]);
        $pause     = $this->u16($raw, 5);
        $dataLen   = $this->u24($raw, 7);
        $data      = ($dataLen > 0) ? fread($h, $dataLen) : '';

        $totalBits  = $dataLen * 8 - (8 - $usedBits);
        $totalT     = $totalBits * ($zeroPulse + $onePulse);
        $durationMs = (int)($totalT * 1000 / $clock);

        return array_merge($b, [
            'zeroPulse'  => $zeroPulse,
            'onePulse'   => $onePulse,
            'usedBits'   => $usedBits,
            'pause'      => $pause,
            'durationMs' => $durationMs,
            'totalMs'    => $durationMs + $pause,
            'dataLen'    => $dataLen,
            'data'       => $data,
            'sumData'    => $this->sumBytes($data),
            'lastByte'   => (strlen($data) > 0) ? ord($data[strlen($data) - 1]) : 0,
        ]);
    }

    /** 0x15 — Direct Recording */
    private function parseBlock15($h, int $clock, array $b): array
    {
        $raw        = fread($h, 8);
        $tPerSample = $this->u16($raw, 0);
        $pause      = $this->u16($raw, 2);
        $usedBits   = ord($raw[4]);
        $dataLen    = $this->u24($raw, 5);
        $data       = ($dataLen > 0) ? fread($h, $dataLen) : '';

        $samples    = $dataLen * 8 - (8 - $usedBits);
        $durationMs = ($tPerSample > 0) ? (int)($samples * $tPerSample * 1000 / $clock) : 0;

        return array_merge($b, [
            'tPerSample' => $tPerSample,
            'usedBits'   => $usedBits,
            'pause'      => $pause,
            'durationMs' => $durationMs,
            'totalMs'    => $durationMs + $pause,
            'dataLen'    => $dataLen,
            'data'       => substr($data, 0, 512),
            'sumData'    => $this->sumBytes($data),
        ]);
    }

    /** 0x18 — CSW Recording */
    private function parseBlock18($h, int $clock, array $b): array
    {
        $lenRaw   = fread($h, 4);
        $blockLen = $this->u32($lenRaw, 0);
        $body     = fread($h, $blockLen);

        $pause      = $this->u16($body, 0);
        $sampleRate = $this->u24($body, 2);
        $durationMs = 0; // calculer depuis le contenu CSW est très complexe

        return array_merge($b, [
            'pause'      => $pause,
            'sampleRate' => $sampleRate,
            'durationMs' => $durationMs,
            'totalMs'    => $pause,
            'dataLen'    => $blockLen,
            'data'       => substr($body, 10, 256),
            'sumData'    => $this->sumBytes($body),
        ]);
    }

    /** 0x19 — Generalized Data Block */
    private function parseBlock19($h, int $clock, array $b): array
    {
        $lenRaw   = fread($h, 4);
        $blockLen = $this->u32($lenRaw, 0);
        $body     = fread($h, $blockLen);
        $pause    = strlen($body) >= 2 ? $this->u16($body, 0) : 0;

        return array_merge($b, [
            'pause'      => $pause,
            'durationMs' => 0,
            'totalMs'    => $pause,
            'dataLen'    => $blockLen,
            'data'       => substr($body, 0, 256),
            'sumData'    => $this->sumBytes($body),
        ]);
    }

    /** 0x20 — Pause / Stop the Tape */
    private function parseBlock20($h, array $b): array
    {
        $raw   = fread($h, 2);
        $pause = $this->u16($raw, 0);

        return array_merge($b, [
            'pause'      => $pause,
            'durationMs' => $pause,
            'totalMs'    => $pause,
        ]);
    }

    /** 0x21 — Group Start */
    private function parseBlock21($h, array $b): array
    {
        $len  = ord(fread($h, 1));
        $name = ($len > 0) ? fread($h, $len) : '';

        return array_merge($b, ['groupName' => $name]);
    }

    /** 0x30 — Text Description */
    private function parseBlock30($h, array $b): array
    {
        $len  = ord(fread($h, 1));
        $text = ($len > 0) ? fread($h, $len) : '';

        return array_merge($b, [
            'description' => $text,
            'dataLen'     => $len,
            'data'        => $text,
        ]);
    }

    /** 0x31 — Message Block */
    private function parseBlock31($h, array $b): array
    {
        $time = ord(fread($h, 1));
        $len  = ord(fread($h, 1));
        $msg  = ($len > 0) ? fread($h, $len) : '';

        return array_merge($b, [
            'displayTime' => $time,
            'message'     => $msg,
            'dataLen'     => $len,
            'data'        => $msg,
        ]);
    }

    /** 0x32 — Archive Info */
    private function parseBlock32($h, array $b): array
    {
        $raw        = fread($h, 3);
        $totalLen   = $this->u16($raw, 0);
        $numStrings = ord($raw[2]);

        $strings = [];
        $read    = 0;
        for ($i = 0; $i < $numStrings && $read < ($totalLen - 1); $i++) {
            $tByte = fread($h, 1);
            $lByte = fread($h, 1);
            if ($tByte === false || $lByte === false) break;
            $strType = ord($tByte);
            $strLen  = ord($lByte);
            $text    = ($strLen > 0) ? fread($h, $strLen) : '';
            $strings[] = ['type' => $strType, 'text' => $text];
            $read += 2 + $strLen;
        }

        $archiveTypes = [
            0x00 => 'Full title',
            0x01 => 'Software house/Publisher',
            0x02 => 'Author(s)',
            0x03 => 'Year of publication',
            0x04 => 'Language',
            0x05 => 'Game/utility type',
            0x06 => 'Price',
            0x07 => 'Protection scheme/loader',
            0x08 => 'Origin',
            0xFF => 'Comment(s)',
        ];

        foreach ($strings as &$s) {
            $s['typeName'] = $archiveTypes[$s['type']] ?? 'Info ' . $s['type'];
        }

        return array_merge($b, ['archiveStrings' => $strings]);
    }

    /** 0x33 — Hardware Type */
    private function parseBlock33($h, array $b): array
    {
        $n   = ord(fread($h, 1));
        $raw = fread($h, $n * 3);

        $machines = [];
        for ($i = 0; $i < $n; $i++) {
            if (strlen($raw) < ($i + 1) * 3) break;
            $machines[] = [
                'hwType' => ord($raw[$i * 3]),
                'hwId'   => ord($raw[$i * 3 + 1]),
                'hwInfo' => ord($raw[$i * 3 + 2]),
            ];
        }

        return array_merge($b, ['machines' => $machines]);
    }

    /** 0x35 — Custom Info Block */
    private function parseBlock35($h, array $b): array
    {
        $ident   = rtrim(fread($h, 16), "\0");
        $lenRaw  = fread($h, 4);
        $dataLen = $this->u32($lenRaw, 0);
        $data    = ($dataLen > 0) ? fread($h, $dataLen) : '';

        return array_merge($b, [
            'ident'   => $ident,
            'dataLen' => $dataLen,
            'data'    => substr($data, 0, 256),
            'sumData' => $this->sumBytes($data),
        ]);
    }

    /** Blocs inconnus : lecture sûre via longueur 4 octets */
    private function parseGenericBlock($h, int $type, array $b): array
    {
        $lenRaw = fread($h, 4);
        if ($lenRaw === false || strlen($lenRaw) < 4) return [];

        $len = $this->u32($lenRaw, 0);
        if ($len > 0 && $len < 50_000_000) {
            fread($h, $len);
        }

        return $b;
    }

    // ── Détection des en-têtes ────────────────────────────────────────────────

    /**
     * Détecte et décode un en-tête cassette Amstrad CPC.
     * Marker : premier octet = 0x2C
     * Structure : sync(1) + nom(16) + blockNum(1) + lastBlock(1) + type(1) +
     *             dataLen(2) + loadAddr(2) + firstBlock(1) + logLen(2) + execAddr(2)
     */
    private function parseCpcHeader(string $data): ?array
    {
        if (strlen($data) < 29 || ord($data[0]) !== 0x2C) {
            return null;
        }

        $name       = rtrim(substr($data, 1, 16), "\0 ");
        $blockNum   = ord($data[17]);
        $lastBlock  = (ord($data[18]) === 0xFF);
        $fileType   = ord($data[19]);
        $dataLen    = $this->u16($data, 20);
        $loadAddr   = $this->u16($data, 22);
        $firstBlock = (ord($data[24]) === 0xFF);
        $logLen     = $this->u16($data, 25);
        $execAddr   = $this->u16($data, 27);

        $typeNames = [
            0 => 'BASIC',
            1 => 'BASIC protégé',
            2 => 'Binaire',
            3 => 'Binaire protégé',
            4 => 'Screen dump',
            8 => 'Source ASM',
        ];

        return [
            'isCpc'        => true,
            'name'         => $name,
            'blockNum'     => $blockNum,
            'lastBlock'    => $lastBlock,
            'firstBlock'   => $firstBlock,
            'fileType'     => $fileType,
            'fileTypeName' => $typeNames[$fileType] ?? 'Type ' . $fileType,
            'dataLen'      => $dataLen,
            'loadAddr'     => $loadAddr,
            'execAddr'     => $execAddr,
            'logLen'       => $logLen,
        ];
    }

    /**
     * Détecte et décode un en-tête cassette ZX Spectrum standard.
     * Marker : premier octet = 0x00 (flag header)
     */
    private function parseZxHeader(string $data): ?array
    {
        if (strlen($data) < 19 || ord($data[0]) !== 0x00) {
            return null;
        }

        $fileType = ord($data[1]);
        $name     = rtrim(substr($data, 2, 10));
        $length   = $this->u16($data, 12);
        $param1   = $this->u16($data, 14);
        $param2   = $this->u16($data, 16);

        $typeNames = [
            0 => 'Program',
            1 => 'Number Array',
            2 => 'Character Array',
            3 => 'Byte Block',
        ];

        return [
            'isCpc'        => false,
            'name'         => $name,
            'fileType'     => $fileType,
            'fileTypeName' => $typeNames[$fileType] ?? 'Unknown',
            'length'       => $length,
            'param1'       => $param1,
            'param2'       => $param2,
        ];
    }

    // ── Helpers binaires ─────────────────────────────────────────────────────

    /** Somme de tous les octets */
    private function sumBytes(string $data): int
    {
        $sum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $sum += ord($data[$i]);
        }
        return $sum;
    }

    /** 16 bits little-endian */
    private function u16(string $d, int $o): int
    {
        if (strlen($d) < $o + 2) return 0;
        return ord($d[$o]) | (ord($d[$o + 1]) << 8);
    }

    /** 24 bits little-endian */
    private function u24(string $d, int $o): int
    {
        if (strlen($d) < $o + 3) return 0;
        return ord($d[$o]) | (ord($d[$o + 1]) << 8) | (ord($d[$o + 2]) << 16);
    }

    /** 32 bits little-endian */
    private function u32(string $d, int $o): int
    {
        if (strlen($d) < $o + 4) return 0;
        return ord($d[$o]) | (ord($d[$o + 1]) << 8) | (ord($d[$o + 2]) << 16) | (ord($d[$o + 3]) << 24);
    }
}