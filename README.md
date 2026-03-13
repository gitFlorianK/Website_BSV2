# Bogensportverein – CMS-Website

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

- CSRF-Token-Schutz auf allen Formularen
- Prepared Statements (SQL-Injection-Schutz)
- Passwort-Hashing mit `password_hash()`
- Session-Härtung (httponly, samesite, strict mode)
- HTML-Sanitizing bei Seiteninhalt
- MIME-Typ-Validierung bei Uploads
- Geschütztes Datenverzeichnis via `.htaccess`

## Voraussetzungen

- PHP 8.1+
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
- Standardpasswort: `admin123`

**Passwort nach dem ersten Login ändern!**

## Projektstruktur

```
├── admin.php              # CMS-Backend
├── index.php              # Frontend-Router
├── config.php             # Konfiguration & Sicherheitsfunktionen
├── .htaccess              # URL-Rewriting & Dateischutz
├── includes/
│   ├── db.php             # Datenbank-Setup (SQLite)
│   ├── functions.php      # Hilfsfunktionen
│   ├── header.php         # Frontend-Header
│   └── footer.php         # Frontend-Footer
├── admin/includes/
│   ├── header.php         # Admin-Header
│   └── footer.php         # Admin-Footer mit Quill-Editor
├── assets/css/
│   └── style.css          # Styling (Frontend + Admin)
├── data/                  # SQLite-Datenbank (nicht im Repo)
└── uploads/               # Hochgeladene Bilder (nicht im Repo)
```

## Technologien

- PHP 8 mit SQLite3
- Quill Editor (WYSIWYG)
- Vanilla HTML/CSS/JavaScript
- Apache mod_rewrite für Clean URLs
