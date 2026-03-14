<?php
require_once __DIR__ . '/db.php';

function get_setting(string $key): string {
    $db = get_db();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key");
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? $row['value'] : '';
}

function get_all_settings(): array {
    $db = get_db();
    $result = $db->query("SELECT key, value FROM settings");
    $settings = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function get_page(string $slug): ?array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = :slug AND is_published = 1");
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function get_menu_pages(): array {
    $db = get_db();
    $result = $db->query("SELECT id, slug, title, parent_id, menu_order FROM pages WHERE show_in_menu = 1 AND is_published = 1 ORDER BY menu_order ASC");
    $pages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pages[] = $row;
    }
    return $pages;
}

function build_menu_tree(array $pages, ?int $parentId = null): array {
    $tree = [];
    foreach ($pages as $page) {
        $pid = $page['parent_id'] !== null ? (int)$page['parent_id'] : null;
        if ($pid === $parentId) {
            $children = build_menu_tree($pages, (int)$page['id']);
            $page['children'] = $children;
            $tree[] = $page;
        }
    }
    return $tree;
}

function render_menu(array $tree, string $currentSlug = '', bool $isSubmenu = false): string {
    $class = $isSubmenu ? 'submenu' : 'nav-menu';
    $html = '<ul class="' . $class . '">';
    foreach ($tree as $item) {
        $active = ($item['slug'] === $currentSlug) ? ' active' : '';
        $hasChildren = !empty($item['children']);
        $html .= '<li class="nav-item' . ($hasChildren ? ' has-submenu' : '') . '">';
        $html .= '<a href="/' . escape($item['slug']) . '" class="nav-link' . $active . '">' . escape($item['title']) . '</a>';
        if ($hasChildren) {
            $html .= render_menu($item['children'], $currentSlug, true);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

// Brute-Force-Schutz
function check_login_allowed(string $ip): bool {
    $db = get_db();
    // Alte Einträge bereinigen
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-" . LOGIN_LOCKOUT_SECONDS . " seconds')");

    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip");
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    return $stmt->execute()->fetchArray()[0] < MAX_LOGIN_ATTEMPTS;
}

function record_login_attempt(string $ip): void {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->execute();
}

function clear_login_attempts(string $ip): void {
    $db = get_db();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = :ip");
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->execute();
}

function resize_image(string $sourcePath, string $destPath, int $maxWidth = MAX_IMAGE_WIDTH, int $maxHeight = MAX_IMAGE_HEIGHT): bool {
    if (!function_exists('imagecreatefromjpeg')) {
        // GD nicht verfügbar – Datei nur kopieren
        if ($sourcePath !== $destPath) {
            return copy($sourcePath, $destPath);
        }
        return true;
    }

    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $mime = $info['mime'];
    $origWidth = $info[0];
    $origHeight = $info[1];

    if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
        if ($sourcePath !== $destPath) {
            return copy($sourcePath, $destPath);
        }
        return true;
    }

    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = (int)round($origWidth * $ratio);
    $newHeight = (int)round($origHeight * $ratio);

    $source = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($sourcePath),
        'image/png' => imagecreatefrompng($sourcePath),
        'image/gif' => imagecreatefromgif($sourcePath),
        'image/webp' => imagecreatefromwebp($sourcePath),
        default => false,
    };

    if (!$source) return false;

    $dest = imagecreatetruecolor($newWidth, $newHeight);

    if (in_array($mime, ['image/png', 'image/gif'])) {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    $result = match ($mime) {
        'image/jpeg' => imagejpeg($dest, $destPath, 85),
        'image/png' => imagepng($dest, $destPath, 8),
        'image/gif' => imagegif($dest, $destPath),
        'image/webp' => imagewebp($dest, $destPath, 85),
        default => false,
    };

    return $result;
}
