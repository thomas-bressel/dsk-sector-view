<div id="tab-map" class="tab-panel">

    <?php $ms = $d['mapStats']; ?>

    <!-- Barre de stats -->
    <div class="map-stats-bar">
        <div class="map-stats-header"><?= sprintf($t['map_face'], 1) ?></div>
        <div class="map-stats-row">
            <?php
            for ($n = 0; $n <= 9; $n++):
                $cnt = $ms['sizeCounts'][$n] ?? 0;
                $lbl = $n < 9 ? 'Size ' . $n : 'Size >8';
            ?>
            <div class="map-stat-cell <?= $cnt > 0 ? 'has-value' : '' ?>">
                <div class="msc-label"><?= $lbl ?></div>
                <div class="msc-value"><?= $cnt ?></div>
            </div>
            <?php endfor; ?>
            <div class="map-stat-cell erased-cell <?= $ms['erased'] > 0 ? 'has-value' : '' ?>">
                <div class="msc-label"><?= $t['map_stat_erased'] ?></div>
                <div class="msc-value"><?= $ms['erased'] ?></div>
            </div>
            <div class="map-stat-cell weak-cell <?= $ms['weak'] > 0 ? 'has-value' : '' ?>">
                <div class="msc-label"><?= $t['map_stat_weak'] ?></div>
                <div class="msc-value"><?= $ms['weak'] ?></div>
            </div>
            <div class="map-stat-cell incomplete-cell <?= $ms['incomplete'] > 0 ? 'has-value' : '' ?>">
                <div class="msc-label"><?= $t['map_stat_incomplete'] ?></div>
                <div class="msc-value"><?= $ms['incomplete'] ?></div>
            </div>
            <div class="map-stat-cell total-weak-cell <?= $ms['weakTotal'] > 0 ? 'has-value' : '' ?>">
                <div class="msc-label"><?= $t['map_stat_total_weak'] ?></div>
                <div class="msc-value"><?= $ms['weakTotal'] ?></div>
            </div>
            <div class="map-stat-cell gaps-cell">
                <div class="msc-label"><?= $t['map_stat_gaps'] ?></div>
                <div class="msc-value"><?= $ms['gaps'] ?></div>
            </div>
            <div class="map-stat-cell gaps-cell">
                <div class="msc-label"><?= $t['map_stat_gap2'] ?></div>
                <div class="msc-value"><?= $ms['gap2'] ?></div>
            </div>
        </div>
    </div>

    <!-- Légende -->
    <div class="map-legend">
        <span style="font-size:12px;font-weight:700;color:var(--text);margin-right:4px"><?= $t['map_legend_label'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#FFFFFF;border:1px solid #000"></span><?= $t['map_legend_normal_used'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#A0A0A0;border:1px solid #555"></span><?= $t['map_legend_normal_empty'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#84CFEF;border:1px solid #000"></span><?= $t['map_legend_erased_used'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#0073DF;border:1px solid #000"></span><?= $t['map_legend_erased_empty'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#FF0000;border:1px solid #000"></span><?= $t['map_legend_weak_used'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#A00000;border:1px solid #000"></span><?= $t['map_legend_weak_empty'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#FF00FF;border:1px solid #000"></span><?= $t['map_legend_weak_erased_used'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#BA00BA;border:1px solid #000"></span><?= $t['map_legend_weak_erased_empty'] ?></span>
        <span class="legend-item"><span class="legend-swatch" style="background:#fff;border:2px dashed #0a0"></span><?= $t['map_legend_incomplete'] ?></span>
    </div>

    <!-- Carte secteurs -->
    <div class="map-container">
    <table class="map-table">
        <thead>
            <tr>
                <th class="map-th-track"><?= $t['map_col_track'] ?></th>
                <th><?= $t['map_col_sectors'] ?></th>
                <th class="map-th-nb"><?= $t['map_col_nb'] ?></th>
            </tr>
        </thead>
        <tbody>
        <?php
        $byTrack = [];
        foreach ($d['sectors'] as $s) {
            $byTrack[$s['track']][] = $s;
        }
        ksort($byTrack);
        foreach ($byTrack as $tn => $secs):
        ?>
        <tr class="map-row">
            <td class="map-td-track"><?= $tn ?></td>
            <td class="map-td-sectors">
                <div class="map-sectors">
                <?php foreach ($secs as $s):
                    $cls = FormatHelper::sectorCssClass($s);
                    $tip = FormatHelper::sectorTooltip($s);
                ?>
                <div class="sector-block <?= $cls ?> tooltip" data-tip="<?= htmlspecialchars($tip) ?>">
                    <?= strtoupper(dechex($s['R'])) ?>
                </div>
                <?php endforeach; ?>
                </div>
            </td>
            <td class="map-td-nb"><?= count($secs) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
