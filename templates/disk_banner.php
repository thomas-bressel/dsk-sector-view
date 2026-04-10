<div class="disk-banner">
    <img src="public/assets/img/logo-dsk-tool-php-mini.webp" alt="DSK Tool PHP" class="disk-icon">
    <div>
        <div class="disk-title"><?= htmlspecialchars($originalName) ?></div>
        <div class="disk-meta">
            <?= htmlspecialchars($d['format']) ?> &nbsp;·&nbsp;
            <?= $t['banner_creator'] ?> <?= htmlspecialchars($d['creator']) ?> &nbsp;·&nbsp;
            <?= FormatHelper::bytes($d['fileSize']) ?>
        </div>
    </div>
    <div class="disk-stats">
        <div class="stat-badge"><div class="val"><?= $d['nbTracks'] ?></div><div class="lbl"><?= $t['banner_tracks'] ?></div></div>
        <div class="stat-badge"><div class="val"><?= $d['nbSides'] ?></div><div class="lbl"><?= $t['banner_sides'] ?></div></div>
        <div class="stat-badge"><div class="val"><?= $d['totalSectors'] ?></div><div class="lbl"><?= $t['banner_sectors'] ?></div></div>
        <div class="stat-badge"><div class="val" style="color:var(--green)"><?= $d['usedSectors'] ?></div><div class="lbl"><?= $t['banner_used'] ?></div></div>
        <?php if ($d['weakSectors'] > 0): ?>
        <div class="stat-badge"><div class="val" style="color:var(--red)"><?= $d['weakSectors'] ?></div><div class="lbl"><?= $t['banner_weak'] ?></div></div>
        <?php endif; ?>
    </div>
    <div class="new-upload-btn">
        <?php if (!empty($d['repackReady'])): ?>
        <a href="?download=repack" class="btn btn-sm"><?= $t['btn_download_repack'] ?></a>
        <?php endif; ?>
        <a href="?" class="btn btn-sm"><?= $t['btn_new_file'] ?></a>
    </div>
</div>
