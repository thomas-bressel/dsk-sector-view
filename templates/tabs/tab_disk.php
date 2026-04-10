<div id="tab-disk" class="tab-panel active">

    <?php
    $trackMap = [];
    foreach ($d['sectors'] as $s) {
        $trackMap[$s['track']][] = $s;
    }
    ksort($trackMap);

    $nbTracks  = max(1, count($trackMap));
    $cx        = 300;
    $cy        = 300;
    $rMin      = 40;
    $rMax      = 270;
    $rStep     = ($rMax - $rMin) / max(1, $nbTracks);
    $svgW      = 600;
    $svgH      = 620;

    $colors = [
        'normal-used'        => ['fill' => '#FFFFFF', 'text' => '#000000'],
        'normal-empty'       => ['fill' => '#3a3a5a', 'text' => '#7a7a9a'],
        'erased-used'        => ['fill' => '#84CFEF', 'text' => '#003366'],
        'erased-empty'       => ['fill' => '#0073DF', 'text' => '#ffffff'],
        'weak'               => ['fill' => '#FF0000', 'text' => '#ffffff'],
        'weak-empty'         => ['fill' => '#A00000', 'text' => '#ffffff'],
        'weak-erased'        => ['fill' => '#FF00FF', 'text' => '#ffffff'],
        'weak-erased-empty'  => ['fill' => '#BA00BA', 'text' => '#ffffff'],
        'incomplete'         => ['fill' => '#e8ffe8', 'text' => '#003300'],
        'protection-n6'      => ['fill' => '#FFB300', 'text' => '#000000'],
        'protection-n6-empty'=> ['fill' => '#7a5500', 'text' => '#FFB300'],
    ];

    $protections = $d['protections'] ?? [];
    $hasN6       = ($d['sizeCounts'][6] ?? 0) > 0;

    if (!function_exists('diskSectorPath')) {
        function diskSectorPath(float $cx, float $cy, float $r1, float $r2, float $aStart, float $aEnd): string {
            $gap    = 1.2;
            $aStart += $gap / 2;
            $aEnd   -= $gap / 2;
            $toRad = M_PI / 180;
            $x1 = $cx + $r1 * cos($aStart * $toRad);
            $y1 = $cy + $r1 * sin($aStart * $toRad);
            $x2 = $cx + $r2 * cos($aStart * $toRad);
            $y2 = $cy + $r2 * sin($aStart * $toRad);
            $x3 = $cx + $r2 * cos($aEnd   * $toRad);
            $y3 = $cy + $r2 * sin($aEnd   * $toRad);
            $x4 = $cx + $r1 * cos($aEnd   * $toRad);
            $y4 = $cy + $r1 * sin($aEnd   * $toRad);
            $large = ($aEnd - $aStart > 180) ? 1 : 0;
            return sprintf(
                'M %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z',
                $x1, $y1, $x2, $y2, $r2, $r2, $large, $x3, $y3,
                $x4, $y4, $r1, $r1, $large, $x1, $y1
            );
        }
    }
    ?>

    <!-- ── Disk Visual Map ────────────────────────────────────────────────── -->
    <div class="disk-visual-card">
        <div class="disk-visual-title"><?= $t['disk_visual_title'] ?></div>
        <div class="disk-visual-wrap">
        <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" xmlns="http://www.w3.org/2000/svg" class="disk-svg">
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $rMax + 10 ?>" fill="#1a1a2e" stroke="#2a2a4a" stroke-width="2"/>
            <?php
            $trackIndex   = 0;
            $globalSector = 0;
            foreach ($trackMap as $trackNum => $sectors):
                $spt    = count($sectors);
                $r1     = $rMin + $trackIndex * $rStep;
                $r2     = $r1 + $rStep - 1;
                $rMid   = ($r1 + $r2) / 2;
                $aDeg   = 360 / max(1, $spt);
                $offset = -90;
                foreach ($sectors as $si => $s):
                    $sectorIdx = $globalSector++;
                    if ($s['N'] === 6) {
                        $cssClass = $s['isUsed'] ? 'protection-n6' : 'protection-n6-empty';
                    } else {
                        $cssClass = FormatHelper::sectorCssClass($s);
                    }
                    $col    = $colors[$cssClass] ?? $colors['normal-empty'];
                    $aStart = $offset + $si * $aDeg;
                    $aEnd   = $aStart + $aDeg;
                    $path   = diskSectorPath($cx, $cy, $r1, $r2, $aStart, $aEnd);
                    $aMid   = ($aStart + $aEnd) / 2 * M_PI / 180;
                    $lx     = $cx + $rMid * cos($aMid);
                    $ly     = $cy + $rMid * sin($aMid);
                    $tooltip = 'T' . str_pad($trackNum, 2, '0', STR_PAD_LEFT)
                             . ' S#' . strtoupper(str_pad(dechex($s['R']), 2, '0', STR_PAD_LEFT))
                             . ' — ' . $s['realSize'] . 'o';
                    if ($s['isUsed'])       $tooltip .= ' [USED]';
                    if ($s['isWeak'])       $tooltip .= ' WEAK';
                    if ($s['isErased'])     $tooltip .= ' ERASED';
                    if ($s['isIncomplete']) $tooltip .= ' INCOMPLETE';
            ?>
                <path d="<?= $path ?>" fill="<?= $col['fill'] ?>" stroke="#0d0d1a" stroke-width="0.5"
                      opacity="<?= $s['isUsed'] ? '1' : '0.45' ?>" class="disk-sector-path"
                      onclick="diskSectorClick(<?= $sectorIdx ?>)">
                    <title><?= htmlspecialchars($tooltip) ?></title>
                </path>
                <?php if ($rStep >= 10 && $spt <= 18): ?>
                <text x="<?= round($lx, 1) ?>" y="<?= round($ly + 4, 1) ?>" text-anchor="middle"
                      font-size="<?= max(5, min(9, (int)($rStep * 0.5))) ?>"
                      fill="<?= $col['text'] ?>" pointer-events="none"
                      opacity="<?= $s['isUsed'] ? '1' : '0.5' ?>">
                    <?= strtoupper(str_pad(dechex($s['R']), 2, '0', STR_PAD_LEFT)) ?>
                </text>
                <?php endif; ?>
            <?php endforeach;
            $trackIndex++;
            endforeach; ?>

            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $rMin - 2 ?>" fill="#0d0d1a" stroke="#2a2a4a" stroke-width="1.5"/>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="6" fill="#333" stroke="#555" stroke-width="1"/>
            <?php if (!empty($protections)): ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $rMin - 4 ?>"
                    fill="none" stroke="<?= $protections[0]['color'] ?>"
                    stroke-width="2" stroke-dasharray="4,3" opacity="0.8"/>
            <text x="<?= $cx ?>" y="<?= $cy - 6 ?>" text-anchor="middle" font-size="10" font-weight="bold"
                  fill="<?= $protections[0]['color'] ?>">🛡</text>
            <text x="<?= $cx ?>" y="<?= $cy + 6 ?>" text-anchor="middle" font-size="5.5" font-weight="bold"
                  fill="<?= $protections[0]['color'] ?>">PROT</text>
            <?php endif; ?>
            <text x="<?= $cx ?>" y="<?= $cy + $rMin - 8 ?>" text-anchor="middle" font-size="9" fill="#4fc3f7">T00</text>
            <text x="<?= $cx ?>" y="<?= $cy - $rMax - 3 ?>" text-anchor="middle" font-size="9" fill="#4fc3f7">T<?= str_pad(array_key_last($trackMap), 2, '0', STR_PAD_LEFT) ?></text>
        </svg>
        <div class="disk-legend">
            <div class="dl-item"><span class="dl-swatch" style="background:#FFFFFF;border:1px solid #555"></span><?= $t['disk_legend_used'] ?></div>
            <div class="dl-item"><span class="dl-swatch" style="background:#3a3a5a;border:1px solid #555"></span><?= $t['disk_legend_empty'] ?></div>
            <div class="dl-item"><span class="dl-swatch" style="background:#84CFEF"></span><?= $t['disk_legend_erased_used'] ?></div>
            <div class="dl-item"><span class="dl-swatch" style="background:#0073DF"></span><?= $t['disk_legend_erased_empty'] ?></div>
            <div class="dl-item"><span class="dl-swatch" style="background:#FF0000"></span><?= $t['disk_legend_weak'] ?></div>
            <div class="dl-item"><span class="dl-swatch" style="background:#FF00FF"></span><?= $t['disk_legend_weak_erased'] ?></div>
            <div class="dl-item"><span class="dl-swatch" style="background:#e8ffe8;border:2px dashed #0a0"></span><?= $t['disk_legend_incomplete'] ?></div>
            <?php if ($hasN6): ?>
            <div class="dl-item"><span class="dl-swatch" style="background:#FFB300"></span><?= $t['disk_legend_prot_n6'] ?></div>
            <?php endif; ?>
            <?php if (!empty($protections)): ?>
            <div class="dl-sep"></div>
            <?php foreach ($protections as $p): ?>
            <div class="dl-protection" style="border-color:<?= $p['color'] ?>;color:<?= $p['color'] ?>">
                <?= $p['icon'] ?> <?= htmlspecialchars($p['label']) ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <!-- ── Spec + Side 1 ─────────────────────────────────────────────────── -->
    <div class="spec-grid">
        <div class="spec-card">
            <span class="card-title"><?= $t['disk_spec_title'] ?></span>
            <table>
                <tr><td><?= $t['disk_spec_dump_size'] ?></td><td><?= number_format($d['fileSize']) ?> (<?= FormatHelper::bytes($d['fileSize']) ?>)</td></tr>
                <tr><td><?= $t['disk_spec_creator'] ?></td><td><?= htmlspecialchars($d['creator']) ?></td></tr>
                <tr><td><?= $t['disk_spec_format'] ?></td><td><?= htmlspecialchars($d['format']) ?></td></tr>
                <tr><td><?= $t['disk_spec_sides'] ?></td><td><?= $d['nbSides'] ?></td></tr>
                <tr><td><?= $t['disk_spec_tracks_fmt'] ?></td><td><?= $d['tracksFormatted'] ?></td></tr>
                <tr><td><?= $t['disk_spec_tracks_side'] ?></td><td><?= $d['nbTracks'] ?></td></tr>
                <?php for ($side = 1; $side <= $d['nbSides']; $side++): ?>
                <tr><td>Side <?= $side ?><?= $t['disk_spec_track_size_decl'] ?></td><td><?= number_format($d['tracksDeclaredSize']) ?> (<?= FormatHelper::bytes($d['tracksDeclaredSize']) ?>)</td></tr>
                <tr><td>Side <?= $side ?><?= $t['disk_spec_track_size_real'] ?></td><td><?= number_format($d['totalRealBytes']) ?> (<?= FormatHelper::bytes($d['totalRealBytes']) ?>)</td></tr>
                <?php $diff = $d['tracksDeclaredSize'] - $d['totalRealBytes']; ?>
                <?php if ($diff !== 0): ?>
                <tr>
                    <td>Side <?= $side ?><?= $t['disk_spec_track_size_diff'] ?></td>
                    <td style="color:var(--orange);font-weight:600"><?= number_format(abs($diff)) ?></td>
                </tr>
                <?php endif; ?>
                <tr><td>Side <?= $side ?><?= $t['disk_spec_all_sect_decl'] ?></td><td><?= number_format($d['totalDeclaredBytes']) ?> (<?= FormatHelper::bytes($d['totalDeclaredBytes']) ?>)</td></tr>
                <tr><td>Side <?= $side ?><?= $t['disk_spec_sum_data'] ?></td><td><?= number_format($d['totalSumData']) ?></td></tr>
                <?php endfor; ?>
            </table>
        </div>
        <div class="spec-card">
            <span class="card-title"><?= sprintf($t['disk_side_sectors_title'], 1) ?></span>
            <table>
                <?php
                $sizeBytes = [128, 256, 512, 1024, 2048, 4096, 8192, 16384, 32768, null];
                for ($n = 0; $n <= 9; $n++):
                    $cnt   = $d['sizeCounts'][$n] ?? 0;
                    $bytes = $sizeBytes[$n];
                    if ($n < 9) {
                        $lbl = 'SectorSize ' . $n . ' (' . number_format($bytes) . ')';
                    } else {
                        $lbl = 'SectorSize &gt; 8';
                    }
                ?>
                <tr>
                    <td><?= $lbl ?></td>
                    <td style="text-align:right;<?= $cnt > 0 ? 'color:var(--accent);font-weight:700' : '' ?>"><?= $cnt ?></td>
                </tr>
                <?php endfor; ?>
                <tr style="border-top:2px solid var(--border)">
                    <td><strong><?= $t['disk_total_sectors'] ?></strong></td>
                    <td style="text-align:right;font-weight:700;color:var(--accent3)"><?= $d['totalSectors'] ?></td>
                </tr>
                <tr><td><?= $t['disk_sector_used'] ?></td><td style="text-align:right;color:var(--green);font-weight:700"><?= $d['usedSectors'] ?></td></tr>
                <tr><td><?= $t['disk_sector_not_used'] ?></td><td style="text-align:right"><?= $d['totalSectors'] - $d['usedSectors'] ?></td></tr>
                <tr><td colspan="2">&nbsp;</td></tr>
                <tr>
                    <td><?= $t['disk_sector_incomplete'] ?></td>
                    <td style="text-align:right;<?= $d['incompleteSectors'] > 0 ? 'color:var(--orange);font-weight:700' : '' ?>"><?= $d['incompleteSectors'] ?></td>
                </tr>
                <tr>
                    <td><?= $t['disk_sector_erased'] ?></td>
                    <td style="text-align:right;<?= $d['erasedSectors'] > 0 ? 'color:#84cfef;font-weight:700;background:rgba(132,207,239,.1)' : '' ?>"><?= $d['erasedSectors'] ?></td>
                </tr>
                <tr>
                    <td><?= $t['disk_weak_sectors'] ?></td>
                    <td style="text-align:right;<?= $d['weakSectors'] > 0 ? 'color:var(--red);font-weight:700' : '' ?>"><?= $d['weakSectors'] ?></td>
                </tr>
                <tr>
                    <td><?= $t['disk_total_weak'] ?></td>
                    <td style="text-align:right;<?= $d['totalWeakSectors'] > 0 ? 'background:rgba(255,68,68,.15);color:var(--red);font-weight:700' : '' ?>"><?= $d['totalWeakSectors'] ?></td>
                </tr>
                <tr><td><?= $t['disk_gaps'] ?></td><td style="text-align:right">0</td></tr>
                <tr><td><?= $t['disk_gap2'] ?></td><td style="text-align:right">0</td></tr>
                <tr>
                    <td><?= $t['disk_fdc_errors'] ?></td>
                    <td style="text-align:right;<?= ($d['fdcErrors'] ?? 0) > 0 ? 'color:var(--orange);font-weight:700' : '' ?>"><?= $d['fdcErrors'] ?? 0 ?></td>
                </tr>
            </table>
        </div>
    </div>

</div>
