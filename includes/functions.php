<?php
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /admin.php?action=login');
        exit;
    }
}

function getSetting(string $key, string $default = ''): string {
    $db = getDB();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->bindValue(1, $key);
    $result = $stmt->execute()->fetchArray();
    return $result ? $result['value'] : $default;
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    $stmt->bindValue(1, $key);
    $stmt->bindValue(2, $value);
    $stmt->execute();
}

function getPages(bool $publishedOnly = true, bool $menuOnly = false): array {
    $db = getDB();
    $sql = "SELECT * FROM pages";
    $conditions = [];
    if ($publishedOnly) $conditions[] = "is_published = 1";
    if ($menuOnly) $conditions[] = "show_in_menu = 1";
    if ($conditions) $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " ORDER BY menu_order ASC, title ASC";
    $result = $db->query($sql);
    $pages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pages[] = $row;
    }
    return $pages;
}

function getPage(string $slug): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
    $stmt->bindValue(1, $slug);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $result ?: null;
}

function getPageById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->bindValue(1, $id);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $result ?: null;
}

function slugify(string $text): string {
    $text = mb_strtolower($text);
    $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function resizeImage(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): bool {
    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $mime = $info['mime'];
    $origWidth = $info[0];
    $origHeight = $info[1];

    // Only resize if larger than max dimensions
    if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
        if ($sourcePath !== $destPath) {
            copy($sourcePath, $destPath);
        }
        return true;
    }

    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = (int)($origWidth * $ratio);
    $newHeight = (int)($origHeight * $ratio);

    switch ($mime) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$source) return false;

    $dest = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG/GIF
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
    }

    imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($dest, $destPath, 85);
            break;
        case 'image/png':
            $result = imagepng($dest, $destPath);
            break;
        case 'image/gif':
            $result = imagegif($dest, $destPath);
            break;
        case 'image/webp':
            $result = imagewebp($dest, $destPath, 85);
            break;
        default:
            $result = false;
    }

    imagedestroy($source);
    imagedestroy($dest);

    return $result;
}

function uploadImage(array $file): ?array {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        return null;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . strtolower($ext);
    $destPath = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Move and resize
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return null;
    }

    resizeImage($destPath, $destPath, MAX_IMAGE_WIDTH, MAX_IMAGE_HEIGHT);

    // Save to DB
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO media (filename, original_name, mime_type) VALUES (?, ?, ?)");
    $stmt->bindValue(1, $filename);
    $stmt->bindValue(2, $file['name']);
    $stmt->bindValue(3, $file['type']);
    $stmt->execute();

    return [
        'id' => $db->lastInsertRowID(),
        'filename' => $filename,
        'url' => '/uploads/' . $filename,
    ];
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getMenuTree(): array {
    $pages = getPages(true, true);
    $tree = [];
    $children = [];

    foreach ($pages as $page) {
        if ($page['parent_id']) {
            $children[$page['parent_id']][] = $page;
        } else {
            $tree[] = $page;
        }
    }

    foreach ($tree as &$page) {
        $page['children'] = $children[$page['id']] ?? [];
    }

    return $tree;
}
