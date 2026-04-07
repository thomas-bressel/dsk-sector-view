<?php
session_start();

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Service/CsrfService.php';
require_once __DIR__ . '/src/Service/FileCleanupService.php';
require_once __DIR__ . '/src/Service/UploadService.php';
require_once __DIR__ . '/src/Domain/DskParser.php';
require_once __DIR__ . '/src/Domain/CpmDirectoryParser.php';
require_once __DIR__ . '/src/Domain/DiskStats.php';
require_once __DIR__ . '/src/Helper/FormatHelper.php';
require_once __DIR__ . '/src/Service/ProtectionDetector.php';

// ── Services transverses ──────────────────────────────────────────────────────
(new FileCleanupService())->run();

$csrf = new CsrfService();
$csrf->init();
$csrfToken = $csrf->getToken();

// ── Gestion de l'upload ───────────────────────────────────────────────────────
$uploadError  = '';
$dskFile      = '';
$originalName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$csrf->verify($_POST['csrf_token'])) {
        $uploadError = 'Token de sécurité invalide.';
    } else {
        $result = (new UploadService())->handle($_FILES['dsk_file'] ?? []);

        if ($result['success']) {
            $dskFile      = $result['path'];
            $originalName = $result['originalName'];
            $csrf->renew();
            $csrfToken = $csrf->getToken();
        } else {
            $uploadError = $result['error'];
        }
    }
}

// ── Parsing DSK ───────────────────────────────────────────────────────────────
$diskData = null;

if ($dskFile && file_exists($dskFile)) {
    try {
        $raw              = (new DskParser())->parse($dskFile);
        $raw['files']     = (new CpmDirectoryParser())->parse($raw['rawSectors']);
        $diskData         = (new DiskStats())->compute($raw);
        $diskData['originalName'] = $originalName;
        $diskData['protections']  = (new ProtectionDetector())->detect($diskData);
    } catch (\RuntimeException $e) {
        $uploadError = 'Erreur de lecture : ' . $e->getMessage();
        $diskData    = null;
    }
}

// ── Rendu ─────────────────────────────────────────────────────────────────────
include __DIR__ . '/templates/layout.php';
