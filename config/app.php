<?php

define('APP_VERSION',           '1.0.2');
define('APP_DATE',              '2026-04-23');
define('UPLOAD_DIR',            __DIR__ . '/../files/');
define('MAX_FILE_SIZE',         5 * 1024 * 1024);
define('CSRF_TOKEN_NAME',       'dsk_csrf_token');
define('FILE_MAX_AGE_SECONDS',  3600);
define('DSK_VALID_SIGNATURES',  ['EXTENDED CPC DSK', 'MV - CPCEMU']);
define('CDT_VALID_SIGNATURE',   "ZXTape!\x1a");
define('CDT_VALID_EXTENSIONS',  ['cdt', 'tzx']);
define('CDT_MAX_FILE_SIZE',     10 * 1024 * 1024);
