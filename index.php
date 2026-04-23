<?php
session_start();

// ── Langue ────────────────────────────────────────────────────────────────────
$supportedLangs = ['fr', 'en', 'de', 'es'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLangs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'fr';
$t = require __DIR__ . '/lang/' . $currentLang . '.php';

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Service/CsrfService.php';
require_once __DIR__ . '/src/Service/FileCleanupService.php';
require_once __DIR__ . '/src/Service/UploadService.php';
require_once __DIR__ . '/src/Service/CdtUploadService.php';
require_once __DIR__ . '/src/Domain/DskParser.php';
require_once __DIR__ . '/src/Domain/CpmDirectoryParser.php';
require_once __DIR__ . '/src/Domain/DiskStats.php';
require_once __DIR__ . '/src/Domain/CdtParser.php';
require_once __DIR__ . '/src/Domain/CdtStats.php';
require_once __DIR__ . '/src/Helper/FormatHelper.php';
require_once __DIR__ . '/src/Service/ProtectionDetector.php';
require_once __DIR__ . '/src/Domain/DskWriter.php';
require_once __DIR__ . '/src/Service/DskRepackager.php';

// ── Services transverses ──────────────────────────────────────────────────────
(new FileCleanupService())->run();

$csrf = new CsrfService();
$csrf->init();
$csrfToken = $csrf->getToken();

$repackager = new DskRepackager(new DskParser(), new DskWriter());

// ── Téléchargement du fichier repacké ────────────────────────────────────────
if (isset($_GET['download']) && $_GET['download'] === 'repack'
    && isset($_SESSION['repack_path'], $_SESSION['repack_name'])
) {
    $path = $_SESSION['repack_path'];
    $name = $_SESSION['repack_name'];
    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// ── Gestion de l'upload ───────────────────────────────────────────────────────
$uploadError  = '';
$dskFile      = '';
$cdtFile      = '';
$originalName = '';
$repackPath   = '';
$mode         = '';   // 'dsk' | 'tape' | ''

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$csrf->verify($_POST['csrf_token'])) {
        $uploadError = 'Token de sécurité invalide.';
    } elseif (!empty($_FILES['dsk_file']['name'])) {
        // ── Upload DSK ───────────────────────────────────────────────────────
        $result = (new UploadService())->handle($_FILES['dsk_file']);
        if ($result['success']) {
            $dskFile      = $result['path'];
            $originalName = $result['originalName'];
            $mode         = 'dsk';

            $repackPath = dirname($dskFile) . '/repack_' . basename($dskFile);
            try {
                $repackager->repack($dskFile, $repackPath);
                $repackName = pathinfo($originalName, PATHINFO_FILENAME) . '_repack.dsk';
                $_SESSION['repack_path'] = $repackPath;
                $_SESSION['repack_name'] = $repackName;
            } catch (\RuntimeException $e) {
                $repackPath = '';
                unset($_SESSION['repack_path'], $_SESSION['repack_name']);
            }

            $csrf->renew();
            $csrfToken = $csrf->getToken();
        } else {
            $uploadError = $result['error'];
        }
    } elseif (!empty($_FILES['cdt_file']['name'])) {
        // ── Upload CDT/TZX ───────────────────────────────────────────────────
        $result = (new CdtUploadService())->handle($_FILES['cdt_file']);
        if ($result['success']) {
            $cdtFile      = $result['path'];
            $originalName = $result['originalName'];
            $mode         = 'tape';
            $csrf->renew();
            $csrfToken = $csrf->getToken();
        } else {
            $uploadError = $result['error'];
        }
    }
}

// ── Parsing ───────────────────────────────────────────────────────────────────
$diskData = null;
$tapeData = null;

if ($dskFile && file_exists($dskFile)) {
    try {
        $raw              = (new DskParser())->parse($dskFile);
        $raw['files']     = (new CpmDirectoryParser())->parse($raw['rawSectors']);
        $diskData         = (new DiskStats())->compute($raw);
        $diskData['originalName'] = $originalName;
        $diskData['protections']  = (new ProtectionDetector())->detect($diskData);
        $diskData['repackReady']  = ($repackPath !== '' && file_exists($repackPath));
    } catch (\RuntimeException $e) {
        $uploadError = 'Erreur de lecture : ' . $e->getMessage();
        $diskData    = null;
        $mode        = '';
    }
}

if ($cdtFile && file_exists($cdtFile)) {
    try {
        $raw      = (new CdtParser())->parse($cdtFile);
        $tapeData = (new CdtStats())->compute($raw);
        $tapeData['originalName'] = $originalName;
    } catch (\RuntimeException $e) {
        $uploadError = 'Erreur de lecture : ' . $e->getMessage();
        $tapeData    = null;
        $mode        = '';
    }
}

// ── Rendu ─────────────────────────────────────────────────────────────────────
include __DIR__ . '/templates/layout.php';
