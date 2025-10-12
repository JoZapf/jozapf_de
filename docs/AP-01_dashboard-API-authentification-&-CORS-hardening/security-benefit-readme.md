
# Security & Privacy Benefits — Contact Form + Dashboard

Dieses Dokument liefert eine **sicherheitsfokussierte Übersicht** über die bereitgestellten Komponenten sowie konkrete **Hardening- und Betriebs-Empfehlungen**. Ziel ist es, die Vorteile der implementierten Schutzmechanismen transparent zu machen und sicher zu betreiben.

Betroffene Dateien (Kernbestandteile):
- `contact-php-handler.php` — Mailversand, Validierung, Server-seitiges Captcha, Logging
- `ContactFormValidator-v2.php` — Validierungs- und Heuristik-Layer (Spam-Score, Honeypot, Timestamps, Domain-Blacklist, Block-/Whitelist)
- `BlocklistManager.php` — IP-Block-/Whitelist inkl. Ablaufdaten und Subnetz-Unterstützung
- `ExtendedLogger.php` — DSGVO-orientiertes Extended Logging, Auto-Anonymisierung
- `dashboard.php`, `dashboard-login.php`, `dashboard-blocklist.php` — Admin-Dashboard (HMAC-Token), Blocklisten-Verwaltung (PRG-Pattern)
- `dashboard-api.php` — API für Dashboard-Statistiken
- `assets/js/contact-form-logic.js` — Client-Logik inkl. Captcha-UI, robustes Fehlermanagement

---

## 1) Problemdefinition
Kontaktformulare sind ein primäres Einfallstor für **Spam, Missbrauch und Injektionsangriffe**. Zusätzlich sind **Protokollierung** und **Betriebs-Transparenz** notwendig, ohne die **DSGVO** zu verletzen. Administrationsoberflächen benötigen **starken Zugriffsschutz** und robuste **Fehler-/Missbrauchsresistenz**.

---

## 2) Faktenbasierte Antwort (Sicherheitsmerkmale & Nutzen)

### 2.1 Eingabevalidierung & Spam-Abwehr
- **Mehrstufige Validierung:** `ContactFormValidator-v2.php` prüft Pflichtfelder, E-Mail-Format, Nachrichtinhalte.
- **Honeypot:** Automatische Erhöhung des Spam-Scores, wenn Bot-Fallen-Felder befüllt werden.
- **Zeitbasierte Heuristik:** Formular-Timestamps (z. B. „zu schnelle Eingabe“) fließen in den Spam-Score ein.
- **Domain-Blacklist:** Konfigurierbare Liste unerwünschter Absender-Domains reduziert Bot- und Wegwerf-Mail-Traffic.
- **Server-seitiges Captcha:** In `contact-php-handler.php` wird die Aufgabe **unabhängig von der UI** verifiziert (kein „nur Client“).

**Vorteil:** Senkt Spam signifikant, reduziert Ressourcenverbrauch (Mail-Queue/Rate-Limits), verhindert missbräuchliche Nutzung und False Positives werden durch nachvollziehbare Gründe minimiert.

### 2.2 IP-Block-/Whitelist (inkl. Subnetze)
- **Blocklist/Whitelist** über `BlocklistManager.php` (JSON-Backends mit Notizen & Ablauf).
- **Subnetz-Unterstützung (CIDR):** gezieltes Sperren ganzer Netze (z. B. `192.0.2.0/24`) bei Angriffswellen.
- **Whitelist** für bekannte Partner/Redaktionen (Bypass legitimer Anfragen trotz strenger Heuristik).

**Vorteil:** Hohe Wirksamkeit gegen wiederkehrende Angreifer/Botnetze, kontrollierte Ausnahmen für vertrauenswürdige Quellen.

