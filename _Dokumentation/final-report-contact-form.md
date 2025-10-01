# Abschlussbericht: Kontaktformular www.jozapf.de (Version test11.1)

## Inhaltsverzeichnis
1. [Zielvereinbarung (technisch präzise)](#zielvereinbarung-technisch-präzise)
2. [Umsetzung & aktueller Stand](#umsetzung--aktueller-stand)
3. [Probleme, Ursachen & Maßnahmen](#probleme-ursachen--maßnahmen)
4. [Didaktische Doku: Funktionsweise DEV vs. PROD](#didaktische-doku-funktionsweise-dev-vs-prod)
5. [Dateistruktur & relevante Dateien](#dateistruktur--relevante-dateien)
6. [Konfiguration (.env) – Beispiele](#konfiguration-env--beispiele)
7. [Sicherheitsaspekte](#sicherheitsaspekte)
8. [Troubleshooting-Checkliste](#troubleshooting-checkliste)
9. [To‑Dos / Optionen für später](#to-dos--optionen-für-später)
10. [Changelog (Kurzverlauf)](#changelog-kurzverlauf)

---

## 1) Zielvereinbarung (technisch präzise)

**Ziel:** Ein robustes, DMARC/SPF-konformes Kontaktformular für _www.jozapf.de_ (Pfad `test11.1`) mit stabiler Zustellung an das Admin-Postfach und optionaler Bestätigungsmail an den Absender. Das System soll sowohl **in DEV** (lokal/Docker) als auch **in PROD** (Hetzner Shared Hosting) reproduzierbar funktionieren.

### Muss-Kriterien
- **Frontend**
  - Einbettbarer **Form-Wrapper** (`contact-form-wrapper.html`) mit Feldern: `firstName`, `lastName`, `email`, `phone`, `subject`, `message`, `privacy`, `captchaAnswer`.
  - Ein **JS-Loader** (`contact-form-logic.js`), der das Snippet lädt, initialisiert (inkl. Security-Question) und die Submission via `fetch` an den Handler ausführt.
  - UI-Feedback (Erfolg/Fehler) basierend auf JSON-Antworten.
- **Backend**
  - **PHPMailer**-basierter Handler `assets/php/contact-php-handler.php` mit stabilen JSON-Responses (kein HTML‑Output, kein 500 im Erfolgsfall).
  - **SMTP-Versand** über Hetzner:
    - Host `mail.your-server.de`
    - Port 465 (SSL) oder 587 (STARTTLS), Auth mit `SMTP_USER=mail@jozapf.de`.
    - **DMARC-freundliche Absender-Politik**: Header-From = authentifizierte Mailbox; Envelope-From = `SMTP_USER` (Bounces).
  - **Optionale User-Bestätigung** (abschaltbar per ENV).
  - **.env**-basiertes Setup: `.env.local` (DEV) hat Vorrang, sonst `.env.prod` (PROD).
  - **Logging** (eigener Ordner) + EML-Mitschnitt (forensisch).
  - **Diagnosemodus** per `__diag=1` (POST) ohne Versand.
- **Build/Dependencies**
  - `composer.json` für `phpmailer/phpmailer:^6.9`, sauberer Autoloader (`vendor/autoload.php`).

---

## 2) Umsetzung & aktueller Stand

### Frontend
- **`contact-form-wrapper.html`**
  - Enthält vollständige Formularstruktur inkl. Datenschutz-Checkbox und **Security Question** (Captcha-Light).
  - Verankert mit Anker `#contact-form-anchor` fürs Scrollen aus dem Menü.

- **`contact-form-logic.js`**
  - Modul lädt den Wrapper on demand, bindet Event-Handler, erzeugt die Security-Question, validiert Minimalfelder.
  - Sendet `multipart/form-data` an den Handler, erwartet **JSON**.
  - Handhabt UI-Zustände: Ladeindikator, Erfolge, Feldfehler (422).

### Backend
- **`assets/php/contact-php-handler.php`**
  - **Eigener .env-Lader**: liest `.env.local` (wenn vorhanden) sonst `.env.prod`.
  - **Valider JSON-Output** in allen Codepfaden: `json_ok(...)` / `json_error(...)` (Status 2xx/4xx/5xx).
  - **PHPMailer-Konfiguration**:
    - `SMTPSecure`: automatische Wahl anhand Port (587→STARTTLS, 465→SMTPS), overridebar via `SMTP_SECURE`.
    - `SMTPAuth`: nur wenn `SMTP_USER` gesetzt.
    - **Header-From** = `SMTP_USER` (falls vorhanden) oder `NOREPLY_EMAIL`; **Reply-To** setzt bevorzugt die User-Mail (falls valide), ansonsten `NOREPLY_EMAIL`.
    - **Envelope-From / Return-Path** = `SMTP_USER` → Bounces und DMARC/SPF sauber.
  - **Admin-Mail**: HTML-Body inkl. Tabelle aller Felder + Plaintext-AltBody.
  - **User-Bestätigung (neu)**:
    - Steuerbar via `USER_CONFIRM_ENABLE` (default `true`).
    - `From` = `SMTP_USER`, `Reply-To` = `NOREPLY_EMAIL`.
    - Versand **erst nach** erfolgreichem Admin-Versand.
  - **Logs**: `assets/php/logs/` (wird bei Bedarf erstellt)
    - `debug.log` (SMTP-Debug in Nicht-Prod)
    - `php-errors.log` (PHP-Fehler via `set_error_handler`)
    - `sent-eml/*.eml` (EML-Mitschnitte via `preSend()`)
  - **Diagnose (`__diag=1`)** gibt u. a. ENV-Quelle, SMTP-Setup, Pfad `vendor/autoload.php`, Log-Schreibrechte aus.

### Server/Hosting
- **Hetzner**: Funktioniert in PROD mit `.env.prod` und `.htaccess`-Override `SetEnv APP_ENV prod`.
- **Composer/Autoload**: `vendor/` liegt im Projekt-Root (`test11.1/vendor`).

---

## 3) Probleme, Ursachen & Maßnahmen

### A) 500 Internal Server Error (mehrfach)
- **Ursachen:**
  - Fehlender Autoloader (`vendor/autoload.php` nicht vorhanden oder BOM-Problem in `composer.json`).
  - Früher HTML/Fehlerausgabe statt JSON (führte zu `500` im Frontend).
  - Schreibrechte/Existenz von `logs/`.
- **Maßnahmen:**
  - `composer.json` ohne BOM neu erzeugt; `composer install` (Docker/WSL) durchgeführt → `vendor/` erstellt.
  - Handler kapselt **alle** Fehler in `json_error(...)` und schreibt ins Log.
  - `logs/` wird mit `@mkdir(..., true)` erzeugt; Error-Handler schreibt nach `php-errors.log`.

### B) ENV-Mixups (DEV vs PROD)
- **Ursachen:**
  - `.env.local` hatte Priorität und zeigte auf `mailhog:1025` → PROD scheiterte.
- **Maßnahmen:**
  - Klar definierte ENV-Priorität: **.env.local > .env.prod**.
  - In PROD `.htaccess` (oder vHost) `SetEnv APP_ENV prod` zur eindeutigen Signalisierung.
  - Diagnose-Endpoint (`__diag=1`) zur Live-Prüfung von geladenen Werten.

### C) Security Question „verschwunden“
- **Ursachen:**
  - Modul-Ladefehler („`initContactForm not exported`“) durch falschen Import/Bundle/Relative-Pfade.
- **Maßnahmen:**
  - `contact-form-logic.js` als **ES Module** korrekt exportiert (`export function initContactForm(){...}`) und dynamisch importiert.
  - Relativpfade vereinheitlicht; Loader-Script angepasst.

### D) SMTP-Verbindung/Authentifizierung
- **Ursachen:**
  - DEV-Host `mailhog` nicht auflösbar in PROD; falsche `SECURE`/Port-Kombination.
- **Maßnahmen:**
  - Diagnose-Block (DNS-Auflösung + TCP-Connect) in früheren Iterationen → heute konsolidiert auf PHPMailer-Debug, Ports 465/587 Logik, klare ENV-Doku.
  - **Absender-Politik** DMARC-konform (Header-From=SMTP_USER, Sender=SMTP_USER).

### E) Backscatter/DMARC bei User-Bestätigung
- **Ursachen:**
  - Bestätigungsmails mit `From` der User-Adresse sind DMARC-gefährdet.
- **Maßnahmen:**
  - Bestätigungsmail **immer** mit `From = SMTP_USER`, Reply-To `NOREPLY_EMAIL` (oder None), Versand nur bei valider User-Mail.

---

## 4) Didaktische Doku: Funktionsweise DEV vs. PROD

### DEV (lokal/Docker)
- **ENV:** `.env.local`
  - Beispiel: `APP_ENV=dev`, `SMTP_HOST=mailhog`, `SMTP_PORT=1025`, `DEV_FAKE_SEND=true/false`.
- **Zweck:** Schnelles Testen ohne echte Zustellung. Optional Fake-Send, SMTP-Debug aktiv.
- **E-Mail:** Mailhog fängt Mails ab, kein externer Versand.
- **Debug:** In Nicht-PROD wird `SMTPDebug` in `debug.log` geschrieben.

### PROD (Hetzner)
- **ENV:** `.env.prod` (und/oder `SetEnv APP_ENV prod` in `.htaccess`)
  - Beispiel: `SMTP_HOST=mail.your-server.de`, `SMTP_PORT=587`, `SMTP_SECURE=tls`, `SMTP_USER=mail@jozapf.de`, `SMTP_PASS=***`.
- **E-Mail:** Authentifizierter Versand über Hetzner, DMARC/SPF-konform.
- **Debug:** `SMTPDebug` ist standardmäßig AUS (nur Warn-/Fehlerlogs).

### Ablauf (Request→Response)
1. JS lädt Formular → User füllt aus → JS sendet POST (multipart/form-data).
2. Handler:
   - Lädt `.env`, validiert Felder, baut Admin-Mail.
   - Konfiguriert PHPMailer (SMTP).
   - Sendet Admin-Mail (oder Fake im DEV).
   - Bei Erfolg optional **Bestätigungsmail** an User.
   - Antwortet **JSON** `{ ok:true, ... }`.
3. JS zeigt Status (grün/rot), setzt ggf. Formular zurück.

---

## 5) Dateistruktur & relevante Dateien

```
test11.1/
├─ index.html                         # lädt das Formular bei Klick/Hash (#contact)
├─ assets/
│  ├─ html/
│  │  └─ contact-form-wrapper.html    # kompletter Form-Block inkl. Security Question
│  ├─ js/
│  │  └─ contact-form-logic.js        # Modul: init, validate, submit, UI
│  └─ php/
│     ├─ contact-php-handler.php      # PHPMailer-Handler (JSON, SMTP, Logs, EML)
│     └─ logs/
│        ├─ debug.log
│        ├─ php-errors.log
│        └─ sent-eml/*.eml
├─ vendor/                            # Composer-Abhängigkeiten
├─ composer.json
├─ .env.local (DEV) / .env.prod (PROD)
└─ .htaccess (optional: SetEnv APP_ENV prod)
```

---

## 6) Konfiguration (.env) – Beispiele

### PROD (Hetzner)
```env
APP_ENV=prod
DEV_FAKE_SEND=false

RECIPIENT_EMAIL=mail@jozapf.de
NOREPLY_EMAIL=noreply@jozapf.de
SUBJECT_PREFIX=Kontakt via www.jozapf.de: 

SMTP_HOST=mail.your-server.de
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=mail@jozapf.de
SMTP_PASS=***

# Optional: User-Bestätigung
USER_CONFIRM_ENABLE=true
USER_CONFIRM_SUBJECT=Eingang Ihrer Nachricht bei www.jozapf.de
USER_CONFIRM_GREETING=Vielen Dank für Ihre Nachricht! Wir melden uns zeitnah.
```

### DEV (lokal/Docker, Mailhog)
```env
APP_ENV=dev
DEV_FAKE_SEND=false

RECIPIENT_EMAIL=mail@jozapf.de
NOREPLY_EMAIL=noreply@example.test

SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_SECURE=

# Optional: User-Bestätigung in DEV
USER_CONFIRM_ENABLE=false
```

---

## 7) Sicherheitsaspekte

- **DMARC/SPF-konform:** `From`/`Sender` = authentifizierte Domain (`SMTP_USER`), kein „Senden im Namen des Users“.
- **Reply-To-Policy:** User-Mail nur als **Reply-To**, niemals als Header-From.
- **Input-Sanitizing:** Zeilenumbrüche entfernt, HTML ge-escaped, Plaintext-AltBody generiert.
- **CORS/Headers:** JSON-only, `Access-Control-Allow-*` restriktiv für POST/OPTIONS.
- **.env-Schutz:** `.env`-Dateien via Server-Konfig gesperrt (und im Code nicht ausgeliefert).
- **Logs:** Nur Server-intern; EML-Mitschnitte enthalten personenbezogene Daten → Zugriff beschränken.

---

## 8) Troubleshooting-Checkliste

1. **`/vendor/autoload.php` vorhanden?**  
   → Sonst `composer install` im Projekt-Root ausführen.
2. **Logs beschreibbar?** (`assets/php/logs/`)  
   → Rechte prüfen; Handler legt Ordner an.
3. **`.env.local` vs `.env.prod`**  
   → In PROD sicherstellen, dass nicht `.env.local` mit DEV-Daten greift.
4. **SMTP-Port & SECURE**
   - Port 587 → `SMTP_SECURE=tls`
   - Port 465 → `SMTP_SECURE=ssl`
5. **Diagnose:** POST mit `__diag=1` → prüft ENV, Pfade, SMTP-Flags.
6. **Kein Eingang?**  
   - Hetzner Webmail/Spam prüfen.  
   - `debug.log`/`php-errors.log`/`sent-eml/*.eml` ansehen.
7. **Security Question fehlt?**  
   - Browser-Konsole auf Ladefehler achten (`initContactForm not exported`).  
   - Prüfen, ob `contact-form-logic.js` als **Module** geladen und exportiert ist.

---

## 9) To‑Dos / Optionen für später

- **Rate-Limiting / Bot-Schutz** (z. B. per simple Token/Timer oder hCaptcha/Turnstile).
- **Server-Side Captcha-Validierung** (Security Question serverseitig prüfen).
- **Queue/Retry** bei temporären SMTP-Fehlern.
- **i18n** für Fehlermeldungen / Mails.
- **Access-Control** (CORS enger fassen, wenn Domain fix ist).

---

## 10) Changelog (Kurzverlauf)

- **Form & Logic stabilisiert** (ESM, Security Question, Scroll/Anchor).  
- **Handler komplett refactored:** ENV-Lader, JSON-Responses, Logging, DMARC-konformer Versand.
- **SMTP/Hetzner verifiziert** (465/587, TLS/SSL), Envelope-From gesetzt.  
- **Bestätigungsmail an User** **wieder** eingeführt, DMARC-safe, per ENV schaltbar.  
- **Diagnosemodus** `__diag=1` für Live-Prüfungen.

---

**Stand:** Produktivsystem sendet zuverlässig an Admin; optionale User-Bestätigung aktivierbar.  
**Empfehlung:** `.env.prod` pflegen, `logs/` regelmäßig sichten/rotieren, später Bot‑Schutz ergänzen.
