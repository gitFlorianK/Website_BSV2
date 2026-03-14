<?php
// Pfade
define('ROOT_PATH', __DIR__);
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('DB_FILE', DATA_PATH . '/site.db');

// Upload-Einstellungen
define('MAX_IMAGE_WIDTH', 1920);
define('MAX_IMAGE_HEIGHT', 1080);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Brute-Force-Schutz
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300); // 5 Minuten

// Session sicher starten (muss VOR session_start aufgerufen werden)
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', (int)(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600);

    session_start();
}

// Security-Headers für alle Antworten
function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// CSRF-Token generieren (pro Formular ein eigenes Token)
function csrf_token(): string {
    secure_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        // Token nach Verwendung rotieren
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

// HTML-Sanitizer: entfernt gefährliche Attribute und Protokolle
function sanitize_html(string $html): string {
    // Erlaubte Tags
    $html = strip_tags($html, '<h1><h2><h3><h4><h5><h6><p><br><a><img><ul><ol><li><strong><em><u><s><blockquote><pre><code><table><thead><tbody><tr><th><td><div><span><hr><figure><figcaption>');

    // Alle Event-Handler entfernen (on*)
    $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $html);

    // javascript: und data: URIs in href/src entfernen
    $html = preg_replace('/(<a[^>]*\s+href\s*=\s*["\'])\s*(javascript|data):[^"\']*(["\'])/i', '$1#$3', $html);
    $html = preg_replace('/(<img[^>]*\s+src\s*=\s*["\'])\s*(javascript|data):[^"\']*(["\'])/i', '$1#$3', $html);

    // style-Attribute mit expression() entfernen
    $html = preg_replace('/\s+style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/i', '', $html);

    return $html;
}

// Sicherheitsfunktionen
function escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validate_color(string $value): bool {
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $value);
}

function redirect(string $url): never {
    // Nur interne Redirects erlauben
    if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
        $url = '/';
    }
    header('Location: ' . $url);
    exit;
}
