<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

$action = $_GET['action'] ?? 'dashboard';

// --- Logout ---
if ($action === 'logout') {
    session_destroy();
    redirect('/admin.php');
}

// --- Login ---
if (!isset($_SESSION['user_id']) && $action !== 'login_post') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = 'Ungültige Anmeldedaten.';
    }
    show_login($error);
    exit;
}

if ($action === 'login_post' || ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['user_id']))) {
    handle_login();
    exit;
}

// --- Routing ---
match ($action) {
    'dashboard' => show_dashboard(),
    'pages' => show_pages(),
    'page_edit' => show_page_edit(),
    'page_save' => handle_page_save(),
    'page_delete' => handle_page_delete(),
    'media' => show_media(),
    'media_upload' => handle_media_upload(),
    'media_delete' => handle_media_delete(),
    'upload_tinymce' => handle_tinymce_upload(),
    'users' => show_users(),
    'user_edit' => show_user_edit(),
    'user_save' => handle_user_save(),
    'user_delete' => handle_user_delete(),
    'settings' => show_settings(),
    'settings_save' => handle_settings_save(),
    default => show_dashboard(),
};

// =============================================
// LOGIN
// =============================================
function show_login(string $error = ''): void {
    $settings = get_all_settings();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Admin</title>
    <style>
        :root { --primary: <?= escape($settings['primary_color'] ?? '#2e7d32') ?>; --secondary: <?= escape($settings['secondary_color'] ?? '#1b5e20') ?>; --accent: <?= escape($settings['accent_color'] ?? '#ff8f00') ?>; --bg: #f0f2f5; --text: #333; }
    </style>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100vh;">
    <div class="login-container">
        <h1>Anmeldung</h1>
        <?php if ($error): ?><div class="alert alert-error"><?= escape($error) ?></div><?php endif; ?>
        <form method="post" action="/admin.php?action=login_post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label for="username">Benutzername</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Anmelden</button>
        </form>
    </div>
</body>
</html>
<?php
}

function handle_login(): void {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        show_login('Ungültiges Sicherheitstoken.');
        return;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $db = get_db();
    $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = :u");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        redirect('/admin.php');
    } else {
        show_login('Ungültige Anmeldedaten.');
    }
}

function require_admin(): void {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        echo '<div class="alert alert-error">Zugriff verweigert. Nur Administratoren haben Zugriff auf diesen Bereich.</div>';
        exit;
    }
}

