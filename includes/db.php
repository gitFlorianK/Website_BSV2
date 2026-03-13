<?php
function getDB(): SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->enableExceptions(true);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        initDB($db);
    }
    return $db;
}

function initDB(SQLite3 $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS pages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        content TEXT DEFAULT '',
        layout TEXT DEFAULT 'default',
        menu_order INTEGER DEFAULT 0,
        parent_id INTEGER DEFAULT NULL,
        show_in_menu INTEGER DEFAULT 1,
        is_published INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        original_name TEXT NOT NULL,
        mime_type TEXT,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default admin user (admin / admin123 - should be changed)
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users");
    $result = $stmt->execute()->fetchArray();
    if ($result['cnt'] == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bindValue(1, 'admin');
        $stmt->bindValue(2, $hash);
        $stmt->execute();
    }

    // Seed default pages
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM pages");
    $result = $stmt->execute()->fetchArray();
    if ($result['cnt'] == 0) {
        $defaultPages = [
            ['startseite', 'Startseite', '<div class="text-center"><h1>Willkommen beim BSV 1960 Plauen e.V.</h1><p class="lead">Bogensport mit Tradition seit 1960</p></div>', 'hero', 1, null],
            ['impressum', 'Impressum', '<h2>Impressum</h2><p>Angaben gem&auml;&szlig; &sect; 5 TMG:</p><p><strong>BSV 1960 Plauen e.V.</strong><br>Musterstra&szlig;e 1<br>08523 Plauen</p><p><strong>Vertreten durch:</strong><br>Vorstandsvorsitzende/r: [Name]</p><p><strong>Kontakt:</strong><br>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p><p><strong>Registereintrag:</strong><br>Eingetragen im Vereinsregister.<br>Registergericht: Amtsgericht Plauen<br>Registernummer: [VR-Nummer]</p>', 'default', 90, null],
            ['datenschutz', 'Datenschutzerkl&auml;rung', '<h2>Datenschutzerkl&auml;rung</h2><h3>1. Datenschutz auf einen Blick</h3><h4>Allgemeine Hinweise</h4><p>Die folgenden Hinweise geben einen einfachen &Uuml;berblick dar&uuml;ber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie pers&ouml;nlich identifiziert werden k&ouml;nnen.</p><h4>Datenerfassung auf dieser Website</h4><p><strong>Wer ist verantwortlich f&uuml;r die Datenerfassung auf dieser Website?</strong></p><p>Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber: BSV 1960 Plauen e.V., Musterstra&szlig;e 1, 08523 Plauen.</p><h3>2. Hosting</h3><p>Diese Website wird bei [Hosting-Anbieter] gehostet. Details entnehmen Sie der Datenschutzerkl&auml;rung des Anbieters.</p><h3>3. Allgemeine Hinweise und Pflichtinformationen</h3><h4>Datenschutz</h4><p>Die Betreiber dieser Seiten nehmen den Schutz Ihrer pers&ouml;nlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend den gesetzlichen Datenschutzvorschriften sowie dieser Datenschutzerkl&auml;rung.</p><p><strong>Hinweis:</strong> Bitte passen Sie diese Datenschutzerkl&auml;rung an Ihre spezifischen Gegebenheiten an. Nutzen Sie ggf. einen Datenschutzerkl&auml;rungs-Generator.</p>', 'default', 91, null],
        ];

        $stmt = $db->prepare("INSERT INTO pages (slug, title, content, layout, menu_order, parent_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($defaultPages as $page) {
            $stmt->bindValue(1, $page[0]);
            $stmt->bindValue(2, $page[1]);
            $stmt->bindValue(3, $page[2]);
            $stmt->bindValue(4, $page[3]);
            $stmt->bindValue(5, $page[4]);
            $stmt->bindValue(6, $page[5]);
            $stmt->execute();
            $stmt->reset();
        }
    }

    // Seed default settings
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM settings");
    $result = $stmt->execute()->fetchArray();
    if ($result['cnt'] == 0) {
        $defaults = [
            ['site_name', 'BSV 1960 Plauen e.V.'],
            ['site_subtitle', 'Bogensport mit Tradition seit 1960'],
            ['footer_text', '&copy; ' . date('Y') . ' BSV 1960 Plauen e.V. - Alle Rechte vorbehalten.'],
            ['primary_color', '#F5A623'],
            ['secondary_color', '#1B1464'],
            ['bg_color', '#121212'],
        ];
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        foreach ($defaults as $s) {
            $stmt->bindValue(1, $s[0]);
            $stmt->bindValue(2, $s[1]);
            $stmt->execute();
            $stmt->reset();
        }
    }
}
