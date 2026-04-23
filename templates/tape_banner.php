<div class="tape-banner">
    <span class="tape-icon">📼</span>
    <div>
        <div class="tape-title"><?= htmlspecialchars($d['originalName']) ?></div>
        <div class="tape-meta">
            <?= htmlspecialchars($d['format']) ?> &nbsp;·&nbsp;
            ZXTape! v<?= $d['header']['majorVersion'] ?>.<?= str_pad($d['header']['minorVersion'], 2, '0', STR_PAD_LEFT) ?>
            &nbsp;·&nbsp; <?= FormatHelper::bytes($d['fileSize']) ?>
        </div>
    </div>

    <div class="tape-stats">
        <div class="stat-badge">
            <div class="val"><?= $d['blockCount'] ?></div>
            <div class="lbl">Blocs</div>
        </div>
        <div class="stat-badge">
            <div class="val" style="color:var(--green)"><?= count($d['catalogue']) ?></div>
            <div class="lbl">Fichier(s)</div>
        </div>
        <div class="stat-badge">
            <div class="val" style="color:var(--accent3)"><?= FormatHelper::msToTime($d['totalMs']) ?></div>
            <div class="lbl">Durée totale</div>
        </div>
        <div class="stat-badge">
            <div class="val" style="color:var(--accent)"><?= number_format($d['totalSumData']) ?></div>
            <div class="lbl">Sum DATA</div>
        </div>
    </div>

    <div class="new-upload-btn">
        <a href="?" class="btn btn-sm">⬆ Nouveau fichier</a>
    </div>
</div>