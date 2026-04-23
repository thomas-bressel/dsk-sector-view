<?php $message = $uploadError; include __DIR__ . '/partials/error_msg.php'; ?>

<div class="home-chooser">

    <!-- ══ Carte DSK ══════════════════════════════════════════════════════════ -->
    <div class="chooser-card" id="card-dsk">
        <div class="chooser-icon">💽</div>
        <h2 class="chooser-title"><?= htmlspecialchars($t['upload_title']) ?></h2>
        <p class="chooser-desc"><?= $t['upload_desc'] ?></p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="drop-zone" id="drop-zone-dsk">
                <input type="file" name="dsk_file" id="dsk_file" accept=".dsk">
                <div class="dz-label"><?= $t['upload_dropzone'] ?></div>
                <div class="dz-file-name" id="dz-file-name-dsk"></div>
            </div>

            <button type="submit" class="btn btn-dsk">
                <?= htmlspecialchars($t['upload_btn']) ?>
            </button>
        </form>

        <div class="format-badges">
            <span class="fmt-badge dsk">💽 .DSK — Amstrad CPC</span>
        </div>
    </div>

    <!-- ══ Séparateur ════════════════════════════════════════════════════════ -->
    <div class="chooser-sep"><span>ou</span></div>

    <!-- ══ Carte CDT/TZX ════════════════════════════════════════════════════ -->
    <div class="chooser-card" id="card-cdt">
        <div class="chooser-icon">📼</div>
        <h2 class="chooser-title"><?= htmlspecialchars($t['upload_tape_title']) ?></h2>
        <p class="chooser-desc"><?= $t['upload_tape_desc'] ?></p>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="drop-zone" id="drop-zone-cdt">
                <input type="file" name="cdt_file" id="cdt_file" accept=".cdt,.tzx">
                <div class="dz-label"><?= $t['upload_tape_dropzone'] ?></div>
                <div class="dz-file-name" id="dz-file-name-cdt"></div>
            </div>

            <button type="submit" class="btn btn-cdt">
                <?= htmlspecialchars($t['upload_tape_btn']) ?>
            </button>
        </form>

        <div class="format-badges">
            <span class="fmt-badge cdt">📼 .CDT — Amstrad CPC</span>
            <span class="fmt-badge tzx">💾 .TZX — ZX Spectrum</span>
        </div>
    </div>

</div>
