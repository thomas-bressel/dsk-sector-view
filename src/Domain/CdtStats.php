<?php

/**
 * CdtStats
 *
 * Calcule les métriques agrégées à partir des blocs bruts du CdtParser.
 * Fournit tout ce dont les vues ont besoin sans logique d'affichage.
 */
class CdtStats
{
    public function compute(array $raw): array
    {
        $blocks = $raw['blocks'];

        // ── Récapitulatif par type ────────────────────────────────────────────
        $typeSummary     = [];
        $totalDurationMs = 0;
        $totalPauseMs    = 0;
        $totalSumData    = 0;
        $totalDataBlocks = 0;

        foreach ($blocks as $block) {
            $type = $block['type'];

            if (!isset($typeSummary[$type])) {
                $typeSummary[$type] = [
                    'type'       => $type,
                    'typeName'   => $block['typeName'],
                    'cssClass'   => $block['cssClass'],
                    'count'      => 0,
                    'durationMs' => 0,
                    'pauseMs'    => 0,
                    'totalMs'    => 0,
                ];
            }

            $typeSummary[$type]['count']++;
            $typeSummary[$type]['durationMs'] += $block['durationMs'];
            $typeSummary[$type]['pauseMs']    += $block['pause'] ?? 0;
            $typeSummary[$type]['totalMs']    += $block['totalMs'];

            $totalDurationMs += $block['durationMs'];
            $totalPauseMs    += $block['pause'] ?? 0;
            $totalSumData    += $block['sumData'] ?? 0;

            if ($block['dataLen'] > 0) $totalDataBlocks++;
        }

        // ── CheckData ──────────────────────────────────────────────────────────
        // Comptage par taille (1 octet / 3 octets / autre) pour affichage
        $checkDataRows = [];
        foreach ($blocks as $block) {
            if (($block['sumData'] ?? 0) === 0 && $block['dataLen'] === 0) continue;
            $checkDataRows[] = [
                'index'    => $block['index'],
                'typeName' => $block['typeName'],
                'cssClass' => $block['cssClass'],
                'sumData'  => $block['sumData'] ?? 0,
                'usedBits' => $block['usedBits'] ?? 8,
                'lastByte' => $block['lastByte'] ?? 0,
                'dataLen'  => $block['dataLen'],
            ];
        }

        // ── Catalogue des fichiers ────────────────────────────────────────────
        $catalogue = $this->buildCatalogue($blocks);

        // ── Descriptions texte ────────────────────────────────────────────────
        $descriptions = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 0x30) {
                $descriptions[] = [
                    'index' => $block['index'],
                    'text'  => $block['description'] ?? '',
                ];
            }
            if ($block['type'] === 0x31) {
                $descriptions[] = [
                    'index' => $block['index'],
                    'text'  => $block['message'] ?? '',
                ];
            }
        }

        return [
            // Identité
            'path'            => $raw['path'],
            'fileSize'        => $raw['fileSize'],
            'format'          => $raw['format'],
            'ext'             => $raw['ext'],
            'clock'           => $raw['clock'],
            'header'          => $raw['header'],

            // Blocs bruts
            'blocks'          => $blocks,
            'blockCount'      => count($blocks),

            // Statistiques
            'typeSummary'     => array_values($typeSummary),
            'totalDurationMs' => $totalDurationMs,
            'totalPauseMs'    => $totalPauseMs,
            'totalMs'         => $totalDurationMs + $totalPauseMs,
            'totalSumData'    => $totalSumData,
            'checkDataRows'   => $checkDataRows,

            // Contenu
            'catalogue'       => $catalogue,
            'descriptions'    => $descriptions,
        ];
    }

    // ── Privé ──────────────────────────────────────────────────────────────────

    /**
     * Construit le catalogue en fusionnant les en-têtes CPC/ZX détectés.
     * Un fichier = une entrée HEADER + (optionnellement) le bloc DATA suivant.
     */
    private function buildCatalogue(array $blocks): array
    {
        $catalogue = [];

        foreach ($blocks as $i => $block) {
            $header = $block['cpcHeader'] ?? $block['zxHeader'] ?? null;
            if ($header === null) continue;

            // Cherche le bloc DATA suivant (non-header)
            $dataBlock = null;
            for ($j = $i + 1; $j < count($blocks); $j++) {
                $next = $blocks[$j];
                if ($next['dataLen'] > 0
                    && ($next['cpcHeader'] === null)
                    && ($next['zxHeader'] === null)) {
                    $dataBlock = $next;
                    break;
                }
                // Si on tombe sur un autre header, on arrête
                if ($next['cpcHeader'] !== null || $next['zxHeader'] !== null) {
                    break;
                }
            }

            $catalogue[] = [
                'headerBlockIndex' => $block['index'],
                'dataBlockIndex'   => $dataBlock ? $dataBlock['index'] : null,
                'header'           => $header,
                'headerSumData'    => $block['sumData'] ?? 0,
                'dataSumData'      => $dataBlock ? ($dataBlock['sumData'] ?? 0) : 0,
                'dataLen'          => $dataBlock ? $dataBlock['dataLen'] : 0,
            ];
        }

        return $catalogue;
    }
}