<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'dashboard';

// --- Login / Logout (no auth required) ---
if ($action === 'login') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bindValue(1, $_POST['username'] ?? '');
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: /admin.php?action=dashboard');
            exit;
        }
        $error = 'Ungültige Anmeldedaten.';
    }
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
    <div class="login-wrap">
        <div class="login-box">
            <h1>Admin Login</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Anmelden</button>
            </form>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: /admin.php?action=login');
    exit;
}

// --- All other actions require login ---
requireLogin();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function checkCsrf(): void {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        die('Ungültiger CSRF-Token.');
    }
}

// --- TinyMCE image upload endpoint ---
if ($action === 'upload_image_tinymce') {
    header('Content-Type: application/json');
    if (!empty($_FILES['file'])) {
        $result = uploadImage($_FILES['file']);
        if ($result) {
            echo json_encode(['location' => $result['url']]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Upload fehlgeschlagen.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Datei.']);
    }
    exit;
}

$adminTitle = 'CMS';
$message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

require SITE_ROOT . '/admin/includes/header.php';

if ($message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif;

// ========== DASHBOARD ==========
if ($action === 'dashboard'): ?>
    <h1>Dashboard</h1>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
        <div class="page-content" style="text-align:center;">
            <h2 style="font-size:2rem; margin:0;"><?= getDB()->querySingle("SELECT COUNT(*) FROM pages") ?></h2>
            <p style="color:var(--text-muted);">Seiten</p>
        </div>
        <div class="page-content" style="text-align:center;">
            <h2 style="font-size:2rem; margin:0;"><?= getDB()->querySingle("SELECT COUNT(*) FROM media") ?></h2>
            <p style="color:var(--text-muted);">Medien</p>
        </div>
    </div>

<?php
// ========== PAGES LIST ==========
elseif ($action === 'pages'):
    $pages = getPages(false);
?>
    <h1>Seiten verwalten</h1>
    <a href="/admin.php?action=page_new" class="btn btn-primary" style="margin-bottom:1rem;">Neue Seite erstellen</a>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Slug</th>
                <th>Layout</th>
                <th>Men&uuml;</th>
                <th>Reihenfolge</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
            <tr>
                <td><?= e($p['title']) ?></td>
                <td><code><?= e($p['slug']) ?></code></td>
                <td><?= e($p['layout']) ?></td>
                <td><?= $p['show_in_menu'] ? 'Ja' : 'Nein' ?></td>
                <td><?= $p['menu_order'] ?></td>
                <td><?= $p['is_published'] ? '<span style="color:#27ae60">Aktiv</span>' : '<span style="color:#c0392b">Entwurf</span>' ?></td>
                <td>
                    <a href="/admin.php?action=page_edit&id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Bearbeiten</a>
                    <a href="/admin.php?action=page_delete&id=<?= $p['id'] ?>&csrf=<?= $csrf ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Seite wirklich l&ouml;schen?')">L&ouml;schen</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php
// ========== NEW PAGE ==========
elseif ($action === 'page_new'):
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $db = getDB();
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
        $content = $_POST['content'] ?? '';
        $layout = $_POST['layout'] ?? 'default';
        $menuOrder = (int)($_POST['menu_order'] ?? 0);
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
        $showInMenu = isset($_POST['show_in_menu']) ? 1 : 0;
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        $stmt = $db->prepare("INSERT INTO pages (slug, title, content, layout, menu_order, parent_id, show_in_menu, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $slug);
        $stmt->bindValue(2, $title);
        $stmt->bindValue(3, $content);
        $stmt->bindValue(4, $layout);
        $stmt->bindValue(5, $menuOrder);
        $stmt->bindValue(6, $parentId);
        $stmt->bindValue(7, $showInMenu);
        $stmt->bindValue(8, $isPublished);
        $stmt->execute();

        $_SESSION['flash_message'] = 'Seite erstellt.';
        header('Location: /admin.php?action=pages');
        exit;
    }
    $allPages = getPages(false);
?>
    <h1>Neue Seite erstellen</h1>
    <form method="post" style="max-width:900px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
            <label>Titel</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label>Slug (URL-Pfad, wird automatisch generiert wenn leer)</label>
            <input type="text" name="slug" placeholder="wird-automatisch-generiert">
        </div>
        <div class="form-group">
            <label>Layout</label>
            <select name="layout">
                <option value="default">Standard</option>
                <option value="hero">Hero (Startseite)</option>
                <option value="two-column">Zweispaltig</option>
                <option value="full-width">Volle Breite</option>
            </select>
        </div>
        <div class="form-group">
            <label>&Uuml;bergeordnete Seite</label>
            <select name="parent_id">
                <option value="">Keine (Hauptmen&uuml;)</option>
                <?php foreach ($allPages as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Men&uuml;-Reihenfolge</label>
            <input type="number" name="menu_order" value="10">
        </div>
        <div class="form-group checkbox-group">
            <input type="checkbox" name="show_in_menu" id="show_in_menu" checked>
            <label for="show_in_menu">Im Men&uuml; anzeigen</label>
        </div>
        <div class="form-group checkbox-group">
            <input type="checkbox" name="is_published" id="is_published" checked>
            <label for="is_published">Ver&ouml;ffentlicht</label>
        </div>
        <div class="form-group">
            <label>Inhalt</label>
            <textarea name="content" class="tinymce-editor"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Seite erstellen</button>
        <a href="/admin.php?action=pages" class="btn btn-secondary">Abbrechen</a>
    </form>

<?php
// ========== EDIT PAGE ==========
elseif ($action === 'page_edit'):
    $id = (int)($_GET['id'] ?? 0);
    $page = getPageById($id);
    if (!$page) { echo '<p>Seite nicht gefunden.</p>'; require SITE_ROOT . '/admin/includes/footer.php'; exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $db = getDB();
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
        $content = $_POST['content'] ?? '';
        $layout = $_POST['layout'] ?? 'default';
        $menuOrder = (int)($_POST['menu_order'] ?? 0);
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
        $showInMenu = isset($_POST['show_in_menu']) ? 1 : 0;
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE pages SET slug=?, title=?, content=?, layout=?, menu_order=?, parent_id=?, show_in_menu=?, is_published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->bindValue(1, $slug);
        $stmt->bindValue(2, $title);
        $stmt->bindValue(3, $content);
        $stmt->bindValue(4, $layout);
        $stmt->bindValue(5, $menuOrder);
        $stmt->bindValue(6, $parentId);
        $stmt->bindValue(7, $showInMenu);
        $stmt->bindValue(8, $isPublished);
        $stmt->bindValue(9, $id);
        $stmt->execute();

        $_SESSION['flash_message'] = 'Seite aktualisiert.';
        header('Location: /admin.php?action=pages');
        exit;
    }
    $allPages = getPages(false);
?>
    <h1>Seite bearbeiten: <?= e($page['title']) ?></h1>
    <form method="post" style="max-width:900px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
            <label>Titel</label>
            <input type="text" name="title" value="<?= e($page['title']) ?>" required>
        </div>
        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" value="<?= e($page['slug']) ?>">
        </div>
        <div class="form-group">
            <label>Layout</label>
            <select name="layout">
                <option value="default" <?= $page['layout'] === 'default' ? 'selected' : '' ?>>Standard</option>
                <option value="hero" <?= $page['layout'] === 'hero' ? 'selected' : '' ?>>Hero (Startseite)</option>
                <option value="two-column" <?= $page['layout'] === 'two-column' ? 'selected' : '' ?>>Zweispaltig</option>
                <option value="full-width" <?= $page['layout'] === 'full-width' ? 'selected' : '' ?>>Volle Breite</option>
            </select>
        </div>
        <div class="form-group">
            <label>&Uuml;bergeordnete Seite</label>
            <select name="parent_id">
                <option value="">Keine (Hauptmen&uuml;)</option>
                <?php foreach ($allPages as $p): if ($p['id'] == $id) continue; ?>
                    <option value="<?= $p['id'] ?>" <?= $page['parent_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Men&uuml;-Reihenfolge</label>
            <input type="number" name="menu_order" value="<?= $page['menu_order'] ?>">
        </div>
        <div class="form-group checkbox-group">
            <input type="checkbox" name="show_in_menu" id="show_in_menu" <?= $page['show_in_menu'] ? 'checked' : '' ?>>
            <label for="show_in_menu">Im Men&uuml; anzeigen</label>
        </div>
        <div class="form-group checkbox-group">
            <input type="checkbox" name="is_published" id="is_published" <?= $page['is_published'] ? 'checked' : '' ?>>
            <label for="is_published">Ver&ouml;ffentlicht</label>
        </div>
        <div class="form-group">
            <label>Inhalt</label>
            <textarea name="content" class="tinymce-editor"><?= e($page['content']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
        <a href="/admin.php?action=pages" class="btn btn-secondary">Abbrechen</a>
    </form>

<?php
// ========== DELETE PAGE ==========
elseif ($action === 'page_delete'):
    $id = (int)($_GET['id'] ?? 0);
    if (($_GET['csrf'] ?? '') !== $csrf) { die('Ungültiger CSRF-Token.'); }
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
    $stmt->bindValue(1, $id);
    $stmt->execute();
    $_SESSION['flash_message'] = 'Seite gelöscht.';
    header('Location: /admin.php?action=pages');
    exit;

// ========== MEDIA ==========
elseif ($action === 'media'):
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['images'])) {
        checkCsrf();
        $files = $_FILES['images'];
        $uploaded = 0;
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                if (uploadImage($file)) $uploaded++;
            }
        }
        $_SESSION['flash_message'] = "$uploaded Bild(er) hochgeladen.";
        header('Location: /admin.php?action=media');
        exit;
    }

    $db = getDB();
    $media = [];
    $result = $db->query("SELECT * FROM media ORDER BY uploaded_at DESC");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $media[] = $row;
    }
?>
    <h1>Medien</h1>
    <form method="post" enctype="multipart/form-data" style="margin-bottom:2rem;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
            <label>Bilder hochladen (max. <?= MAX_IMAGE_WIDTH ?>x<?= MAX_IMAGE_HEIGHT ?>px, werden automatisch skaliert)</label>
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp"
                   style="color:var(--text-light);">
        </div>
        <button type="submit" class="btn btn-primary">Hochladen</button>
    </form>

    <div class="media-grid">
        <?php foreach ($media as $m): ?>
        <div class="media-item">
            <img src="/uploads/<?= e($m['filename']) ?>" alt="<?= e($m['original_name']) ?>">
            <div class="media-info"><?= e($m['original_name']) ?></div>
            <div class="media-actions">
                <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText('/uploads/<?= e($m['filename']) ?>').then(()=>alert('URL kopiert!'))">URL kopieren</button>
                <a href="/admin.php?action=media_delete&id=<?= $m['id'] ?>&csrf=<?= $csrf ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Bild l&ouml;schen?')">L&ouml;schen</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (empty($media)): ?>
        <p style="color:var(--text-muted);">Noch keine Bilder hochgeladen.</p>
    <?php endif; ?>

<?php
// ========== DELETE MEDIA ==========
elseif ($action === 'media_delete'):
    if (($_GET['csrf'] ?? '') !== $csrf) { die('Ungültiger CSRF-Token.'); }
    $id = (int)($_GET['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("SELECT filename FROM media WHERE id = ?");
    $stmt->bindValue(1, $id);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $filepath = UPLOAD_DIR . $row['filename'];
        if (file_exists($filepath)) unlink($filepath);
        $stmt = $db->prepare("DELETE FROM media WHERE id = ?");
        $stmt->bindValue(1, $id);
        $stmt->execute();
    }
    $_SESSION['flash_message'] = 'Bild gelöscht.';
    header('Location: /admin.php?action=media');
    exit;

// ========== SETTINGS ==========
elseif ($action === 'settings'):
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        setSetting('site_name', $_POST['site_name'] ?? '');
        setSetting('site_subtitle', $_POST['site_subtitle'] ?? '');
        setSetting('footer_text', $_POST['footer_text'] ?? '');
        setSetting('primary_color', $_POST['primary_color'] ?? '#F5A623');
        setSetting('secondary_color', $_POST['secondary_color'] ?? '#1B1464');
        setSetting('bg_color', $_POST['bg_color'] ?? '#121212');
        $_SESSION['flash_message'] = 'Einstellungen gespeichert.';
        header('Location: /admin.php?action=settings');
        exit;
    }
?>
    <h1>Einstellungen</h1>
    <form method="post" style="max-width:600px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
            <label>Seitenname</label>
            <input type="text" name="site_name" value="<?= e(getSetting('site_name')) ?>">
        </div>
        <div class="form-group">
            <label>Untertitel</label>
            <input type="text" name="site_subtitle" value="<?= e(getSetting('site_subtitle')) ?>">
        </div>
        <div class="form-group">
            <label>Footer-Text</label>
            <input type="text" name="footer_text" value="<?= e(getSetting('footer_text')) ?>">
        </div>
        <div class="form-group">
            <label>Prim&auml;rfarbe</label>
            <input type="color" name="primary_color" value="<?= e(getSetting('primary_color', '#F5A623')) ?>">
        </div>
        <div class="form-group">
            <label>Sekund&auml;rfarbe</label>
            <input type="color" name="secondary_color" value="<?= e(getSetting('secondary_color', '#1B1464')) ?>">
        </div>
        <div class="form-group">
            <label>Hintergrundfarbe</label>
            <input type="color" name="bg_color" value="<?= e(getSetting('bg_color', '#121212')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

<?php
// ========== CHANGE PASSWORD ==========
elseif ($action === 'password'):
    $error = '';
    $success = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bindValue(1, $_SESSION['user_id']);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
            $error = 'Aktuelles Passwort ist falsch.';
        } elseif (strlen($_POST['new_password'] ?? '') < 8) {
            $error = 'Neues Passwort muss mindestens 8 Zeichen lang sein.';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bindValue(1, $hash);
            $stmt->bindValue(2, $_SESSION['user_id']);
            $stmt->execute();
            $success = 'Passwort geändert.';
        }
    }
?>
    <h1>Passwort &auml;ndern</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post" style="max-width:400px;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
            <label>Aktuelles Passwort</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>Neues Passwort</label>
            <input type="password" name="new_password" required minlength="8">
        </div>
        <div class="form-group">
            <label>Neues Passwort best&auml;tigen</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">&Auml;ndern</button>
    </form>

<?php else: ?>
    <h1>Dashboard</h1>
    <p>Willkommen im CMS. W&auml;hlen Sie eine Option in der Seitenleiste.</p>
<?php endif;

require SITE_ROOT . '/admin/includes/footer.php';
