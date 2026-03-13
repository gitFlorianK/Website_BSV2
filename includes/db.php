<?php
require_once __DIR__ . '/../config.php';

function get_db(): SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }
    return $db;
}

function init_db(): void {
    $db = get_db();

    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'editor',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pages (
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
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            file_size INTEGER DEFAULT 0,
            uploaded_by INTEGER,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        );
    ");

    // Standardbenutzer anlegen falls keiner existiert
    $result = $db->querySingle("SELECT COUNT(*) FROM users");
    if ($result == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES ('admin', :pw, 'admin')");
        $stmt->bindValue(':pw', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }

    // Standardeinstellungen
    $defaults = [
        'site_name' => 'Bogensportverein',
        'site_subtitle' => 'Willkommen bei unserem Verein',
        'footer_text' => '&copy; ' . date('Y') . ' Bogensportverein',
        'primary_color' => '#1a2744',
        'secondary_color' => '#0f1b33',
        'accent_color' => '#f5920a',
        'bg_color' => '#f5f5f5',
        'text_color' => '#1a1a1a',
    ];

    foreach ($defaults as $key => $value) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)");
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        $stmt->execute();
    }

    // Standardseiten anlegen
    $pageCount = $db->querySingle("SELECT COUNT(*) FROM pages");
    if ($pageCount == 0) {
        $seedPages = [
            ['startseite', 'Startseite', '<h2>Willkommen beim Bogensportverein</h2><p>Wir freuen uns über Ihren Besuch auf unserer Webseite.</p>', 'hero', 1, 1],
            ['ueber-uns', 'Über uns', '<h2>Über unseren Verein</h2><p>Hier erfahren Sie mehr über unseren Bogensportverein.</p>', 'default', 2, 1],
            ['impressum', 'Impressum', '<h2>Impressum</h2><p><strong>Angaben gemäß § 5 TMG:</strong></p><p>Bogensportverein<br>Musterstraße 1<br>12345 Musterstadt</p><p><strong>Vertreten durch:</strong><br>Vorstand: Max Mustermann</p><p><strong>Kontakt:</strong><br>Telefon: 01234 / 56789<br>E-Mail: info@bogensportverein.de</p><p><strong>Registereintrag:</strong><br>Eingetragen im Vereinsregister.<br>Registergericht: Amtsgericht Musterstadt<br>Registernummer: VR 12345</p><p><strong>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV:</strong><br>Max Mustermann<br>Musterstraße 1<br>12345 Musterstadt</p>', 'default', 90, 1],
            ['datenschutz', 'Datenschutzerklärung', '<h2>Datenschutzerklärung</h2><h3>1. Datenschutz auf einen Blick</h3><p><strong>Allgemeine Hinweise:</strong> Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen.</p><h3>2. Allgemeine Hinweise und Pflichtinformationen</h3><p><strong>Datenschutz:</strong> Die Betreiber dieser Seiten nehmen den Schutz Ihrer persönlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend der gesetzlichen Datenschutzvorschriften sowie dieser Datenschutzerklärung.</p><h3>3. Datenerfassung auf dieser Website</h3><p><strong>Wer ist verantwortlich für die Datenerfassung auf dieser Website?</strong><br>Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten können Sie dem Impressum dieser Website entnehmen.</p><p><strong>Wie erfassen wir Ihre Daten?</strong><br>Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen. Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst. Das sind vor allem technische Daten (z.B. Internetbrowser, Betriebssystem oder Uhrzeit des Seitenaufrufs).</p><h3>4. Hosting</h3><p>Wir hosten die Inhalte unserer Website bei folgendem Anbieter: [Hosting-Anbieter eintragen]</p>', 'default', 91, 1],
        ];

        $stmt = $db->prepare("INSERT INTO pages (slug, title, content, layout, menu_order, show_in_menu) VALUES (:slug, :title, :content, :layout, :order, :menu)");
        foreach ($seedPages as $p) {
            $stmt->bindValue(':slug', $p[0], SQLITE3_TEXT);
            $stmt->bindValue(':title', $p[1], SQLITE3_TEXT);
            $stmt->bindValue(':content', $p[2], SQLITE3_TEXT);
            $stmt->bindValue(':layout', $p[3], SQLITE3_TEXT);
            $stmt->bindValue(':order', $p[4], SQLITE3_INTEGER);
            $stmt->bindValue(':menu', $p[5], SQLITE3_INTEGER);
            $stmt->execute();
            $stmt->reset();
        }
    }
}

init_db();
