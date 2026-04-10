<?php

/**
 * UploadService
 *
 * Validates and stores a .dsk file submitted via an HTML file upload form.
 *
 * Validation steps (in order):
 *   1. PHP upload error check (UPLOAD_ERR_OK required)
 *   2. File extension must be ".dsk" (case-insensitive)
 *   3. File size must not exceed MAX_FILE_SIZE bytes
 *   4. Binary signature must match a known DSK format (DSK_VALID_SIGNATURES)
 *
 * On success, the file is moved from the PHP temporary directory to UPLOAD_DIR
 * under a cryptographically random name to prevent filename collisions and
 * path traversal attacks.
 *
 * @package DskToolPhp\Service
 */
class UploadService
{
    /**
     * Processes a file from the $_FILES superglobal array.
     *
     * @param  array $fileInput  Entry from $_FILES (e.g. $_FILES['dsk_file'])
     * @return array{
     *   success: bool,
     *   path: string,
     *   originalName: string,
     *   error: string
     * }
     */
    public function handle(array $fileInput): array
    {
        $result = ['success' => false, 'path' => '', 'originalName' => '', 'error' => ''];

        if (!isset($fileInput['error']) || $fileInput['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Upload error.';
            return $result;
        }

        $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        if ($ext !== 'dsk') {
            $result['error'] = 'Only .dsk files are accepted.';
            return $result;
        }

        if ($fileInput['size'] > MAX_FILE_SIZE) {
            $result['error'] = 'File too large (max ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB).';
            return $result;
        }

        if (!$this->hasValidSignature($fileInput['tmp_name'])) {
            $result['error'] = 'Invalid file: DSK signature not recognised.';
            return $result;
        }

        $newName = bin2hex(random_bytes(8)) . '.dsk';
        $dest    = UPLOAD_DIR . $newName;

        if (!move_uploaded_file($fileInput['tmp_name'], $dest)) {
            $result['error'] = 'Could not save the file.';
            return $result;
        }

        $result['success']      = true;
        $result['path']         = $dest;
        $result['originalName'] = $fileInput['name'];
        return $result;
    }

    /**
     * Checks that the first 16 bytes of the file match one of the known DSK signatures.
     *
     * @param  string $tmpPath Path to the PHP temporary file
     * @return bool            True if the binary signature is recognised
     */
    private function hasValidSignature(string $tmpPath): bool
    {
        $fp  = fopen($tmpPath, 'rb');
        $sig = fread($fp, 16);
        fclose($fp);

        foreach (DSK_VALID_SIGNATURES as $validSig) {
            if (strpos($sig, $validSig) === 0) {
                return true;
            }
        }
        return false;
    }
}
