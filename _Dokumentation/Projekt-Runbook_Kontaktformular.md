# Kontaktformular – Projekt-Runbook (Kurz & logisch)

## 1. Zielbild
- **Kontaktformular** wird **on demand** bei Klick geladen (kein initialer Payload).
- **Saubere Kapselung:** ES-Module, keine Globals. Loader importiert `initContactForm()`.
- **Sicher & robust:** serverseitiger PHP-Handler, ENV-gesteuertes SMTP (PHPMailer), Schutz der `.env`.
- **Deploy-fähig:** Dev in Docker (Nginx+Apache+MailHog), Prod bei Hetzner (PHP 8.2.x).

## 2. Struktur & Pfade
- Frontend: `assets/html/contact-form-wrapper.html`, `assets/js/contact-form-logic.js`, optional `assets/js/contact-form-loader.js`.
- Backend: `assets/php/contact-php-handler.php`.
- Composer/PHPMailer: `vendor/` unter **`/var/www/html/vendor`** (Host: `./vendor`), Autoloader im Handler:
  `require __DIR__ . '/../../vendor/autoload.php';`

## 3. Loader/Logic (Frontend)
- **Loader (type=module)** lädt Snippet + importiert Logic **lazy**.
- Pfade stabil via `new URL('./…', location.href)`.  
- Responsiv via bestehendem CSS (Bootstrap & eigene Styles).
- A11y: Erfolg/Fehlercontainer mit `role="status"/"alert"` (ToDo: Feinschliff).

## 4. Server/Container (Dev)
- `examples` (Nginx, Port **8080**) → statische Files + **Proxy für .php** zu `php-examples:80`.
- `php-examples` (Apache/PHP 8.2, Port **8081**), Docroot: `/var/www/html` (gemountet).
- `mailhog` (SMTP: **1025**, UI **8025**).
- Wichtige Volumes:
  - `./examples/bootstrap-5.3.8-examples:/var/www/html:rw`
  - `./vendor:/var/www/html/vendor:rw`

## 5. ENV/Lifecycle
- **Variante B aktiv** (layered):
  - `assets/php/.env` (Basis, ohne Secrets)
  - `assets/php/.env.local` (Dev)
  - `assets/php/.env.prod` (Prod)
- Robuster **.env-Loader** (eigene Parser-Funktion, keine Arrays).  
- `envv(key, default)` liest: Prozess-ENV → `$_ENV` → Default.

## 6. Security
- `.htaccess` in `assets/php/` schützt `.env*`.
- Nginx Security-Header/CSP (Dev derzeit mit `'unsafe-inline'` für Scripts; langfristig Nonces/extern).

## 7. Mail/PHPMailer
- PHPMailer 6.9 via Composer installiert, `vendor/autoload.php` eingebunden.
- Dev: MailHog **ohne Auth & ohne TLS**, Port 1025.
- Prod: echter SMTP, SPF/DKIM/DMARC beachten.

## 8. Tests
- Direktaufrufe: Snippet/JS 200, Handler GET → 405.
- Absenden (Dev): POST → JSON `{success:true}`, Mail in MailHog-UI.

## 9. Bisher erledigt ✅
- ES-Module-Setup (Loader/Logic), Pfade gefixt, Responsivität Basis.
- PHP-Handler (POST-only, Sanitizing, CR/LF-Schutz, JSON-Responses).
- Docker-Stack lauffähig; Nginx→Apache Proxy konfiguriert.
- Composer/PHPMailer installiert; **Autoloader OK**.
- **Robuster .env-Loader** implementiert (Zeilenparser).
- MailHog-Auflösung/Konnektivität geprüft.

## 10. Offene Punkte ▶️
- **CSP härtung**: Inline-Scripts eliminieren (Loader in Datei); `script-src 'self'` ohne `'unsafe-inline'`.
- **Frontend Fehler-Logging**: `console.error` beim POST (Status+Body) dauerhaft.
- **Serverseitige Anti-Abuse** (optional): Rate-Limit (IP/10 min), Honeypot/Time-Trap.
- **A11y-Feinschliff** im Formular.
- **Prod-Deliverability**: SPF/DKIM/DMARC für Domain; From/Envelope-Alignment.
- **Dokumentation/Backup**: Snapshot ohne Secrets.
