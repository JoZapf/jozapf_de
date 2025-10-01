# Status: Kontaktformular – Umsetzung & aktueller Stand

## 1) Ziel / Plan
- **Robuster PHP-Handler** für das Kontaktformular mit sauberer JSON-API.
- **ENV-basierte Konfiguration** (dev/prod; SMTP-Handover MailHog ↔ Hetzner).
- **Sicherheit & Zustellbarkeit**: Validierung, Captcha, DMARC/SPF-freundliche Header, keine Datenlecks.
- **Nachvollziehbarkeit**: Debug-Log & .EML-Mitschnitt zum Troubleshooting.

## 2) Stand jetzt
- **Handler vollständig aktualisiert** (`assets/php/contact-php-handler.php`), _ohne BOM_.
- **Composer / PHPMailer** installiert; `vendor/autoload.php` wird geladen.
- **Frontend-Logic** (module import) & **Form-Wrapper** repariert; Security-Question wieder sichtbar.
- **Prod-Versand (Hetzner)** erfolgreich getestet (SMTP Diagnose: OK).
- **Dev/Prod** kann per `APP_ENV` umgeschaltet werden.

## 3) Umsetzung im Code (Kernpunkte)
- **ENV-Loader**: `.env` + `.env.prod` (prod) / `.env.local` (dev); echte Prozess-ENV > Dateiwerte.
- **Felder aus Frontend**: `firstName`, `lastName`, `email`, `phone`, `subject`, `message`, `privacy`, `captchaAnswer|captcha_answer`.
- **Validierung**: Pflichtfelder Name/Message/Privacy, E-Mail-Format, Captcha-Integer.
- **CORS / JSON**: `Content-Type: application/json`, OPTIONS-Preflight, einheitliches `json_ok/json_fail`.
- **SMTP-Routing**:
  - **dev** (MailHog): `mailhog:1025`, **ohne** TLS/SSL, `SMTPDebug=2` ins Log.
  - **prod** (Hetzner): `mail.your-server.de:587 tls` oder `:465 ssl`, mit Auth.
- **DMARC-freundlich**:
  - `setFrom()` = **authentifizierte Mailbox** (i.d.R. `SMTP_USER`), `addReplyTo()` = Nutzer-Mail oder `NOREPLY_EMAIL`.
  - `Sender` (Envelope-From) = `SMTP_USER`.
- **Transparenz / Forensik**:
  - Debug-Log: `assets/php/logs/mail-debug.log`.
  - **.EML-Mitschnitt** vor dem `send()` in `assets/php/logs/sent-eml/`.
- **Fehlerhandling**: 422 bei Validierung, 405 bei Nicht-POST, 500 bei Laufzeitfehlern.

## 4) Wichtige Fehler & Fixes
- **BOM in composer.json** → JSON ungültig → _Neu ohne BOM geschrieben_.
- **Autoloader fehlte** → `vendor/autoload.php` mit **Composer (Docker)** erzeugt.
- **„Array to string“ bei ENV** → eigener `.env`-Parser ohne Array-Syntax.
- **„Headers already sent“** → BOM/Byte-Order-Mark & Header-Ausgabe korrigiert.
- **„Invalid address (From)“** → Absenderpolitik angepasst (`setFrom = SMTP_USER`, `Reply-To = user/NOREPLY`).
- **Dev Tools 405/422/500**:
  - 405: Nicht-POST → behoben.
  - 422: Feldnamen/Validierung → JS & Handler harmonisiert.
  - 500: fehlende Vendor/ENV/SMTP → behoben.
- **Security Question fehlte** → `contact-form-logic.js` repariert (`initContactForm` Export, CAPTCHA-Render).
- **503 auf Prod** → nach Deploy-Fixes (Composer/Vendor/ENV) erledigt.

## 5) Nächste Schritte
- **Prod-Hardening**: `APP_ENV=prod`, echte SMTP-Credentials, Logs beobachten.
- **SPF/DKIM/DMARC** prüfen/setzen (DNS).
- **Rate-Limiting / CSRF / Honeypot** (optional ausbauen).
- **Bounce-Handling**: Mailbox für `Sender` prüfen.
- **Monitoring**: Logrotation für `assets/php/logs/`.