### 2.3 Protokollierung mit DSGVO-Fokus
- **Extended Logging (`ExtendedLogger.php`):** zeichnet Metadaten (Zeitpunkt, User-Agent, Fingerprint, Validierungsdetails, Blockgründe) auf.
- **Aufbewahrung & Auto-Anonymisierung:** Vollständige IPs werden nach **14 Tagen automatisch anonymisiert** (IPv4/IPv6), ältere anonymisierte Datensätze können rotiert/gelöscht werden.
- **Audit-Trail:** Anonymisierungsaktionen werden protokolliert (Transparenz, Nachweis der Datenminimierung).

**Vorteil:** Erfüllung der **Rechenschaftspflicht** (Art. 5, 6 DSGVO – berechtigtes Interesse: Missbrauchsprävention) bei gleichzeitiger **Datenminimierung** (IP-Verkürzung nach Ablauf).

### 2.4 Geschütztes Admin-Dashboard (HMAC-Token, Cookie Flags)
- **Login-Flow (`dashboard-login.php`):** Vergabe eines **HMAC-signierten Tokens** mit Ablaufzeit.
- **Cookie-Härtung:** `Secure`, `HttpOnly`, `SameSite=Strict`, enger `path`.
- **Token-Validierung:** `verifyToken()` prüft HMAC-Signatur und Ablauf; fehlende/ungültige Tokens werden abgewiesen.
- **PRG-Pattern:** `dashboard.php` nutzt Post/Redirect/Get – verhindert unbeabsichtigte Doppel-Submits, verbessert UX.

**Vorteil:** Schutz vor Session Theft/XSS-Cookie-Diebstahl (HttpOnly) und CSRF-Risiko-Reduktion (SameSite=Strict), plus klare Trennung von Auth und UI.

