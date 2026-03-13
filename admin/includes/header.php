<?php
$settings = get_all_settings();
$currentSection = $currentSection ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – <?= escape($settings['site_name'] ?? 'CMS') ?></title>
    <style>
        :root {
            --primary: <?= escape($settings['primary_color'] ?? '#2e7d32') ?>;
            --secondary: <?= escape($settings['secondary_color'] ?? '#1b5e20') ?>;
            --accent: <?= escape($settings['accent_color'] ?? '#ff8f00') ?>;
            --bg: #f0f2f5;
            --text: #333;
        }
    </style>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar" id="admin-sidebar">
            <h2><?= escape($settings['site_name'] ?? 'CMS') ?></h2>
            <ul class="admin-nav">
                <li><a href="/admin.php?action=dashboard" <?= $currentSection === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a></li>
                <li><a href="/admin.php?action=pages" <?= $currentSection === 'pages' ? 'class="active"' : '' ?>>Seiten</a></li>
                <li><a href="/admin.php?action=media" <?= $currentSection === 'media' ? 'class="active"' : '' ?>>Medien</a></li>
                <li><a href="/admin.php?action=users" <?= $currentSection === 'users' ? 'class="active"' : '' ?>>Benutzer</a></li>
                <li><a href="/admin.php?action=settings" <?= $currentSection === 'settings' ? 'class="active"' : '' ?>>Einstellungen</a></li>
                <li><a href="/" target="_blank">Website ansehen</a></li>
                <li><a href="/admin.php?action=logout">Abmelden</a></li>
            </ul>
        </aside>
        <div class="admin-content">
