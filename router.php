<?php
// Router für den PHP Built-in Development Server
// Verwendung: php -S localhost:8080 router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Statische Dateien direkt ausliefern
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Admin-Bereich
if (str_starts_with($uri, '/admin.php')) {
    require __DIR__ . '/admin.php';
    return true;
}

// Alle anderen Anfragen an index.php mit page-Parameter
$slug = ltrim($uri, '/');
if (!empty($slug)) {
    $_GET['page'] = $slug;
}

require __DIR__ . '/index.php';
return true;