### 2.5 E-Mail-Versand über PHPMailer (SMTP, .env)
- **Konfiguration über `.env.prod`** (`SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `SMTP_PORT`, `RECIPIENT_EMAIL`).
- **Fehlerrobust:** Handler protokolliert Konfig-Fehler (z. B. fehlende SMTP-Creds) statt „silent fail“.
- **Sanitization:** Eingaben werden vor dem Versand bereinigt; sensible Felder werden im Log verkürzt/gekürzt.

**Vorteil:** Nachvollziehbarer, sicherer Versandkanal; Trennung von Code und Geheimnissen gemäß Best Practices.

### 2.6 Frontend-Härtung (Kontaktformular-Logik)
- **Server-First-Validierung:** Client-Checks dienen UX; maßgeblich ist die **Server**-Prüfung.
- **Captcha-Regeneration:** Bei Fehlern wird ein neues Rätsel generiert; verstecktes Feld für Serverabgleich.
- **Gutes Fehlermanagement:** Saubere UI-Zustände (success/error), kein Leaken von internen Details.

**Vorteil:** Angreifer können clientseitige Checks nicht „umgehen“, ohne serverseitig zu scheitern.

---

## 3) Optionale Annahmen (klar gekennzeichnet)
- Die Produktion nutzt **HTTPS** (erforderlich für `Secure`-Cookies und Transportverschlüsselung).
- Verzeichnisstruktur vorhanden: `logs/`, `data/`, `assets/js/`, `assets/php/`.
- `.env.prod` liegt **außerhalb** des Webroots oder ist durch Webserver-Regeln vor direktem Download geschützt.
- PHPMailer ist per Composer installiert (oder vendor-Bundle vorhanden).

---

## 4) Empfohlene Schritte (Hardening, Betrieb, Compliance)

### 4.1 Konfiguration (.env.prod)
Beispiel-Variablen:
```
RECIPIENT_EMAIL="mail@example.org"
SMTP_HOST="smtp.example.org"
SMTP_PORT="587"
SMTP_USER="smtp-user"
SMTP_PASS="***"
SMTP_SECURE="tls"

DASHBOARD_SECRET="min. 32 zufällige Bytes Base64"
DASHBOARD_PASSWORD="starkes-Admin-Passwort"
```
**Empfehlungen:**
- Secrets mit **Mindestsicherheitsniveau** (>= 128 Bit Entropie) generieren; Rotation dokumentieren.
- Dateirechte restriktiv setzen (z. B. `600` für `.env.prod`).

### 4.2 Webserver-Header & Transport
- **HTTPS erzwingen**, HSTS aktivieren (z. B. `max-age=15552000; includeSubDomains; preload`).
- **Security Header:** `Content-Security-Policy` (mindestens `default-src 'self'`), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` bzw. `frame-ancestors 'none'`.
- **Rate Limiting** (Webserver/Reverse Proxy): z. B. 10 req/Min/IP auf `/assets/php/contact-php-handler.php` und Dashboard-Routen.
- **CORS prüfen:** `dashboard-api.php` setzt aktuell `Access-Control-Allow-Origin: *`. In Produktion auf die eigene Origin einschränken.

### 4.3 CSRF & XSS
- **CSRF:** `SameSite=Strict` reduziert Risiko; für state-changing POSTs im Dashboard **zusätzliche CSRF-Token** einführen (Hidden-Token + Server-Check).
- **XSS:** Alle dynamischen Ausgaben in Dashboard-HTML **HTML-escapen** (besonders IP/Notizen/Gründe). CSP hilft zusätzlich.
- **JSON-Ausgaben**: `Content-Type: application/json` ist korrekt; bei Einbettung in HTML auf Kontext-escape achten.

### 4.4 Logging & Aufbewahrung
- **Speicherfristen dokumentieren** (14 Tage Voll-IP, danach anonymisiert; definieren, wann anonymisierte Datensätze gelöscht werden).
- **Logrotation** (Dateigröße/Alter) und **Monitoring** (z. B. Failuren im Mailversand, Ausreißer im Spam-Score).
- **Betroffenenrechte** (Art. 15–17 DSGVO): Prozesse definieren (Auskunft/Löschung), Referenz auf das Anonymisierungskonzept im Verzeichnis von Verarbeitungstätigkeiten.

### 4.5 Block-/Whitelist-Betrieb
- **Subnetz-Sperren** sparsam und zeitlich begrenzt (Ablaufdatum pflegen).
- **Whitelists** regelmäßig überprüfen (Least Privilege, kein „Set-and-Forget“).
- **Änderungen auditierbar** halten (Notizen/Gründe pflegen).

### 4.6 Build & Deployment
- **Composer/Vendor** in Produktion bereitstellen; keine Dev-Abhängigkeiten ausliefern.
- **`/data` und `/logs`** außerhalb des Webroots halten oder durch Deny-Regeln schützen.
- **Backups** mit Schutz vor Exfiltration (verschlüsselt, rotationsfähig).

---

## Quickstart (Betrieb)
1. `.env.prod` befüllen (SMTP & Dashboard-Secret/Passwort).
2. Verzeichnisse anlegen: `logs/`, `data/` (Schreibrechte für PHP-FPM/Apache-User).
3. Webserver-Header & HTTPS konfigurieren.
4. Dashboard aufrufen → Login setzen → Block-/Whitelist administrieren.
5. Formulartest (legitim & Spamfälle) durchführen, Dashboard-Statistiken prüfen.

---

## Bekannte Punkte & ToDos
- `dashboard-api.php`: CORS von `*` auf die eigene Origin begrenzen.
- **CSRF-Token** zusätzlich einführen für alle Dashboard-POST-Aktionen.
- Unit-/Smoke-Tests für Validator-Regeln (z. B. Domain-Blacklist, Timestamp-Heuristik).
- Optional: **ReCAPTCHA**/hCaptcha als austauschbarer Provider (derzeit Mathe-Captcha).
- Optional: **Serverseitiges Rate-Limiting** (z. B. Tokens per IP/Zeitfenster in `data/` oder Redis).

---

## Lizenz & Verantwortung
Die Komponenten sind als sicherheitsbewusste Bausteine konzipiert. Sicherheit ist ein **Prozess** – Betrieb, Monitoring und regelmäßige Updates sind notwendig. Betreiber sind für **korrekte Konfiguration** und **rechtliche Einhaltung** (insb. DSGVO) verantwortlich.
