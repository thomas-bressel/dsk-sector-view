<?php

/**
 * FileCleanupService
 *
 * Deletes uploaded .dsk files from the upload directory once they have exceeded
 * the maximum allowed age defined by the FILE_MAX_AGE_SECONDS constant.
 *
 * This service should be called at the beginning of each request to ensure
 * that stale uploads are removed before any new file is processed.
 *
 * @package DskToolPhp\Service
 */
class FileCleanupService
{
    /**
     * Scans the upload directory and removes any .dsk file whose last modification
     * time is older than FILE_MAX_AGE_SECONDS seconds ago.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (glob(UPLOAD_DIR . '*.dsk') as $file) {
            if (filemtime($file) < time() - FILE_MAX_AGE_SECONDS) {
                @unlink($file);
            }
        }
    }
}
