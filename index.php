<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['page'] ?? 'startseite', '/');
$slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

if (empty($slug)) $slug = 'startseite';

$page = get_page($slug);

if (!$page) {
    http_response_code(404);
    $pageTitle = 'Seite nicht gefunden';
    $currentSlug = '';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><h1>404 – Seite nicht gefunden</h1><p>Die angeforderte Seite existiert nicht.</p><p><a href="/">Zurück zur Startseite</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $page['title'] . ' – ' . get_setting('site_name');
$currentSlug = $slug;
$layout = $page['layout'] ?? 'default';

require __DIR__ . '/includes/header.php';
?>

<?php if ($layout === 'hero'): ?>
    <section class="hero">
        <div class="hero-content">
            <h1><?= escape($page['title']) ?></h1>
            <p class="hero-subtitle"><?= escape(get_setting('site_subtitle')) ?></p>
        </div>
    </section>
    <div class="container">
        <article class="page-content">
            <?= $page['content'] ?>
        </article>
    </div>

<?php elseif ($layout === 'two-column'): ?>
    <div class="container layout-two-column">
        <article class="page-content content-main">
            <?= $page['content'] ?>
        </article>
        <aside class="content-sidebar">
            <h3>Navigation</h3>
            <?php
            $menuPages = get_menu_pages();
            foreach ($menuPages as $mp) {
                if ($mp['parent_id'] == $page['parent_id'] || $mp['parent_id'] == $page['id']) {
                    echo '<a href="/' . escape($mp['slug']) . '">' . escape($mp['title']) . '</a><br>';
                }
            }
            ?>
        </aside>
    </div>

<?php elseif ($layout === 'full-width'): ?>
    <div class="full-width">
        <article class="page-content">
            <?= $page['content'] ?>
        </article>
    </div>

<?php else: ?>
    <div class="container">
        <h1 class="page-title"><?= escape($page['title']) ?></h1>
        <article class="page-content">
            <?= $page['content'] ?>
        </article>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
