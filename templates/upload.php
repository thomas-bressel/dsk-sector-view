<div class="upload-card">
    <!-- <div class="icon">📀</div> -->
    <img src="public/assets/img/logo-dsk-tool-php.webp" alt="DSKscan" class="logo-big">
    <h2><?= htmlspecialchars($t['upload_title']) ?></h2>
    <p><?= $t['upload_desc'] ?></p>

    <?php $message = $uploadError; include __DIR__ . '/partials/error_msg.php'; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="drop-zone" id="drop-zone">
            <input type="file" name="dsk_file" id="dsk_file" accept=".dsk">
            <div class="dz-label"><?= $t['upload_dropzone'] ?></div>
            <div class="dz-file-name" id="dz-file-name"></div>
        </div>

        <button type="submit" class="btn">
            <?= htmlspecialchars($t['upload_btn']) ?>
        </button>
    </form>
</div>
