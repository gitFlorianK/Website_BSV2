# BSV 1960 Plauen e.V. – CMS-Website

Vereinswebsite mit integriertem Content-Management-System (CMS) auf Basis von PHP und SQLite.

## Features

- **Seitenverwaltung** – Seiten erstellen, bearbeiten, löschen mit WYSIWYG-Editor (Quill)
- **Layouts** – Standard, Hero, Zweispaltig, Volle Breite (pro Seite wählbar)
- **Hierarchisches Menü** – Unterseiten mit Dropdown-Navigation
- **Medienverwaltung** – Bildupload mit automatischer Skalierung auf max. 1920x1080px
- **Benutzerverwaltung** – Rollen: Administrator und Redakteur
- **Anpassbares Design** – Farben (Primär, Sekundär, Akzent, Hintergrund, Text) über Admin änderbar
- **Konfigurierbare Startseite** – Jede Seite kann als Startseite festgelegt werden
- **Rechtliche Seiten** – Impressum und Datenschutzerklärung vorinstalliert
- **Responsive** – Mobilfreundlich mit Hamburger-Menü

## Sicherheit

### Input-Validierung & Output-Encoding
- DOM-basierter HTML-Sanitizer (`DOMDocument`) mit Tag- und Attribut-Whitelist
- HTML-Entity-Dekodierung vor URL-Prüfung (verhindert `&#106;avascript:`-Bypasses)
- Blockierung gefährlicher Protokolle (`javascript:`, `data:`) in `href`/`src`
- Automatisches `rel="noopener noreferrer"` für externe Links
- HTML-Escaping aller Ausgaben mit `htmlspecialchars()`
- Prepared Statements für alle Datenbankabfragen (SQL-Injection-Schutz)

### Authentifizierung & Session
- Passwort-Hashing mit `password_hash()` (bcrypt)
- Zufällig generiertes Erstpasswort (12 Zeichen, wird nach Login gelöscht)
- Serverseitige Passwort-Mindestlänge (8 Zeichen)
- Brute-Force-Schutz: 5 Versuche, danach 5 Minuten Sperre
- Timing-sichere Prüfung (`password_verify` auch bei unbekanntem User)
- Session-Härtung: httponly, SameSite=Lax, secure (bei HTTPS), strict mode
- Session-Fixation-Schutz: ID-Regenerierung bei Erstellung und Login
- Sicherer Logout: Session-Daten, Cookie und Server-Session werden gelöscht

### CSRF-Schutz
- CSRF-Token auf allen Formularen und Lösch-Aktionen (POST)
- Token-Rotation nach jeder erfolgreichen Verwendung
- Stateless-Verifikation für AJAX-Uploads (via `X-CSRF-Token`-Header)
- Logout nur per POST mit CSRF-Token (verhindert CSRF-Logout)

### HTTP-Security-Headers
- `Content-Security-Policy` (Frontend: nur `'self'`, Admin: + jsdelivr.net für Quill)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Strict-Transport-Security` (automatisch bei HTTPS)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

### Upload-Sicherheit
- MIME-Typ-Validierung via `finfo` (nicht Dateiendung)
- Maximale Dateigröße: 10 MB
- Automatische Bildskalierung auf max. 1920x1080px (GD-Bibliothek)
- Zufällige Dateinamen (32 Hex-Zeichen)
- Re-Encoding durch GD entfernt eingebettete Metadaten/Payloads

### Sonstiges
- Geschütztes Datenverzeichnis via `.htaccess`
- Nur interne Redirects erlaubt (Open-Redirect-Schutz)
- Farbwerte werden bei Ein- und Ausgabe validiert (`#RRGGBB`)
- Slug-Eindeutigkeitsprüfung bei Seiten
- Benutzername-Eindeutigkeitsprüfung

## Voraussetzungen

- PHP 8.1+ mit DOM-Extension
- SQLite3-Extension
- GD-Extension (optional, für Bildskalierung)

## Installation

```bash
git clone git@github.com:gitFlorianK/Website_BSV2.git
cd Website_BSV2
php -S localhost:8080
```

Die Datenbank wird beim ersten Aufruf automatisch erstellt.

## Admin-Zugang

- URL: `/admin.php`
- Standardbenutzer: `admin`
- Passwort: wird zufällig generiert und in `data/initial_password.txt` gespeichert
- Die Passwort-Datei wird nach dem ersten Login automatisch gelöscht

## Rollen

| Rolle | Seiten | Medien | Benutzer | Einstellungen |
|-------|--------|--------|----------|---------------|
| **Administrator** | Erstellen, Bearbeiten, Löschen | Upload, Löschen | Verwalten | Ändern |
| **Redakteur** | Erstellen, Bearbeiten, Löschen | Upload, Löschen | – | – |

## Projektstruktur

```
├── admin.php              # CMS-Backend (Routing, CRUD, Auth)
├── index.php              # Frontend-Router
├── config.php             # Sicherheitsfunktionen, HTML-Sanitizer, CSRF
├── .htaccess              # URL-Rewriting & Dateischutz
├── includes/
│   ├── db.php             # Datenbank-Setup & Seed-Daten (SQLite)
│   ├── functions.php      # Menü, Bildverarbeitung, Brute-Force
│   ├── header.php         # Frontend-Header mit dynamischem Menü
│   └── footer.php         # Frontend-Footer
├── admin/includes/
│   ├── header.php         # Admin-Header mit Navigation
│   └── footer.php         # Admin-Footer mit Quill-Editor-Init
├── assets/css/
│   └── style.css          # Styling (Frontend + Admin, responsive)
├── data/                  # SQLite-Datenbank (nicht im Repo)
└── uploads/               # Hochgeladene Bilder (nicht im Repo)
```

## Technologien

- PHP 8 mit SQLite3 und DOMDocument
- Quill 2.0 Editor (WYSIWYG, via CDN)
- Vanilla HTML/CSS/JavaScript
- Apache mod_rewrite für Clean URLs
