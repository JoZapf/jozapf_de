
# Schwachstellenanalyse & Härtungsempfehlungen (Contact Form + Admin-Dashboard)

> **Zielgruppe:** Technische Admins/Entwickler  
> **Scope:** `BlocklistManager.php`, `ContactFormValidator-v2.php`, `contact-php-handler.php`, `dashboard.php`, `dashboard-api.php`, `dashboard-blocklist.php`, `dashboard-login.php`, `ExtendedLogger.php`, `contact-form-logic.js`  
> **Stand:** 05.10.2025 (Europe/Berlin)

---

## 1. Problemdefinition
Kontaktformulare sind häufige Ziele für Spam, Recon und Injektionsangriffe; Admin-Dashboards benötigen robusten Zugriffsschutz. Ziel dieser Analyse ist es, **angreifbare Schwachstellen** und **Verbesserungspotenziale** zu identifizieren und **konkrete, priorisierte Maßnahmen** bereitzustellen – inkl. Code-Snippets.

---

## 2. Befunde (faktenbasiert, am Code abgeleitet)

### 2.1 Offene Dashboard-API mit PII-Leakage
**Datei:** `dashboard-api.php`  
- Setzt `Access-Control-Allow-Origin: *` und liefert ohne Auth **E-Mail, IP, Zeitstempel** & Gründe/Score aus (`recentSubmissions`/`recentBlocks`).  
**Risiko:** Unautorisierter Datenabfluss (DSGVO), Recon für Angreifer.  
**Empfehlung (Sofortmaßnahme):**
- Entferne `*`, erlaube nur die eigene Origin **oder** verlange ein gültiges Dashboard-Token (siehe `verifyToken()`-Logik).  
- Reduziere PII in Responses (aggregierte Werte statt Rohdaten).

**Beispiel (Minimal-Patch):**
```php
// dashboard-api.php (vor Ausgabe)
require_once __DIR__.'/dashboard-login.php'; // für verifyToken()
if (!verifyToken($_COOKIE['dashboard_token'] ?? '')) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
// CORS auf eigene Origin begrenzen
header('Access-Control-Allow-Origin: https://example.org');
header('Vary: Origin');
```

---

### 2.2 Fehlender CSRF-Schutz bei Admin-POSTs
**Datei:** `dashboard.php`  
- Mutierende Aktionen (`block_ip`, `unblock_ip`, `whitelist_ip`, `remove_whitelist`) ohne Anti-CSRF-Token.  
**Risiko:** CSRF bei eingeloggtem Admin (SameSite=Strict hilft, ersetzt Token aber nicht).  
**Empfehlung (Sofortmaßnahme):**
- Pro Session CSRF-Token generieren (z. B. im HMAC-Token-Claim oder serverseitig) und bei jeder POST-Action prüfen.

**Beispiel (Server-Teil):**
```php
// Beim Login zusätzlich im Cookie ablegen (separat vom HttpOnly-Cookie):
$csrf = bin2hex(random_bytes(32));
setcookie('csrf_token', $csrf, [
  'secure' => true, 'samesite' => 'Strict', 'path' => '/dashboard', 'httponly' => false
]);

// Bei POST-Aktionen in dashboard.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfClient = $_POST['csrf_token'] ?? '';
    $csrfCookie = $_COOKIE['csrf_token'] ?? '';
    if (!hash_equals($csrfCookie, $csrfClient)) {
        http_response_code(403);
        exit('CSRF validation failed');
    }
}
```

**Beispiel (Form):**
```html
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_COOKIE['csrf_token'] ?? '') ?>">
  <!-- restliche Felder -->
</form>
```

---

### 2.3 Admin-Passwort im Klartext-Vergleich, kein Login-Rate-Limit
**Datei:** `dashboard-login.php`  
- Vergleich `$_POST['password'] === $DASHBOARD_PASSWORD`.  
**Risiko:** Keine KDF (Argon2id), kein Brute-Force-Schutz.  
**Empfehlung (Sofortmaßnahme):**
- `.env.prod` auf `DASHBOARD_PASSWORD_HASH` (Argon2id) umstellen, Login via `password_verify()`.  
-  Rate-Limit (z. B. 5 Versuche/15 min/IP) – via `ExtendedLogger` oder Fail2ban.

