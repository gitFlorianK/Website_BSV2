<?php
send_security_headers(true);
$settings = get_all_settings();
$currentSection = $currentSection ?? 'dashboard';

$colors = [
    'primary' => validate_color($settings['primary_color'] ?? '') ? $settings['primary_color'] : '#1a2744',
    'secondary' => validate_color($settings['secondary_color'] ?? '') ? $settings['secondary_color'] : '#0f1b33',
    'accent' => validate_color($settings['accent_color'] ?? '') ? $settings['accent_color'] : '#f5920a',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – <?= escape($settings['site_name'] ?? 'CMS') ?></title>
    <style>
        :root {
            --primary: <?= $colors['primary'] ?>;
            --secondary: <?= $colors['secondary'] ?>;
            --accent: <?= $colors['accent'] ?>;
            --bg: #f0f2f5;
            --text: #333;
        }
    </style>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js" crossorigin="anonymous"></script>
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
                <li>
                    <form method="post" action="/admin.php?action=logout" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="admin-nav-btn">Abmelden</button>
                    </form>
                </li>
            </ul>
        </aside>
        <div class="admin-content">
