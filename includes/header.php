<?php
$menuTree = getMenuTree();
$currentSlug = $_GET['page'] ?? 'startseite';
$siteName = getSetting('site_name', 'BSV 1960 Plauen e.V.');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $siteName) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="site-nav">
    <div class="nav-container">
        <a href="/" class="nav-brand">
            <img src="/Logo_Verein_2_FK.JPG" alt="<?= e($siteName) ?>">
            <span><?= e($siteName) ?></span>
        </a>
        <button class="nav-toggle" onclick="document.querySelector('.nav-menu').classList.toggle('open')" aria-label="Menü">&#9776;</button>
        <ul class="nav-menu">
            <?php foreach ($menuTree as $item): ?>
                <?php if ($item['slug'] === 'impressum' || $item['slug'] === 'datenschutz') continue; ?>
                <li>
                    <a href="/?page=<?= e($item['slug']) ?>"
                       class="<?= $currentSlug === $item['slug'] ? 'active' : '' ?>">
                        <?= e($item['title']) ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                    <div class="dropdown">
                        <?php foreach ($item['children'] as $child): ?>
                            <a href="/?page=<?= e($child['slug']) ?>"
                               class="<?= $currentSlug === $child['slug'] ? 'active' : '' ?>">
                                <?= e($child['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