**Beispiel (KDF):**
```php
// Setup einmalig: $hash = password_hash($plain, PASSWORD_ARGON2ID);
$HASH = getenv('DASHBOARD_PASSWORD_HASH');

if (!password_verify($_POST['password'] ?? '', $HASH)) {
    // failed attempt -> rate-limit counter ++
    http_response_code(401);
    exit('invalid');
}
```

---

### 2.4 „Auto-Anonymisierung“ existiert, wird aber nicht ausgeführt
**Datei:** `ExtendedLogger.php` (+ Aufrufer)  
- Funktion zur Anonymisierung alter Einträge vorhanden, **kein geplanter Aufruf**.  
**Risiko:** Voll-IPs bleiben >14 Tage gespeichert → DSGVO-Risiko.  
**Empfehlung (Sofortmaßnahme):**
- Täglicher Cron-Job:
```bash
# /etc/cron.d/contact-logs
0 3 * * * www-data /usr/bin/php /var/www/app/bin/anonymize-logs.php
```
- `bin/anonymize-logs.php` ruft `anonymizeOldEntries()` auf und loggt Ergebnis (Audit).

---

### 2.5 Logs/Blocklisten potentiell unter Webroot
**Dateien:** `ExtendedLogger.php` (logs), `BlocklistManager.php` (data)  
**Risiko:** Direktabruf bei Directory Listing/Misskonfiguration.  
**Empfehlung (Sofortmaßnahme):**
- Verzeichnisse **außerhalb** des Webroots betreiben oder per Server-Rule sperren.  
- Dateirechte restriktiv (`600`/`640`, Ordner `700`/`750`).

**Beispiel (nginx):**
```nginx
location ~ ^/(logs|data)/ { deny all; return 404; }
```

---

### 2.6 CORS-Weite & Over-Sharing
**Datei:** `dashboard-api.php`  
**Risiko:** Selbst mit Auth zu breite Datenweitergabe (E-Mail, IP, Gründe).  
**Empfehlung:**
- Privacy by Design: nur **aggregierte** Kennzahlen, Rohdaten nur im Admin-UI nach Auth.  
- Explizite `Content-Type: application/json` und `Cache-Control: no-store` setzen.

---

### 2.7 Real-IP-Ermittlung hinter Proxy/CDN
**Dateien:** `ContactFormValidator-v2.php`, `contact-php-handler.php`, `ExtendedLogger.php`  
**Risiko:** Falsche/fehlkonfigurierte Real-IP → Rate-Limit/Blocklist wirkt nicht korrekt; mit blindem `X-Forwarded-For` wäre Spoofing möglich.  
**Empfehlung:**
- Nur bei **vertrauenswürdiger Proxy-Kette** `X-Forwarded-For` auswerten und letzte öffentliche IP extrahieren, sonst bei `REMOTE_ADDR` bleiben.

**Beispiel:**
```php
function getClientIp(): string {
    $trusted = ['203.0.113.10']; // eigene Proxy-IP(s)
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remote, $trusted, true)) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            $ips = array_map('trim', explode(',', $xff));
            return end($ips) ?: $remote;
        }
    }
    return $remote;
}
```

---

### 2.8 Session-/Token-Design
**Datei:** `dashboard-login.php`  
**Risiko:** Token ohne `jti`/Blacklist/Logout-Fluss; feste 24h-Lebenszeit.  
**Empfehlung:**
- Claims: `jti` (random), `iss`, `aud`.  
- Deny-List für widerrufene Tokens (kleiner Ring-Buffer).  
- Rolling Expiration (z. B. Sliding 30 min, Max 24 h) und aktive Logout-Invalidierung.

---

