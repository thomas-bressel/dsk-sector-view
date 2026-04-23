<div id="tab-blocks" class="tab-panel active">

    <!-- ══ Résumé par type ══════════════════════════════════════════════════ -->
    <div class="spec-card" style="margin-bottom:24px">
        <span class="card-title">📋 Récapitulatif des blocs</span>
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type de bloc</th>
                    <th class="center">Nb</th>
                    <th class="right">Durée bloc (ms)</th>
                    <th class="right">Pause après (ms)</th>
                    <th class="right">Total (ms)</th>
                    <th class="right">Durée</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($d['typeSummary'] as $row): ?>
            <tr class="<?= $row['cssClass'] ?>-row">
                <td>
                    <span class="block-type-dot <?= $row['cssClass'] ?>"></span>
                    <?= htmlspecialchars($row['typeName']) ?>
                </td>
                <td class="center mono"><?= $row['count'] ?></td>
                <td class="right mono"><?= number_format($row['durationMs']) ?></td>
                <td class="right mono"><?= number_format($row['pauseMs']) ?></td>
                <td class="right mono"><?= number_format($row['totalMs']) ?></td>
                <td class="right mono"><?= FormatHelper::msToTime($row['totalMs']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td class="center mono"><strong><?= $d['blockCount'] ?></strong></td>
                    <td class="right mono"><strong><?= number_format($d['totalDurationMs']) ?></strong></td>
                    <td class="right mono"><strong><?= number_format($d['totalPauseMs']) ?></strong></td>
                    <td class="right mono"><strong><?= number_format($d['totalMs']) ?></strong></td>
                    <td class="right mono"><strong><?= FormatHelper::msToTime($d['totalMs']) ?></strong></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>

    <!-- ══ CheckData ═══════════════════════════════════════════════════════ -->
    <div class="spec-card" style="margin-bottom:24px">
        <span class="card-title">🔢 Check Data</span>
        <div class="checkdata-summary">
            <div class="cd-total">
                <span class="cd-label">Somme de toutes les données</span>
                <span class="cd-value"><?= number_format($d['totalSumData']) ?></span>
            </div>
        </div>

        <?php if (!empty($d['checkDataRows'])): ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="center">Bloc</th>
                    <th>Type</th>
                    <th class="right">Sum DATA</th>
                    <th class="center">Bits utilisés</th>
                    <th class="center">Dernier octet</th>
                    <th class="right">Données (octets)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($d['checkDataRows'] as $row): ?>
            <tr>
                <td class="center mono">
                    <a href="#" onclick="jumpToBlock(<?= $row['index'] ?>); return false;"
                       class="block-link"><?= str_pad($row['index'], 4, '0', STR_PAD_LEFT) ?></a>
                </td>
                <td>
                    <span class="block-type-dot <?= $row['cssClass'] ?>"></span>
                    <?= htmlspecialchars($row['typeName']) ?>
                </td>
                <td class="right mono<?= $row['sumData'] > 0 ? ' accent-val' : '' ?>">
                    <?= number_format($row['sumData']) ?>
                </td>
                <td class="center mono"><?= $row['usedBits'] ?></td>
                <td class="center mono"><?= strtoupper(str_pad(dechex($row['lastByte']), 2, '0', STR_PAD_LEFT)) ?></td>
                <td class="right mono"><?= number_format($row['dataLen']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ Détail de chaque bloc ════════════════════════════════════════════ -->
    <div class="spec-card">
        <span class="card-title">🔎 Détail des blocs</span>
        <div class="table-scroll">
        <table class="data-table blocks-detail-table">
            <thead>
                <tr>
                    <th class="center">Index</th>
                    <th>Type</th>
                    <th class="right">Durée (ms)</th>
                    <th class="right">Pause (ms)</th>
                    <th class="right">Données</th>
                    <th class="right">Sum DATA</th>
                    <th>Infos</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($d['blocks'] as $block): ?>
            <tr id="block-row-<?= $block['index'] ?>">
                <td class="center mono"><?= str_pad($block['index'], 4, '0', STR_PAD_LEFT) ?></td>
                <td>
                    <span class="block-type-dot <?= $block['cssClass'] ?>"></span>
                    <?= htmlspecialchars($block['typeName']) ?>
                </td>
                <td class="right mono"><?= number_format($block['durationMs']) ?></td>
                <td class="right mono"><?= number_format($block['pause'] ?? 0) ?></td>
                <td class="right mono"><?= $block['dataLen'] > 0 ? number_format($block['dataLen']) : '—' ?></td>
                <td class="right mono"><?= ($block['sumData'] ?? 0) > 0 ? number_format($block['sumData']) : '—' ?></td>
                <td class="block-info-cell">
                    <?php
                    // Informations contextuelles selon le type
                    switch ($block['type']) {
                        case 0x11:
                        case 0x10:
                            $h = $block['cpcHeader'] ?? $block['zxHeader'] ?? null;
                            if ($h) {
                                echo '<span class="info-badge header-badge">';
                                echo '📁 ' . htmlspecialchars($h['name']);
                                echo ' · ' . htmlspecialchars($h['fileTypeName']);
                                echo '</span>';
                            } else {
                                echo '<span class="info-badge data-badge">DATA</span>';
                            }
                            break;
                        case 0x30:
                            echo '<span class="info-muted">' . htmlspecialchars(substr($block['description'] ?? '', 0, 40)) . '</span>';
                            break;
                        case 0x12:
                            echo '<span class="info-muted">' . number_format($block['numPulses'] ?? 0) . ' pulses · ' . ($block['pulseLen'] ?? 0) . ' T</span>';
                            break;
                        case 0x20:
                            echo '<span class="info-muted">Pause ' . number_format($block['pause'] ?? 0) . ' ms</span>';
                            break;
                        case 0x21:
                            echo '<span class="info-muted">' . htmlspecialchars($block['groupName'] ?? '') . '</span>';
                            break;
                        default:
                            break;
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if (!empty($d['descriptions'])): ?>
    <!-- ══ Descriptions texte ══════════════════════════════════════════════ -->
    <div class="spec-card" style="margin-top:24px">
        <span class="card-title">📝 Descriptions texte</span>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="center">Bloc</th>
                    <th>Texte</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($d['descriptions'] as $desc): ?>
            <tr class="type48-row">
                <td class="center mono"><?= str_pad($desc['index'], 4, '0', STR_PAD_LEFT) ?></td>
                <td class="mono"><?= htmlspecialchars($desc['text']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>