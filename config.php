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

// Passwort-Mindestlänge
define('MIN_PASSWORD_LENGTH', 8);

// Session sicher starten (muss VOR session_start aufgerufen werden)
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', (int)(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 3600);

    session_start();

    // Session-Fixation-Schutz: ID bei erster Nutzung regenerieren
    if (empty($_SESSION['_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['_initialized'] = true;
    }
}

// Security-Headers für alle Antworten
function send_security_headers(bool $isAdmin = false): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // HSTS wenn HTTPS aktiv
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content-Security-Policy
    if ($isAdmin) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: blob:; font-src 'self' https://cdn.jsdelivr.net;");
    } else {
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
    }
}

// CSRF-Token
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
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

// Verify CSRF ohne Rotation (für AJAX-Requests die das Token nicht erneuern können)
function verify_csrf_stateless(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// DOM-basierter HTML-Sanitizer
function sanitize_html(string $html): string {
    if (empty(trim($html))) return '';

    // Erlaubte Tags und ihre erlaubten Attribute
    $allowedTags = [
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'p' => [], 'br' => [], 'hr' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'strong' => [], 'em' => [], 'u' => [], 's' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'div' => [], 'span' => [],
        'figure' => [], 'figcaption' => [],
    ];

    // Erlaubte URL-Protokolle
    $allowedProtocols = ['http', 'https', 'mailto', '/'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    // HTML laden mit Fehlerunterdrückung (Quill erzeugt nicht immer valides HTML)
    @$doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);

    $xpath = new DOMXPath($doc);

    // Alle Elemente durchgehen
    $allElements = $xpath->query('//*');
    $toRemove = [];

    foreach ($allElements as $element) {
        $tagName = strtolower($element->nodeName);

        // body und html-Wrapper ignorieren
        if (in_array($tagName, ['body', 'html', '#document'])) continue;

        // Tag nicht erlaubt -> Inhalt behalten, Tag entfernen
        if (!isset($allowedTags[$tagName])) {
            $toRemove[] = $element;
            continue;
        }

        // Nicht erlaubte Attribute entfernen
        $allowedAttrs = $allowedTags[$tagName];
        $attrsToRemove = [];
        foreach ($element->attributes as $attr) {
            $attrName = strtolower($attr->nodeName);

            // Event-Handler immer entfernen
            if (str_starts_with($attrName, 'on')) {
                $attrsToRemove[] = $attr->nodeName;
                continue;
            }

            // style-Attribut immer entfernen
            if ($attrName === 'style') {
                $attrsToRemove[] = $attr->nodeName;
                continue;
            }

            // Nicht in Whitelist
            if (!in_array($attrName, $allowedAttrs)) {
                $attrsToRemove[] = $attr->nodeName;
                continue;
            }

            // URL-Attribute prüfen (href, src)
            if (in_array($attrName, ['href', 'src'])) {
                $value = trim($attr->nodeValue);
                // HTML-Entities dekodieren für echte Prüfung
                $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Whitespace und Steuerzeichen entfernen
                $decoded = preg_replace('/[\x00-\x1f\x7f]/u', '', $decoded);
                $decoded = trim($decoded);

                $safe = false;
                foreach ($allowedProtocols as $proto) {
                    if ($proto === '/') {
                        if (str_starts_with($decoded, '/') && !str_starts_with($decoded, '//')) {
                            $safe = true;
                            break;
                        }
                    } elseif (str_starts_with(strtolower($decoded), $proto . ':')) {
                        $safe = true;
                        break;
                    }
                }

                if (!$safe) {
                    $attrsToRemove[] = $attr->nodeName;
                }
            }
        }

        foreach ($attrsToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }

        // rel="noopener" für externe Links erzwingen
        if ($tagName === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    // Nicht erlaubte Tags entfernen (Inhalt behalten)
    foreach ($toRemove as $element) {
        $fragment = $doc->createDocumentFragment();
        while ($element->firstChild) {
            $fragment->appendChild($element->firstChild);
        }
        if ($element->parentNode) {
            $element->parentNode->replaceChild($fragment, $element);
        }
    }

    // Nur den body-Inhalt zurückgeben
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return '';

    $output = '';
    foreach ($body->childNodes as $child) {
        $output .= $doc->saveHTML($child);
    }

    return $output;
}

// Sicherheitsfunktionen
function escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validate_color(string $value): bool {
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $value);
}

function redirect(string $url): never {
    if (!str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\')) {
        $url = '/';
    }
    header('Location: ' . $url);
    exit;
}