### 2.9 DoS/Performance bei Filesystem-Backends
**Dateien:** `ExtendedLogger.php`, `BlocklistManager.php`  
**Risiko:** `file()`/lineare Scans auf großen Dateien → I/O-Last bei Peak.  
**Empfehlung:**
- Logrotation (Größe/Alter), Indizes/Counter statt Full-Scan.  
- Optional: SQLite/PostgreSQL für Statistiken/Rate-Limit.

---

### 2.10 Output-Härtung (weitgehend gut)
**Datei:** `dashboard.php`  
**Hinweis:** `htmlspecialchars()` konsequent **für alle** dynamischen Felder (inkl. Notes/Reasons/User-Agent) anwenden; CSP unterstützt Defense-in-Depth.

---

## 3. Optionale Annahmen
- (A1) Projekt liegt unter Webroot; `.env.prod` ist per Webserver geschützt.  
- (A2) Reverse-Proxy/CDN evtl. im Einsatz (Real-IP-Thema relevant).  
- (A3) Keine zusätzliche Netz-ACL/HTTP-Auth vor dem Dashboard.

---

## 4. Empfohlene Schritte (priorisiert)

### 4.1 Sofort
1. **API-Auth & CORS fixen** (`dashboard-api.php`): Auth-Pflicht, Origin whitelisten, PII minimieren.  
2. **CSRF-Token** für alle Admin-POSTs (`dashboard.php`).  
3. **Passwort-Hash + Rate-Limit** (`dashboard-login.php`): Argon2id + Fail2ban/Applikationszähler.  
4. **Automatische Anonymisierung** (Cron) und Audit-Logeintrag.  
5. **Logs/Daten** außerhalb Webroot oder hart blockieren, Rechte setzen.

### 4.2 Kurzfristig (Tage)
6. **Security-Header & CSP** (global):  
   - `Content-Security-Policy: default-src 'self'; frame-ancestors 'none'`  
   - `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, HSTS.  
7. **Real-IP-Handling** hinter Proxy korrekt umsetzen.  
8. **Token-Verbesserungen**: `jti`, Deny-List, Rolling Expiration.

### 4.3 Mittelfristig (1–2 Wochen)
9. **API trennen**: Admin-API (authentisiert) vs. Public-Status (nur aggregiert, PII-frei).  
10. **Auditierbarkeit**: Änderungslog für Block-/Whitelist inkl. Benutzer (aus Token), Zeit, Grund.  
11. **Tests**: Unit-Tests (Validator-Heuristiken), E2E-Tests (Login, CSRF, Block/Unblock).

---

## 5. Anhang – Konfigurations- & Server-Snippets

### 5.1 NGINX-Hardening (Ausschnitt)
```nginx
add_header Content-Security-Policy "default-src 'self'; frame-ancestors 'none'" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "no-referrer" always;
add_header Permissions-Policy "geolocation=(), microphone=()" always;

# HSTS (nur aktivieren, wenn HTTPS sauber ausgerollt ist)
add_header Strict-Transport-Security "max-age=15552000; includeSubDomains; preload" always;

location ~ ^/(logs|data)/ { deny all; return 404; }
```

### 5.2 Beispiel `.env.prod`
```
RECIPIENT_EMAIL="mail@example.org"
SMTP_HOST="smtp.example.org"
SMTP_PORT="587"
SMTP_USER="smtp-user"
SMTP_PASS="***"
SMTP_SECURE="tls"

DASHBOARD_SECRET="base64-encoded-32B"
DASHBOARD_PASSWORD_HASH="$argon2id$v=19$m=65536,t=3,p=1$..."
```

---

## 6. Zusammenfassung
- Kritisch: **Offene API mit PII**, **fehlendes CSRF**, **kein Passwort-Hashing**.  
- Compliance: **Anonymisierung einplanen & automatisieren**, Logs/Daten **aus Webroot**.  
- Härtung: **CSP & Header**, **Token-/Logout-Mechanik**, **Real-IP korrekt**, **Rate-Limits**.

> Wenn gewünscht, kann ich dir gezielte Patches pro Datei erstellen (Diffs), die obige Maßnahmen 1:1 implementieren.
