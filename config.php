<?php
session_start();

define('SITE_NAME', 'BSV 1960 Plauen e.V. - Bogensport');
define('SITE_ROOT', __DIR__);
define('UPLOAD_DIR', SITE_ROOT . '/uploads/');
define('MAX_IMAGE_WIDTH', 1920);
define('MAX_IMAGE_HEIGHT', 1080);
define('DB_PATH', SITE_ROOT . '/data/cms.db');

// Ensure data directory exists
if (!is_dir(SITE_ROOT . '/data')) {
    mkdir(SITE_ROOT . '/data', 0755, true);
}

require_once SITE_ROOT . '/includes/db.php';
require_once SITE_ROOT . '/includes/functions.php';