// =============================================
// DASHBOARD
// =============================================
function show_dashboard(): void {
    $db = get_db();
    $pageCount = $db->querySingle("SELECT COUNT(*) FROM pages");
    $mediaCount = $db->querySingle("SELECT COUNT(*) FROM media");
    $userCount = $db->querySingle("SELECT COUNT(*) FROM users");

    $currentSection = 'dashboard';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1>Dashboard</h1>
        <span>Willkommen, <?= escape($_SESSION['username']) ?></span>
    </div>
    <div class="dashboard-cards">
        <div class="card">
            <h3>Seiten</h3>
            <div class="card-value"><?= $pageCount ?></div>
        </div>
        <div class="card">
            <h3>Medien</h3>
            <div class="card-value"><?= $mediaCount ?></div>
        </div>
        <div class="card">
            <h3>Benutzer</h3>
            <div class="card-value"><?= $userCount ?></div>
        </div>
    </div>
    <div class="admin-header">
        <h2>Letzte Seiten</h2>
        <a href="/admin.php?action=page_edit" class="btn btn-primary">Neue Seite</a>
    </div>
    <table class="admin-table">
        <thead><tr><th>Titel</th><th>Slug</th><th>Layout</th><th>Status</th><th>Geändert</th></tr></thead>
        <tbody>
        <?php
        $result = $db->query("SELECT * FROM pages ORDER BY updated_at DESC LIMIT 5");
        while ($page = $result->fetchArray(SQLITE3_ASSOC)):
        ?>
            <tr>
                <td><a href="/admin.php?action=page_edit&id=<?= $page['id'] ?>"><?= escape($page['title']) ?></a></td>
                <td><?= escape($page['slug']) ?></td>
                <td><?= escape($page['layout']) ?></td>
                <td><?= $page['is_published'] ? 'Veröffentlicht' : 'Entwurf' ?></td>
                <td><?= escape($page['updated_at']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

// =============================================
// PAGES
// =============================================
function show_pages(): void {
    $db = get_db();
    $currentSection = 'pages';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1>Seiten verwalten</h1>
        <a href="/admin.php?action=page_edit" class="btn btn-primary">Neue Seite</a>
    </div>
    <table class="admin-table">
        <thead><tr><th>Reihenfolge</th><th>Titel</th><th>Slug</th><th>Layout</th><th>Menü</th><th>Status</th><th>Aktionen</th></tr></thead>
        <tbody>
        <?php
        $result = $db->query("SELECT p.*, pp.title as parent_title FROM pages p LEFT JOIN pages pp ON p.parent_id = pp.id ORDER BY p.menu_order ASC");
        while ($page = $result->fetchArray(SQLITE3_ASSOC)):
        ?>
            <tr>
                <td><?= $page['menu_order'] ?></td>
                <td>
                    <?php if ($page['parent_title']): ?><small style="color:#999;"><?= escape($page['parent_title']) ?> &raquo;</small> <?php endif; ?>
                    <a href="/admin.php?action=page_edit&id=<?= $page['id'] ?>"><?= escape($page['title']) ?></a>
                </td>
                <td><code><?= escape($page['slug']) ?></code></td>
                <td><?= escape($page['layout']) ?></td>
                <td><?= $page['show_in_menu'] ? 'Ja' : 'Nein' ?></td>
                <td><?= $page['is_published'] ? 'Veröffentlicht' : 'Entwurf' ?></td>
                <td>
                    <a href="/<?= escape($page['slug']) ?>" target="_blank" class="btn btn-sm btn-accent">Ansehen</a>
                    <a href="/admin.php?action=page_edit&id=<?= $page['id'] ?>" class="btn btn-sm btn-primary">Bearbeiten</a>
                    <a href="/admin.php?action=page_delete&id=<?= $page['id'] ?>&csrf=<?= csrf_token() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Seite wirklich löschen?')">Löschen</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

function show_page_edit(): void {
    $db = get_db();
    $id = (int)($_GET['id'] ?? 0);
    $page = null;

    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $page = $result->fetchArray(SQLITE3_ASSOC);
    }

    $page = $page ?: ['id' => 0, 'slug' => '', 'title' => '', 'content' => '', 'layout' => 'default', 'menu_order' => 0, 'parent_id' => null, 'show_in_menu' => 1, 'is_published' => 1];

    $parentPages = [];
    $result = $db->query("SELECT id, title FROM pages WHERE parent_id IS NULL ORDER BY menu_order");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['id'] != $page['id']) $parentPages[] = $row;
    }

    $currentSection = 'pages';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1><?= $page['id'] ? 'Seite bearbeiten' : 'Neue Seite' ?></h1>
        <a href="/admin.php?action=pages" class="btn btn-accent">Zurück</a>
    </div>

    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Seite gespeichert.</div><?php endif; ?>

    <form method="post" action="/admin.php?action=page_save">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $page['id'] ?>">

        <div style="display:grid;grid-template-columns:1fr 300px;gap:2rem;">
            <div>
                <div class="form-group">
                    <label for="title">Titel</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?= escape($page['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">URL-Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control" value="<?= escape($page['slug']) ?>" pattern="[a-z0-9\-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche" required>
                </div>
                <div class="form-group">
                    <label for="content">Inhalt</label>
                    <textarea id="content" name="content" class="tinymce-editor"><?= escape($page['content']) ?></textarea>
                </div>
            </div>
            <div>
                <div class="form-group">
                    <label for="layout">Layout</label>
                    <select id="layout" name="layout" class="form-control">
                        <option value="default" <?= $page['layout'] === 'default' ? 'selected' : '' ?>>Standard</option>
                        <option value="hero" <?= $page['layout'] === 'hero' ? 'selected' : '' ?>>Hero (Startseite)</option>
                        <option value="two-column" <?= $page['layout'] === 'two-column' ? 'selected' : '' ?>>Zweispaltig</option>
                        <option value="full-width" <?= $page['layout'] === 'full-width' ? 'selected' : '' ?>>Volle Breite</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="parent_id">Übergeordnete Seite</label>
                    <select id="parent_id" name="parent_id" class="form-control">
                        <option value="">– Keine (Hauptebene) –</option>
                        <?php foreach ($parentPages as $pp): ?>
                            <option value="<?= $pp['id'] ?>" <?= $page['parent_id'] == $pp['id'] ? 'selected' : '' ?>><?= escape($pp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="menu_order">Menüreihenfolge</label>
                    <input type="number" id="menu_order" name="menu_order" class="form-control" value="<?= (int)$page['menu_order'] ?>">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="show_in_menu" value="1" <?= $page['show_in_menu'] ? 'checked' : '' ?>>
                        Im Menü anzeigen
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_published" value="1" <?= $page['is_published'] ? 'checked' : '' ?>>
                        Veröffentlicht
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Speichern</button>
            </div>
        </div>
    </form>

    <script>
        document.getElementById('title').addEventListener('input', function() {
            const slug = document.getElementById('slug');
            if (!slug.dataset.manual) {
                slug.value = this.value.toLowerCase()
                    .replace(/[äÄ]/g, 'ae').replace(/[öÖ]/g, 'oe').replace(/[üÜ]/g, 'ue').replace(/ß/g, 'ss')
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            }
        });
        document.getElementById('slug').addEventListener('input', function() { this.dataset.manual = '1'; });
    </script>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

function handle_page_save(): void {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        redirect('/admin.php?action=pages');
    }

    $db = get_db();
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $layout = $_POST['layout'] ?? 'default';
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $menuOrder = (int)($_POST['menu_order'] ?? 0);
    $showInMenu = isset($_POST['show_in_menu']) ? 1 : 0;
    $isPublished = isset($_POST['is_published']) ? 1 : 0;

    // Sanitize content - allow safe HTML tags
    $content = strip_tags($content, '<h1><h2><h3><h4><h5><h6><p><br><a><img><ul><ol><li><strong><em><u><s><blockquote><pre><code><table><thead><tbody><tr><th><td><div><span><hr><figure><figcaption>');

    // Validate layout
    $allowedLayouts = ['default', 'hero', 'two-column', 'full-width'];
    if (!in_array($layout, $allowedLayouts)) $layout = 'default';

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE pages SET title=:title, slug=:slug, content=:content, layout=:layout, parent_id=:pid, menu_order=:ord, show_in_menu=:menu, is_published=:pub, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare("INSERT INTO pages (title, slug, content, layout, parent_id, menu_order, show_in_menu, is_published) VALUES (:title, :slug, :content, :layout, :pid, :ord, :menu, :pub)");
    }

    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $stmt->bindValue(':content', $content, SQLITE3_TEXT);
    $stmt->bindValue(':layout', $layout, SQLITE3_TEXT);
    $stmt->bindValue(':pid', $parentId, $parentId ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(':ord', $menuOrder, SQLITE3_INTEGER);
    $stmt->bindValue(':menu', $showInMenu, SQLITE3_INTEGER);
    $stmt->bindValue(':pub', $isPublished, SQLITE3_INTEGER);
    $stmt->execute();

    $newId = $id > 0 ? $id : $db->lastInsertRowID();
    redirect('/admin.php?action=page_edit&id=' . $newId . '&saved=1');
}

function handle_page_delete(): void {
    if (!verify_csrf($_GET['csrf'] ?? '')) {
        redirect('/admin.php?action=pages');
    }

    $db = get_db();
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM pages WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    redirect('/admin.php?action=pages');
}

// =============================================
// MEDIA
// =============================================
function show_media(): void {
    $db = get_db();
    $currentSection = 'media';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1>Medienverwaltung</h1>
    </div>

    <?php if (isset($_GET['uploaded'])): ?><div class="alert alert-success">Bild erfolgreich hochgeladen.</div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div class="alert alert-error"><?= escape($_GET['error']) ?></div><?php endif; ?>

    <form method="post" action="/admin.php?action=media_upload" enctype="multipart/form-data" style="margin-bottom:2rem;padding:1.5rem;background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label for="file">Bild hochladen (max. <?= MAX_IMAGE_WIDTH ?>x<?= MAX_IMAGE_HEIGHT ?>px, wird automatisch skaliert)</label>
            <input type="file" id="file" name="file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" required>
        </div>
        <button type="submit" class="btn btn-primary">Hochladen</button>
    </form>

    <div class="media-grid">
    <?php
    $result = $db->query("SELECT * FROM media ORDER BY uploaded_at DESC");
    while ($item = $result->fetchArray(SQLITE3_ASSOC)):
    ?>
        <div class="media-item">
            <img src="/uploads/<?= escape($item['filename']) ?>" alt="<?= escape($item['original_name']) ?>" loading="lazy">
            <div class="media-info">
                <strong><?= escape($item['original_name']) ?></strong><br>
                <small><?= escape($item['uploaded_at']) ?></small><br>
                <input type="text" value="/uploads/<?= escape($item['filename']) ?>" class="form-control" style="font-size:0.75rem;margin:0.3rem 0;" readonly onclick="this.select();document.execCommand('copy');">
                <a href="/admin.php?action=media_delete&id=<?= $item['id'] ?>&csrf=<?= csrf_token() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bild löschen?')">Löschen</a>
            </div>
        </div>
    <?php endwhile; ?>
    </div>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

function handle_media_upload(): void {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        redirect('/admin.php?action=media&error=' . urlencode('Ungültiges Token.'));
    }

    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        redirect('/admin.php?action=media&error=' . urlencode('Upload fehlgeschlagen.'));
    }

    $file = $_FILES['file'];

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        redirect('/admin.php?action=media&error=' . urlencode('Ungültiger Dateityp. Erlaubt: JPEG, PNG, GIF, WebP.'));
    }

    // Validate file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        redirect('/admin.php?action=media&error=' . urlencode('Datei zu groß (max. 10 MB).'));
    }

    // Generate unique filename
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg',
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = UPLOAD_PATH . '/' . $filename;

    // Resize and save
    if (!resize_image($file['tmp_name'], $destPath)) {
        redirect('/admin.php?action=media&error=' . urlencode('Bildverarbeitung fehlgeschlagen.'));
    }

    // Save to database
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO media (filename, original_name, mime_type, file_size, uploaded_by) VALUES (:fn, :on, :mt, :fs, :ub)");
    $stmt->bindValue(':fn', $filename, SQLITE3_TEXT);
    $stmt->bindValue(':on', $file['name'], SQLITE3_TEXT);
    $stmt->bindValue(':mt', $mime, SQLITE3_TEXT);
    $stmt->bindValue(':fs', filesize($destPath), SQLITE3_INTEGER);
    $stmt->bindValue(':ub', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->execute();

    redirect('/admin.php?action=media&uploaded=1');
}

function handle_media_delete(): void {
    if (!verify_csrf($_GET['csrf'] ?? '')) {
        redirect('/admin.php?action=media');
    }

    $db = get_db();
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT filename FROM media WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $item = $result->fetchArray(SQLITE3_ASSOC);

    if ($item) {
        $filepath = UPLOAD_PATH . '/' . $item['filename'];
        if (file_exists($filepath)) unlink($filepath);
        $stmt = $db->prepare("DELETE FROM media WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    redirect('/admin.php?action=media');
}

function handle_tinymce_upload(): void {
    header('Content-Type: application/json');

    if (empty($_FILES['file']['tmp_name'])) {
        echo json_encode(['error' => 'Keine Datei.']);
        exit;
    }

    $file = $_FILES['file'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        echo json_encode(['error' => 'Ungültiger Dateityp.']);
        exit;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', default => 'jpg',
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = UPLOAD_PATH . '/' . $filename;

    if (!resize_image($file['tmp_name'], $destPath)) {
        echo json_encode(['error' => 'Bildverarbeitung fehlgeschlagen.']);
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare("INSERT INTO media (filename, original_name, mime_type, file_size, uploaded_by) VALUES (:fn, :on, :mt, :fs, :ub)");
    $stmt->bindValue(':fn', $filename, SQLITE3_TEXT);
    $stmt->bindValue(':on', $file['name'], SQLITE3_TEXT);
    $stmt->bindValue(':mt', $mime, SQLITE3_TEXT);
    $stmt->bindValue(':fs', filesize($destPath), SQLITE3_INTEGER);
    $stmt->bindValue(':ub', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->execute();

    echo json_encode(['location' => '/uploads/' . $filename]);
    exit;
}

// =============================================
// USERS
// =============================================
function show_users(): void {
    require_admin();
    $db = get_db();
    $currentSection = 'users';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1>Benutzerverwaltung</h1>
        <a href="/admin.php?action=user_edit" class="btn btn-primary">Neuer Benutzer</a>
    </div>

    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Benutzer gespeichert.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Benutzer gelöscht.</div><?php endif; ?>

    <table class="admin-table">
        <thead><tr><th>Benutzername</th><th>Rolle</th><th>Erstellt</th><th>Aktionen</th></tr></thead>
        <tbody>
        <?php
        $result = $db->query("SELECT * FROM users ORDER BY id ASC");
        while ($user = $result->fetchArray(SQLITE3_ASSOC)):
        ?>
            <tr>
                <td><?= escape($user['username']) ?></td>
                <td><?= escape($user['role']) ?></td>
                <td><?= escape($user['created_at']) ?></td>
                <td>
                    <a href="/admin.php?action=user_edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Bearbeiten</a>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="/admin.php?action=user_delete&id=<?= $user['id'] ?>&csrf=<?= csrf_token() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Benutzer löschen?')">Löschen</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

function show_user_edit(): void {
    require_admin();
    $db = get_db();
    $id = (int)($_GET['id'] ?? 0);
    $user = null;

    if ($id > 0) {
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
    }

    $user = $user ?: ['id' => 0, 'username' => '', 'role' => 'editor'];

    $currentSection = 'users';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1><?= $user['id'] ? 'Benutzer bearbeiten' : 'Neuer Benutzer' ?></h1>
        <a href="/admin.php?action=users" class="btn btn-accent">Zurück</a>
    </div>

    <form method="post" action="/admin.php?action=user_save" style="max-width:500px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" class="form-control" value="<?= escape($user['username']) ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Passwort <?= $user['id'] ? '(leer lassen = nicht ändern)' : '' ?></label>
            <input type="password" id="password" name="password" class="form-control" <?= $user['id'] ? '' : 'required' ?> minlength="8">
        </div>
        <div class="form-group">
            <label for="role">Rolle</label>
            <select id="role" name="role" class="form-control">
                <option value="editor" <?= $user['role'] === 'editor' ? 'selected' : '' ?>>Redakteur</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

function handle_user_save(): void {
    require_admin();
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        redirect('/admin.php?action=users');
    }

    $db = get_db();
    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['admin', 'editor']) ? $_POST['role'] : 'editor';

    if (empty($username)) redirect('/admin.php?action=users');

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE users SET username=:u, role=:r WHERE id=:id");
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $stmt->bindValue(':r', $role, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();

        if (!empty($password)) {
            $stmt = $db->prepare("UPDATE users SET password=:p WHERE id=:id");
            $stmt->bindValue(':p', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    } else {
        if (empty($password)) redirect('/admin.php?action=user_edit');
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:u, :p, :r)");
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $stmt->bindValue(':p', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':r', $role, SQLITE3_TEXT);
        $stmt->execute();
    }

    redirect('/admin.php?action=users&saved=1');
}

function handle_user_delete(): void {
    require_admin();
    if (!verify_csrf($_GET['csrf'] ?? '')) {
        redirect('/admin.php?action=users');
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id == $_SESSION['user_id']) {
        redirect('/admin.php?action=users');
    }

    $db = get_db();
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    redirect('/admin.php?action=users&deleted=1');
}

// =============================================
// SETTINGS
// =============================================
function show_settings(): void {
    require_admin();
    $settings = get_all_settings();
    $currentSection = 'settings';
    require __DIR__ . '/admin/includes/header.php';
?>
    <div class="admin-header">
        <h1>Einstellungen</h1>
    </div>

    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Einstellungen gespeichert.</div><?php endif; ?>

    <form method="post" action="/admin.php?action=settings_save" style="max-width:600px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <h3 style="margin-bottom:1rem;">Allgemein</h3>
        <div class="form-group">
            <label for="site_name">Seitenname</label>
            <input type="text" id="site_name" name="site_name" class="form-control" value="<?= escape($settings['site_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="site_subtitle">Untertitel</label>
            <input type="text" id="site_subtitle" name="site_subtitle" class="form-control" value="<?= escape($settings['site_subtitle'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="footer_text">Footer-Text</label>
            <input type="text" id="footer_text" name="footer_text" class="form-control" value="<?= escape($settings['footer_text'] ?? '') ?>">
        </div>

        <h3 style="margin:2rem 0 1rem;">Farben (Layout)</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label for="primary_color">Primärfarbe</label>
                <input type="color" id="primary_color" name="primary_color" value="<?= escape($settings['primary_color'] ?? '#2e7d32') ?>">
            </div>
            <div class="form-group">
                <label for="secondary_color">Sekundärfarbe</label>
                <input type="color" id="secondary_color" name="secondary_color" value="<?= escape($settings['secondary_color'] ?? '#1b5e20') ?>">
            </div>
            <div class="form-group">
                <label for="accent_color">Akzentfarbe</label>
                <input type="color" id="accent_color" name="accent_color" value="<?= escape($settings['accent_color'] ?? '#ff8f00') ?>">
            </div>
            <div class="form-group">
                <label for="bg_color">Hintergrundfarbe</label>
                <input type="color" id="bg_color" name="bg_color" value="<?= escape($settings['bg_color'] ?? '#ffffff') ?>">
            </div>
            <div class="form-group">
                <label for="text_color">Textfarbe</label>
                <input type="color" id="text_color" name="text_color" value="<?= escape($settings['text_color'] ?? '#333333') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Einstellungen speichern</button>
    </form>
<?php
    require __DIR__ . '/admin/includes/footer.php';
}

function handle_settings_save(): void {
    require_admin();
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        redirect('/admin.php?action=settings');
    }

    $db = get_db();
    $allowedKeys = ['site_name', 'site_subtitle', 'footer_text', 'primary_color', 'secondary_color', 'accent_color', 'bg_color', 'text_color'];

    foreach ($allowedKeys as $key) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            // Validate color values
            if (str_contains($key, 'color') && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                continue;
            }
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)");
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
        }
    }

    redirect('/admin.php?action=settings&saved=1');
}
