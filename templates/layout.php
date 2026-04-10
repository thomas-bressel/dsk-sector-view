<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($t['app_name']) ?></title>
<link rel="icon" type="image/webp" href="public/assets/img/logo-dsk-tool-php-mini.webp">
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>

<header class="site-header">
    <img src="public/assets/img/logo-dsk-tool-php.webp" alt="DSK Tool PHP" class="logo">
    <h1><?= htmlspecialchars($t['app_name']) ?></h1>
    <span class="sub"><?= htmlspecialchars($t['app_subtitle']) ?></span>

    <nav class="lang-switcher">
        <?php
        $flags = [
            'fr' => ['label' => 'Français', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2"><rect width="1" height="2" fill="#002395"/><rect x="1" width="1" height="2" fill="#fff"/><rect x="2" width="1" height="2" fill="#ED2939"/></svg>'],
            'en' => ['label' => 'English',  'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30"><clipPath id="a"><path d="M0 0v30h60V0z"/></clipPath><clipPath id="b"><path d="M30 15h30v15zv15H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v30h60V0z" fill="#012169"/><path d="M0 0l60 30m0-30L0 30" stroke="#fff" stroke-width="6"/><path d="M0 0l60 30m0-30L0 30" clip-path="url(#b)" stroke="#C8102E" stroke-width="4"/><path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10"/><path d="M30 0v30M0 15h60" stroke="#C8102E" stroke-width="6"/></g></svg>'],
            'de' => ['label' => 'Deutsch',  'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="3" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>'],
            'es' => ['label' => 'Español',  'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2"><rect width="3" height="2" fill="#c60b1e"/><rect width="3" height="1" y=".5" fill="#ffc400"/></svg>'],
        ];
        $base   = strtok($_SERVER['REQUEST_URI'] ?? '?', '?');
        $params = $_GET;
        foreach ($flags as $code => $flag):
            $params['lang'] = $code;
            $url    = $base . '?' . http_build_query($params);
            $active = ($currentLang === $code) ? ' lang-active' : '';
        ?>
        <a href="<?= htmlspecialchars($url) ?>" class="lang-btn<?= $active ?>" title="<?= $flag['label'] ?>" aria-label="<?= $flag['label'] ?>">
            <?= $flag['svg'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
</header>

<div class="container">

<?php if (!$diskData): ?>

    <?php include __DIR__ . '/upload.php'; ?>

<?php else:
    $d = $diskData;
?>

    <?php include __DIR__ . '/disk_banner.php'; ?>

    <div class="tabs-wrapper">
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="showTab('disk', this)"><?= $t['tab_disk'] ?></button>
            <button class="tab-btn" onclick="showTab('files', this)"><?= $t['tab_files'] ?></button>
            <button class="tab-btn" onclick="showTab('map', this)"><?= $t['tab_map'] ?></button>
            <button class="tab-btn" onclick="showTab('sectors', this)"><?= $t['tab_sectors'] ?></button>
            <button class="tab-btn" onclick="showTab('tracks', this)"><?= $t['tab_tracks'] ?></button>
            <button class="tab-btn" onclick="showTab('infos', this)"><?= $t['tab_infos'] ?></button>
            <button class="tab-btn" onclick="showTab('data', this)"><?= $t['tab_data'] ?></button>
        </div>

        <?php include __DIR__ . '/tabs/tab_disk.php'; ?>
        <?php include __DIR__ . '/tabs/tab_files.php'; ?>
        <?php include __DIR__ . '/tabs/tab_map.php'; ?>
        <?php include __DIR__ . '/tabs/tab_sectors.php'; ?>
        <?php include __DIR__ . '/tabs/tab_tracks.php'; ?>
        <?php include __DIR__ . '/tabs/tab_infos.php'; ?>
        <?php include __DIR__ . '/tabs/tab_data.php'; ?>

    </div><!-- /tabs-wrapper -->

<?php endif; ?>

</div><!-- /container -->

<footer class="site-footer">
    DSK Tool PHP &nbsp;·&nbsp; v<?= APP_VERSION ?> &nbsp;·&nbsp; <?= APP_DATE ?>
</footer>

<script src="public/assets/app.js"></script>
</body>
</html>
