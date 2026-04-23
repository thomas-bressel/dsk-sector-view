<?php

class CdtUploadService
{
    public function handle(array $fileInput): array
    {
        $result = ['success' => false, 'path' => '', 'originalName' => '', 'error' => ''];

        if (!isset($fileInput['error']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Erreur lors du transfert du fichier.';
            return $result;
        }

        $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, CDT_VALID_EXTENSIONS, true)) {
            $result['error'] = 'Seuls les fichiers .cdt et .tzx sont acceptés.';
            return $result;
        }

        if ($fileInput['size'] > CDT_MAX_FILE_SIZE) {
            $result['error'] = 'Fichier trop grand (max ' . (CDT_MAX_FILE_SIZE / 1024 / 1024) . ' Mo).';
            return $result;
        }

        if (!$this->hasValidSignature($fileInput['tmp_name'])) {
            $result['error'] = 'Fichier invalide : signature ZXTape! non trouvée.';
            return $result;
        }

        $newName = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest    = UPLOAD_DIR . $newName;

        if (!move_uploaded_file($fileInput['tmp_name'], $dest)) {
            $result['error'] = 'Impossible de sauvegarder le fichier.';
            return $result;
        }

        $result['success']      = true;
        $result['path']         = $dest;
        $result['originalName'] = $fileInput['name'];
        return $result;
    }

    private function hasValidSignature(string $tmpPath): bool
    {
        $fp  = fopen($tmpPath, 'rb');
        if (!$fp) return false;
        $sig = fread($fp, 8);
        fclose($fp);
        return $sig === CDT_VALID_SIGNATURE;
    }
}
