<?php requireLogin(); ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= e($adminTitle ?? 'CMS') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
<div class="admin-wrap">
    <aside class="admin-sidebar">
        <h2>CMS Verwaltung</h2>
        <a href="/admin.php?action=dashboard" class="<?= ($_GET['action'] ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="/admin.php?action=pages" class="<?= ($_GET['action'] ?? '') === 'pages' ? 'active' : '' ?>">Seiten</a>
        <a href="/admin.php?action=page_new" class="<?= ($_GET['action'] ?? '') === 'page_new' ? 'active' : '' ?>">Neue Seite</a>
        <a href="/admin.php?action=media" class="<?= ($_GET['action'] ?? '') === 'media' ? 'active' : '' ?>">Medien</a>
        <a href="/admin.php?action=settings" class="<?= ($_GET['action'] ?? '') === 'settings' ? 'active' : '' ?>">Einstellungen</a>
        <a href="/admin.php?action=password">Passwort &auml;ndern</a>
        <a href="/" target="_blank">Website ansehen</a>
        <a href="/admin.php?action=logout">Abmelden</a>
    </aside>
    <main class="admin-main">
