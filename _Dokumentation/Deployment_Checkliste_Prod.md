# Deployment-Checkliste (Prod) – Kontaktformular

Diese kurze Checkliste führt dich **Ende-zu-Ende** durch ein sauberes Prod-Deployment (Hetzner/ähnlich).

---

## 0) Vorab
- [ ] **Backup** vom aktuellen Stand (Code + Webserver-Config).
- [ ] **Repo sauber**: keine Secrets, `vendor/` optional CI-basiert erzeugen.
- [ ] **Branch/Tag** für Release erstellt.

---

## 1) Server-Voraussetzungen
- [ ] PHP **8.2.x** mit `mbstring`, `openssl`, `json`, `filter`.
- [ ] Webserver: Nginx (**empfohlen**) vor Apache/PHP-FPM oder nur Apache.
- [ ] Git & Composer vorhanden (oder Deploy-Artifact mitliefern).

---

## 2) Code ausrollen
- [ ] Zielpfad z. B. `/var/www/project` (Docroot z. B. `/var/www/project/public` oder direkt Repo-Ordner).
- [ ] Dateien hochladen/auschecken (inkl. `assets`, `vendor` falls mitgeliefert).
- [ ] Dateirechte: Webserver-Benutzer liest **alles**, **keine** Schreibrechte im Codebaum nötig (außer Cache/Logs, falls vorhanden).

---

## 3) Composer/PHPMailer
- [ ] Im Projektroot (dort, wo `composer.json` liegt):
  ```bash
  composer install --no-dev --prefer-dist --optimize-autoloader
  ```
- [ ] `vendor/autoload.php` existiert und ist **vom Handler aus** erreichbar:
  - Handler: `assets/php/contact-php-handler.php`
  - Require: `require __DIR__ . '/../../vendor/autoload.php';`

---

## 4) ENV-Konfiguration (Variante B)
- [ ] `assets/php/.env` (Basis, ohne Secrets).
- [ ] **Prod-Overrides:** `assets/php/.env.prod` mit echten Werten:
  ```ini
  APP_ENV=prod
  SMTP_HOST=smtp.deine-domain.tld
  SMTP_PORT=587
  SMTP_SECURE=tls
  SMTP_USER=postfach@deine-domain.tld
  SMTP_PASS=••••••
  RECIPIENT_EMAIL=kontakt@deine-domain.tld
  NOREPLY_EMAIL=no-reply@deine-domain.tld
  SUBJECT_PREFIX=[Kontakt]
  DEV_FAKE_SEND=false
  ```
- [ ] `.env.prod` **nicht** ins Repo; nur auf dem Server.
- [ ] `.htaccess`/Webserver blockiert Zugriff auf `.env*` (siehe Punkt 6).

---

## 5) Webserver-Konfiguration
### Nginx (vor Apache/PHP-FPM)
- [ ] Statische Files direkt servieren.
- [ ] `.php` per `proxy_pass` (zu Apache) oder `fastcgi_pass` (PHP-FPM) weiterreichen.
- [ ] **Security-Header/CSP** zentral setzen (siehe 6).

### Apache (alleinstehend)
- [ ] `AllowOverride All` (falls `.htaccess` genutzt wird).
- [ ] `mod_rewrite`, `mod_headers` aktiv.

---

## 6) Security-Header / CSP
Prod-Empfehlung (Nginx `server {}` oder Apache `<VirtualHost>`/`.htaccess`):

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy:
  default-src 'self';
  script-src 'self';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: blob:;
  font-src 'self' data:;
  connect-src 'self';
  base-uri 'none';
  frame-ancestors 'none';
  form-action 'self'
```

> **Hinweis:** Stelle sicher, dass **keine** Inline-Skripte mehr nötig sind (Loader ausgelagert). Falls doch, nutze **Nonce/Hash** statt `'unsafe-inline'` bei `script-src`.

**.env-Schutz**
- [ ] Zugriff blockieren:
  - Nginx:
    ```nginx
    location ~* \.(env|env\.local|env\.prod)$ { deny all; }
    ```
  - Apache (`.htaccess` in `assets/php/`):
    ```apache
    <FilesMatch "^\.env(\..*)?$">
      Require all denied
    </FilesMatch>
    ```

---

## 7) PHP-Handler (Sanity)
- [ ] `GET assets/php/contact-php-handler.php` → **405** (erwartet).
- [ ] `POST` vom Formular → **200** + `{"success":true}` (nach Deployment testen).

---

## 8) E-Mail-Zustellbarkeit (Prod)
- [ ] **SPF**-Record enthält deinen SMTP-Sender (z. B. `include:spf.provider.tld`).
- [ ] **DKIM**-Key eingerichtet (DNS TXT) und beim Provider aktiviert.
- [ ] **DMARC**-Policy (z. B. `v=DMARC1; p=quarantine; rua=mailto:dmarc@deine-domain.tld`).
- [ ] **Absender-Domain**: `NOREPLY_EMAIL` und SMTP-Login **gleiche** Domain (Alignment).
- [ ] Test an externe Provider (Gmail/Outlook) und Spamfolder prüfen.

---

## 9) Smoke-Tests nach Go-Live
- [ ] Startseite lädt ohne CSP-Verstöße (Browser-Console).
- [ ] Kontaktformular lädt on-demand (Snippet + `initContactForm()`).
- [ ] Absenden → **200** + Erfolgsmeldung.
- [ ] E-Mail wird zugestellt (Prod-SMTP).
- [ ] Logs sauber (keine PHP Notices/Warnings).

---

## 10) Monitoring & Rollback
- [ ] Webserver-Access/Error-Logs im Blick (Fail2ban/Watchtower optional).
- [ ] Backup/Release-Tag verfügbar für schnelles **Rollback**.
- [ ] Optional: einfache Uptime-Checks/Health-URLs.

---

## 11) Unterschiede Dev ↔ Prod (Merkzettel)
- Dev: MailHog (1025, ohne TLS/Auth), CSP toleranter (während Umbau).
- Prod: echter SMTP (587 + TLS), CSP hart (`script-src 'self'`), ENV aus `.env.prod`.
