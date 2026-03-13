<?php
$settings = get_all_settings();
$menuPages = get_menu_pages();
$menuTree = build_menu_tree($menuPages);
$currentSlug = $currentSlug ?? 'startseite';
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
            --primary: <?= escape($settings['primary_color'] ?? '#2e7d32') ?>;
            --secondary: <?= escape($settings['secondary_color'] ?? '#1b5e20') ?>;
            --accent: <?= escape($settings['accent_color'] ?? '#ff8f00') ?>;
            --bg: <?= escape($settings['bg_color'] ?? '#ffffff') ?>;
            --text: <?= escape($settings['text_color'] ?? '#333333') ?>;
        }
    </style>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="site-logo">
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
