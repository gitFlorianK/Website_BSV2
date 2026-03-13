<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['page'] ?? 'startseite';
$slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
$page = getPage($slug);

if (!$page) {
    http_response_code(404);
    $pageTitle = '404 - Seite nicht gefunden';
    require SITE_ROOT . '/includes/header.php';
    echo '<div class="content-wrap"><div class="page-content"><h1>404</h1><p>Die angeforderte Seite wurde nicht gefunden.</p><p><a href="/">Zur Startseite</a></p></div></div>';
    require SITE_ROOT . '/includes/footer.php';
    exit;
}

$pageTitle = e($page['title']) . ' - ' . getSetting('site_name');
$layout = $page['layout'] ?? 'default';

require SITE_ROOT . '/includes/header.php';

if ($layout === 'hero' && $slug === 'startseite'): ?>
<section class="hero-section">
    <img src="/Logo_Verein_2_FK.JPG" alt="BSV 1960 Plauen e.V." class="hero-logo">
    <?= $page['content'] ?>
</section>
<div class="content-wrap">
    <!-- Placeholder for future content blocks -->
</div>
<?php elseif ($layout === 'two-column'): ?>
<div class="content-wrap layout-two-column">
    <div class="page-content">
        <?= $page['content'] ?>
    </div>
</div>
<?php else: ?>
<div class="content-wrap">
    <div class="page-content">
        <?= $page['content'] ?>
    </div>
</div>
<?php endif;

require SITE_ROOT . '/includes/footer.php';
