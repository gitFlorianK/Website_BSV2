<?php
send_security_headers(false);
$settings = get_all_settings();
$menuPages = get_menu_pages();
$menuTree = build_menu_tree($menuPages);
$currentSlug = $currentSlug ?? 'startseite';

// Farben nur ausgeben wenn sie dem erwarteten Format entsprechen
$colors = [
    'primary' => validate_color($settings['primary_color'] ?? '') ? $settings['primary_color'] : '#1a2744',
    'secondary' => validate_color($settings['secondary_color'] ?? '') ? $settings['secondary_color'] : '#0f1b33',
    'accent' => validate_color($settings['accent_color'] ?? '') ? $settings['accent_color'] : '#f5920a',
    'bg' => validate_color($settings['bg_color'] ?? '') ? $settings['bg_color'] : '#f5f5f5',
    'text' => validate_color($settings['text_color'] ?? '') ? $settings['text_color'] : '#1a1a1a',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= escape($settings['site_name'] ?? 'Bogensportverein') ?>">
    <title><?= escape($pageTitle ?? $settings['site_name'] ?? 'Bogensportverein') ?></title>
    <style>
        :root {
            --primary: <?= $colors['primary'] ?>;
            --secondary: <?= $colors['secondary'] ?>;
            --accent: <?= $colors['accent'] ?>;
            --bg: <?= $colors['bg'] ?>;
            --text: <?= $colors['text'] ?>;
        }
    </style>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="site-logo">
                <img src="/uploads/bsv_logo_web.png" alt="<?= escape($settings['site_name'] ?? 'Bogensportverein') ?>" class="logo-img">
                <span class="site-name"><?= escape($settings['site_name'] ?? 'Bogensportverein') ?></span>
            </a>
            <button class="menu-toggle" aria-label="Menü öffnen" aria-expanded="false">
                <span class="hamburger"></span>
            </button>
            <nav class="main-nav" id="main-nav">
                <?= render_menu($menuTree, $currentSlug) ?>
            </nav>
        </div>
    </header>
    <main class="site-main">
