<div id="tab-data" class="tab-panel">

<?php
// Ne conserver que les blocs ayant des données binaires affichables
$dataBlocks = array_filter($d['blocks'], fn($b) => $b['dataLen'] > 0 && strlen($b['data']) > 0);
$dataBlocks = array_values($dataBlocks);
?>

<?php if (empty($dataBlocks)): ?>
    <div class="empty-state">
        <div class="es-icon">📭</div>
        <div>Aucun bloc avec données binaires.</div>
    </div>
<?php else: ?>

    <div class="sdata-toolbar">
        <label for="sdata-select" class="sdata-label">Choisir un bloc :</label>
        <select id="sdata-select" onchange="sdataShow(this.value)">
            <?php foreach ($dataBlocks as $i => $block): ?>
                <option value="<?= $i ?>">
                    [<?= str_pad($block['index'], 4, '0', STR_PAD_LEFT) ?>]
                    <?= htmlspecialchars($block['typeName']) ?>
                    <?php
                    $h = $block['cpcHeader'] ?? $block['zxHeader'] ?? null;
                    if ($h) echo ' — ' . htmlspecialchars($h['name']);
                    echo ' (' . number_format($block['dataLen']) . ' o)';
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm" onclick="sdataNav(-1)">&#8249;</button>
        <button class="btn btn-sm" onclick="sdataNav(+1)">&#8250;</button>
    </div>

    <div id="sdata-panels">
    <?php foreach ($dataBlocks as $i => $block):
        $len        = strlen($block['data']);
        $truncated  = ($block['dataLen'] > $len);
        $flags      = [];
        if ($block['cpcHeader'] ?? null) $flags[] = 'HEADER CPC';
        if ($block['zxHeader']  ?? null) $flags[] = 'HEADER ZX';
        $flagStr = $flags ? ' / ' . implode(' + ', $flags) : '';
    ?>
    <div class="sdata-panel <?= $i === 0 ? 'active' : '' ?>" id="sdata-panel-<?= $i ?>">

        <!-- Métadonnées du bloc -->
        <div class="block-meta-bar">
            <div class="bm-cell">
                <span class="bm-label">Type</span>
                <span class="bm-value">
                    <span class="block-type-dot <?= $block['cssClass'] ?>"></span>
                    <?= htmlspecialchars($block['typeName']) ?>
                </span>
            </div>
            <div class="bm-cell">
                <span class="bm-label">Taille déclarée</span>
                <span class="bm-value mono"><?= number_format($block['dataLen']) ?> o</span>
            </div>
            <div class="bm-cell">
                <span class="bm-label">Taille réelle</span>
                <span class="bm-value mono<?= $truncated ? ' orange-val' : '' ?>"><?= number_format($len) ?> o</span>
            </div>
            <div class="bm-cell">
                <span class="bm-label">Sum DATA</span>
                <span class="bm-value mono"><?= number_format($block['sumData'] ?? 0) ?></span>
            </div>
            <div class="bm-cell">
                <span class="bm-label">Durée</span>
                <span class="bm-value mono"><?= number_format($block['durationMs']) ?> ms</span>
            </div>
            <?php if ($flagStr): ?>
            <div class="bm-cell">
                <span class="bm-label">Flags</span>
                <span class="bm-value" style="color:var(--accent)"><?= htmlspecialchars(ltrim($flagStr, ' /')) ?></span>
            </div>
            <?php endif; ?>
            <?php if (isset($block['pilotPulse'])): ?>
            <div class="bm-cell">
                <span class="bm-label">Pilot / Sync1 / Sync2</span>
                <span class="bm-value mono"><?= $block['pilotPulse'] ?> / <?= $block['sync1'] ?> / <?= $block['sync2'] ?> T</span>
            </div>
            <div class="bm-cell">
                <span class="bm-label">Zero / One bit</span>
                <span class="bm-value mono"><?= $block['zeroPulse'] ?> / <?= $block['onePulse'] ?> T</span>
            </div>
            <div class="bm-cell">
                <span class="bm-label">Pilot tone</span>
                <span class="bm-value mono"><?= number_format($block['pilotCount']) ?> pulses</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($truncated): ?>
        <div class="sdata-warning">
            ⚠️ Hex dump limité aux <?= number_format($len) ?> premiers octets
            (bloc de <?= number_format($block['dataLen']) ?> octets).
        </div>
        <?php endif; ?>

        <pre class="hex-dump"><?= FormatHelper::hexDump($block['data']) ?></pre>

    </div>
    <?php endforeach; ?>
    </div>

<?php endif; ?>
</div>