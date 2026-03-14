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
    // Nur einmal initialisieren (Lock-Datei als Marker)
    $lockFile = DATA_PATH . '/.initialized';
    if (file_exists($lockFile) && file_exists(DB_FILE)) return;

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

        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Standardbenutzer mit zufälligem Passwort
    $result = $db->querySingle("SELECT COUNT(*) FROM users");
    if ($result == 0) {
        $initialPassword = bin2hex(random_bytes(6)); // 12 Zeichen
        $hash = password_hash($initialPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES ('admin', :pw, 'admin')");
        $stmt->bindValue(':pw', $hash, SQLITE3_TEXT);
        $stmt->execute();

        // Passwort in temporäre Datei schreiben (einmalig lesbar)
        file_put_contents(DATA_PATH . '/initial_password.txt',
            "Admin-Zugangsdaten (bitte nach dem ersten Login loeschen!):\n" .
            "Benutzer: admin\n" .
            "Passwort: $initialPassword\n"
        );
    }

    // Standardeinstellungen
    $defaults = [
        'site_name' => 'BSV 1960 Plauen e.V.',
        'site_subtitle' => 'Bogensportverein 1960 Plauen e.V.',
        'footer_text' => '&copy; ' . date('Y') . ' Bogensportverein 1960 Plauen e.V.',
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
            ['startseite', 'Startseite', '<h2>Herzlich Willkommen</h2><h3>Unser Verein</h3><p>Unser Verein wurde am 18. Juni 1960 in Plauen gegründet. Hauptsächlich werden bei uns Recurvebögen (olympische) geschossen, aber auch die Langbogen, Jagdbögen sowie Compoundbögen haben schon ihre Liebhaber gefunden.</p><p>Je nach Leistungsstand motivieren wir unsere Schützen zur Teilnahme an Fita- oder Jagdturnieren. Auch haben wir erfahrene Trainer in unserem Verein, die ihr Wissen mit großer Begeisterung an unsere Anfänger weitergeben.</p><h3>Vorstand</h3><ul><li><strong>Vereinsvorsitzender:</strong> Florian Künzel<br>E-Mail: info@bogensport-plauen.de</li><li><strong>Stell. Vereinsvorsitzender:</strong> Ronny Krauß</li><li><strong>Schatzmeisterin:</strong> Anja Kus</li></ul>', 'hero', 1, 1],
            ['training', 'Training', '<h2>Trainingszeiten</h2><p>Wir trainieren regelmäßig und freuen uns über neue Gesichter. Sowohl Anfänger als auch Fortgeschrittene sind bei uns herzlich willkommen.</p><p>Hauptsächlich werden bei uns Recurvebögen in olympischer Ausführung geschossen. Aber auch Langbogen, Jagdbögen und Compoundbögen kommen bei uns zum Einsatz.</p><p>Unsere erfahrenen Trainer stehen euch bei Fragen gerne zur Verfügung und begleiten euch auf eurem Weg im Bogensport.</p><p><strong>Für aktuelle Trainingszeiten und -orte kontaktiert uns bitte direkt:</strong><br>E-Mail: info@bogensport-plauen.de</p>', 'default', 2, 1],
            ['anfaengerkurs', 'Anfängerkurs', '<h2>Anfängerkurs</h2><p>Du möchtest den Bogensport kennenlernen? Bei uns bist du genau richtig!</p><p>Unsere erfahrenen Trainer geben ihr Wissen mit großer Begeisterung an Anfänger weiter. Du benötigst keine eigene Ausrüstung – wir stellen dir alles Nötige zur Verfügung.</p><h3>Was erwartet dich?</h3><ul><li>Einführung in die Grundlagen des Bogenschießens</li><li>Sicherheitsunterweisung</li><li>Erlernen der richtigen Schusstechnik</li><li>Materialkunde (Recurve, Compound, Langbogen)</li><li>Jede Menge Spaß und nette Leute</li></ul><p><strong>Interesse?</strong> Schreib uns einfach eine E-Mail an info@bogensport-plauen.de oder komm zu unseren Trainingszeiten vorbei.</p>', 'default', 3, 1],
            ['information', 'Information', '<h2>Informationen zum Verein</h2><p>Der Bogensportverein 1960 Plauen e.V. wurde am 18. Juni 1960 gegründet und ist einer der traditionsreichsten Bogensportvereine in der Region.</p><h3>Unsere Disziplinen</h3><ul><li><strong>Recurvebogen</strong> – Unsere Hauptdisziplin, olympische Ausführung</li><li><strong>Compoundbogen</strong> – Für technikbegeisterte Schützen</li><li><strong>Langbogen</strong> – Traditionelles Bogenschießen</li><li><strong>Jagdbogen</strong> – Für den instinktiven Schuss</li></ul><h3>Turniere und Wettkämpfe</h3><p>Je nach Leistungsstand motivieren wir unsere Schützen zur Teilnahme an Fita- oder Jagdturnieren.</p><h3>Mitgliedschaft</h3><p>Du möchtest Mitglied werden? Kontaktiere uns unter info@bogensport-plauen.de – wir freuen uns auf dich!</p>', 'default', 4, 1],
            ['kontakt', 'Kontakt', '<h2>Kontakt</h2><p><strong>Bogensportverein 1960 Plauen e.V.</strong></p><p><strong>Vereinsvorsitzender:</strong> Florian Künzel</p><p><strong>E-Mail:</strong> info@bogensport-plauen.de</p><h3>Vorstand</h3><ul><li><strong>Vereinsvorsitzender:</strong> Florian Künzel</li><li><strong>Stell. Vereinsvorsitzender:</strong> Ronny Krauß</li><li><strong>Schatzmeisterin:</strong> Anja Kus</li></ul><p>Wir freuen uns über deine Nachricht!</p>', 'default', 5, 1],
            ['impressum', 'Impressum', '<h2>Impressum</h2><p><strong>Angaben gemäß § 5 TMG:</strong></p><p>Bogensportverein 1960 Plauen e.V.<br>Plauen</p><p><strong>Vertreten durch:</strong><br>Vereinsvorsitzender: Florian Künzel</p><p><strong>Kontakt:</strong><br>E-Mail: info@bogensport-plauen.de</p><p><strong>Registereintrag:</strong><br>Eingetragen im Vereinsregister.<br>Registergericht: Amtsgericht Plauen</p><p><strong>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV:</strong><br>Florian Künzel<br>Plauen</p>', 'default', 90, 1],
            ['datenschutz', 'Datenschutzerklärung', '<h2>Datenschutzerklärung</h2><h3>1. Datenschutz auf einen Blick</h3><p><strong>Allgemeine Hinweise:</strong> Die folgenden Hinweise geben einen einfachen Überblick darüber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen.</p><h3>2. Verantwortliche Stelle</h3><p>Bogensportverein 1960 Plauen e.V.<br>Vereinsvorsitzender: Florian Künzel<br>E-Mail: info@bogensport-plauen.de</p><h3>3. Datenerfassung auf dieser Website</h3><p><strong>Wie erfassen wir Ihre Daten?</strong><br>Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen. Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst. Das sind vor allem technische Daten (z.B. Internetbrowser, Betriebssystem oder Uhrzeit des Seitenaufrufs).</p><h3>4. Hosting</h3><p>Wir hosten die Inhalte unserer Website bei einem externen Dienstleister (Hoster).</p><h3>5. Ihre Rechte</h3><p>Sie haben jederzeit das Recht auf Auskunft, Berichtigung oder Löschung Ihrer gespeicherten personenbezogenen Daten.</p>', 'default', 91, 1],
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

    touch($lockFile);
}

init_db();
