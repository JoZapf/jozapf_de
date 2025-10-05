# Security Fixes Runbook

> **Projekt:** Contact Form Abuse Prevention  
> **Erstellungsdatum:** 05.10.2025  
> **Status:** In Bearbeitung  
> **Ziel:** Behebung kritischer Sicherheitslücken gemäß Schwachstellenanalyse

---

## 1. Hauptbefunde (Kritisch)

### 🔴 Kritische Sicherheitslücken

| ID | Schwachstelle | Risiko | Priorität |
|----|---------------|--------|-----------|
| SEC-01 | Offene Dashboard-API mit PII-Leakage | DSGVO-Verstoß, Recon | **KRITISCH** |
| SEC-02 | Fehlender CSRF-Schutz bei Admin-POSTs | Session-Hijacking, unauth. Aktionen | **KRITISCH** |
| SEC-03 | Klartext-Passwort-Vergleich ohne Rate-Limit | Brute-Force-Angriffe | **KRITISCH** |
| SEC-04 | Fehlende automatische Anonymisierung | DSGVO-Verstoß | **HOCH** |
| SEC-05 | Logs/Blocklisten unter Webroot | Datenleck bei Fehlkonfiguration | **HOCH** |
| SEC-06 | CORS-Weite & Over-Sharing | Unnötiger Datenzugriff | **MITTEL** |
| SEC-07 | Real-IP-Ermittlung anfällig | Rate-Limit-Bypass, Spoofing | **MITTEL** |
| SEC-08 | Token-Design ohne Widerruf | Session-Management-Schwächen | **MITTEL** |
| SEC-09 | DoS-Anfälligkeit bei großen Dateien | Performance-Probleme | **NIEDRIG** |
| SEC-10 | Fehlende Security-Header | Defense-in-Depth | **NIEDRIG** |

---

## 2. Logische Strukturierung der Arbeitspakete

### Phase 1: Sofortmaßnahmen (Kritische Fixes)
- **Ziel:** Kritische Sicherheitslücken schließen, die aktiv ausnutzbar sind
- **Zeitrahmen:** 1-2 Tage
- **Arbeitspakete:** AP-01 bis AP-05

### Phase 2: Kurzfristige Härtung (Defense-in-Depth)
- **Ziel:** Zusätzliche Sicherheitsschichten implementieren
- **Zeitrahmen:** 3-5 Tage
- **Arbeitspakete:** AP-06 bis AP-08

### Phase 3: Mittelfristige Verbesserungen (Best Practices)
- **Ziel:** Langfristige Sicherheitsarchitektur etablieren
- **Zeitrahmen:** 1-2 Wochen
- **Arbeitspakete:** AP-09 bis AP-11

---

## 3. Detaillierte Arbeitspakete

---

### 📦 AP-01: Dashboard-API Authentifizierung & CORS-Härtung

**Betroffene Datei:** `assets/php/dashboard-api.php`  
**Schweregrad:** 🔴 KRITISCH  
**Geschätzter Aufwand:** 30-45 min

#### a) Schwachstelle

```php
// AKTUELLER ZUSTAND (unsicher):
header('Access-Control-Allow-Origin: *');
// ...
echo json_encode([
    'recentSubmissions' => [
        ['email' => 'user@example.com', 'ip' => '192.168.1.1', ...],
        // ... PII wird ohne Auth ausgeliefert
    ]
]);
```

**Probleme:**
- `Access-Control-Allow-Origin: *` erlaubt **jeder** Domain Zugriff
- API liefert personenbezogene Daten (E-Mail, IP, Zeitstempel) **ohne Authentifizierung**
- DSGVO-Verstoß: Unbefugter Zugriff auf PII möglich
- Recon-Möglichkeit für Angreifer (IP-Adressen, Zeitstempel, Verhaltensmuster)

#### b) Zu realisierende Lösung

**Schritt 1:** Token-basierte Authentifizierung implementieren
```php
<?php
// dashboard-api.php - ANFANG DER DATEI
require_once __DIR__.'/dashboard-login.php';

// Token-Prüfung VOR jeder Datenausgabe
if (!verifyToken($_COOKIE['dashboard_token'] ?? '')) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
```

**Schritt 2:** CORS auf eigene Origin beschränken
```php
// CORS nur für eigene Domain
$allowedOrigin = 'https://jozapf.de'; // TODO: aus .env laden
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');
```

**Schritt 3:** PII-Daten minimieren (optional, aber empfohlen)
```php
// Statt Rohdaten: Aggregierte Werte
'recentSubmissions' => [
    ['timestamp' => '2025-10-05 10:23', 'status' => 'sent', 'ip_partial' => '192.168.*.*'],
    // E-Mail-Adresse nur als Hash oder gar nicht
]
```

**Akzeptanzkriterien:**
- ✅ API liefert HTTP 401 bei fehlendem/ungültigem Token
- ✅ CORS-Header auf eigene Domain beschränkt
- ✅ Keine Volltexte von E-Mail/IP ohne validen Admin-Token
- ✅ Test: Zugriff ohne Cookie schlägt fehl

---

### 📦 AP-02: CSRF-Schutz für Admin-Aktionen

**Betroffene Datei:** `assets/php/dashboard.php`  
**Schweregrad:** 🔴 KRITISCH  
**Geschätzter Aufwand:** 45-60 min

#### a) Schwachstelle

```php
// AKTUELLER ZUSTAND (unsicher):
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'block_ip') {
        // KEINE CSRF-Prüfung!
        $blocklistManager->blockIP($_POST['ip'], ...);
    }
}
```

**Probleme:**
- Eingeloggter Admin kann durch bösartige Webseite zu Aktionen gezwungen werden
- `SameSite=Strict` Cookie allein ist **kein ausreichender Schutz** (Browser-Kompatibilität, Subdomain-Angriffe)
- Angreifer kann IPs blocken/entblocken, Whitelist manipulieren

#### b) Zu realisierende Lösung

**Schritt 1:** CSRF-Token im Login generieren
```php
// dashboard-login.php - nach erfolgreicher Authentifizierung
$csrfToken = bin2hex(random_bytes(32));

// Separates Cookie (NICHT HttpOnly, da JS-Zugriff benötigt)
setcookie('csrf_token', $csrfToken, [
    'expires' => time() + 86400,
    'path' => '/assets/php',
    'domain' => '', 
    'secure' => true,
    'httponly' => false, // Muss für JS lesbar sein
    'samesite' => 'Strict'
]);

// Token auch im JWT-Claim speichern (für serverseitige Validierung)
$payload = [
    'exp' => time() + 86400,
    'iat' => time(),
    'csrf' => $csrfToken,
    // ... weitere Claims
];
```

**Schritt 2:** CSRF-Validierung vor jeder POST-Aktion
```php
// dashboard.php - ANFANG DER POST-VERARBEITUNG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token aus Cookie und POST-Daten holen
    $csrfCookie = $_COOKIE['csrf_token'] ?? '';
    $csrfPost = $_POST['csrf_token'] ?? '';
    
    // Timing-Safe Vergleich
    if (!hash_equals($csrfCookie, $csrfPost)) {
        http_response_code(403);
        die('CSRF validation failed');
    }
    
    // Zusätzlich: Token aus JWT validieren
    $decoded = JWT::decode($_COOKIE['dashboard_token'], ...);
    if (!hash_equals($decoded->csrf ?? '', $csrfCookie)) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
    
    // Erst JETZT sind POST-Aktionen erlaubt
    // ...
}
```

**Schritt 3:** Token in alle Formulare einfügen
```php
// dashboard.php - in HTML-Formularen
<form method="post" action="dashboard.php">
    <input type="hidden" name="csrf_token" 
           value="<?= htmlspecialchars($_COOKIE['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="block_ip">
    <!-- weitere Felder -->
</form>
```

**Akzeptanzkriterien:**
- ✅ Alle POST-Aktionen prüfen CSRF-Token
- ✅ Fehlerhafter Token → HTTP 403
- ✅ Token wird bei Login generiert
- ✅ Token ist in allen Formularen vorhanden
- ✅ Test: POST ohne Token schlägt fehl

---

### 📦 AP-03: Passwort-Hashing & Login-Rate-Limit

**Betroffene Dateien:** `assets/php/dashboard-login.php`, `.env.prod`  
**Schweregrad:** 🔴 KRITISCH  
**Geschätzter Aufwand:** 60-90 min

#### a) Schwachstelle

```php
// AKTUELLER ZUSTAND (unsicher):
$DASHBOARD_PASSWORD = getenv('DASHBOARD_PASSWORD');
if ($_POST['password'] === $DASHBOARD_PASSWORD) {
    // Login erfolgreich
}
```

**Probleme:**
- Passwort wird im **Klartext** verglichen (kein Hashing)
- Keine Key Derivation Function (KDF) wie Argon2id/bcrypt
- **Kein Rate-Limiting** → Brute-Force-Angriffe möglich
- Bei Kompromittierung der `.env`-Datei ist Passwort sofort bekannt

#### b) Zu realisierende Lösung

**Schritt 1:** Passwort-Hash generieren (einmalig)
```bash
# Auf dem Server ausführen:
php -r "echo password_hash('DEIN_SICHERES_PASSWORT', PASSWORD_ARGON2ID);"
# Output: $argon2id$v=19$m=65536,t=4,p=1$...
```

**Schritt 2:** `.env.prod` aktualisieren
```env
# .env.prod
DASHBOARD_PASSWORD_HASH="$argon2id$v=19$m=65536,t=4,p=1$..."
# WICHTIG: DASHBOARD_PASSWORD entfernen!
```

**Schritt 3:** Login-Logik mit password_verify()
```php
// dashboard-login.php
$passwordHash = getenv('DASHBOARD_PASSWORD_HASH');

if (empty($passwordHash)) {
    error_log('CRITICAL: DASHBOARD_PASSWORD_HASH not set!');
    http_response_code(500);
    die('Server configuration error');
}

$inputPassword = $_POST['password'] ?? '';

if (!password_verify($inputPassword, $passwordHash)) {
    // FEHLVERSUCH LOGGEN (für Rate-Limit)
    logFailedLoginAttempt($_SERVER['REMOTE_ADDR']);
    
    http_response_code(401);
    die('Invalid credentials');
}

// Login erfolgreich - Failed-Attempts zurücksetzen
resetFailedLoginAttempts($_SERVER['REMOTE_ADDR']);
```

**Schritt 4:** Rate-Limiting implementieren
```php
// dashboard-login.php - Hilfsfunktionen
function logFailedLoginAttempt(string $ip): void {
    $logger = new ExtendedLogger(__DIR__ . '/logs');
    $logger->logAttempt([
        'type' => 'failed_login',
        'ip' => $ip,
        'timestamp' => time()
    ]);
}

function isRateLimited(string $ip): bool {
    $logger = new ExtendedLogger(__DIR__ . '/logs');
    $attempts = $logger->getRecentAttempts($ip, 900); // 15 Minuten
    
    $failedLogins = array_filter($attempts, fn($a) => $a['type'] === 'failed_login');
    
    return count($failedLogins) >= 5; // Max 5 Versuche in 15 Min
}

// VOR password_verify() prüfen:
if (isRateLimited($_SERVER['REMOTE_ADDR'])) {
    http_response_code(429);
    die('Too many failed login attempts. Try again in 15 minutes.');
}
```

**Akzeptanzkriterien:**
- ✅ Passwort wird mit Argon2id gehasht
- ✅ `password_verify()` wird verwendet
- ✅ Rate-Limit: Max 5 Versuche / 15 Min / IP
- ✅ Fehlversuche werden geloggt
- ✅ Test: 6. Loginversuch wird blockiert
- ✅ Klartext-Passwort aus `.env` entfernt

---

### 📦 AP-04: Automatische Log-Anonymisierung (Cron)

**Betroffene Dateien:** `assets/php/ExtendedLogger.php`, neues Script `bin/anonymize-logs.php`  
**Schweregrad:** 🔴 KRITISCH (DSGVO)  
**Geschätzter Aufwand:** 30-45 min

#### a) Schwachstelle

```php
// ExtendedLogger.php - Funktion existiert, wird aber NICHT aufgerufen
public function anonymizeOldEntries(int $daysOld = 14): int {
    // ... Code vorhanden, aber keine automatische Ausführung
}
```

**Probleme:**
- IP-Adressen bleiben **unbegrenzt** in Logs gespeichert
- DSGVO verlangt Datensparsamkeit (max. 14 Tage für Vollst-IPs)
- Keine automatische Ausführung → manuelle Intervention erforderlich
- Fehlende Audit-Logs für Anonymisierungen

#### b) Zu realisierende Lösung

**Schritt 1:** Anonymisierungs-Script erstellen
```php
<?php
// bin/anonymize-logs.php
require_once __DIR__ . '/../assets/php/ExtendedLogger.php';

$logger = new ExtendedLogger(__DIR__ . '/../assets/php/logs');

try {
    $anonymizedCount = $logger->anonymizeOldEntries(14);
    
    // Audit-Log schreiben
    $logger->logAttempt([
        'type' => 'log_anonymization',
        'count' => $anonymizedCount,
        'timestamp' => time(),
        'triggered_by' => 'cronjob'
    ]);
    
    echo date('Y-m-d H:i:s') . " - Anonymized {$anonymizedCount} log entries\n";
    exit(0);
    
} catch (Exception $e) {
    error_log("Log anonymization failed: " . $e->getMessage());
    exit(1);
}
```

**Schritt 2:** Cron-Job einrichten
```bash
# /etc/cron.d/contact-form-anonymize
0 3 * * * www-data /usr/bin/php /var/www/jozapf.de/assets/php/bin/anonymize-logs.php >> /var/log/anonymize-logs.log 2>&1
```

**Schritt 3:** ExtendedLogger erweitern (Audit-Log)
```php
// ExtendedLogger.php - neue Methode
public function logAnonymization(int $count): void {
    $auditEntry = sprintf(
        "[%s] ANONYMIZATION: %d entries anonymized\n",
        date('Y-m-d H:i:s'),
        $count
    );
    
    file_put_contents(
        $this->logDir . '/audit.log',
        $auditEntry,
        FILE_APPEND | LOCK_EX
    );
}
```

**Akzeptanzkriterien:**
- ✅ Script `bin/anonymize-logs.php` erstellt
- ✅ Cron-Job läuft täglich um 3:00 Uhr
- ✅ Anonymisierung erfolgt nach 14 Tagen
- ✅ Audit-Log wird geschrieben
- ✅ Test: Manueller Script-Aufruf funktioniert
- ✅ Fehlerbehandlung vorhanden

---

### 📦 AP-05: Logs/Daten aus Webroot isolieren

**Betroffene Dateien:** `assets/php/ExtendedLogger.php`, `assets/php/BlocklistManager.php`, Webserver-Config  
**Schweregrad:** 🟡 HOCH  
**Geschätzter Aufwand:** 45-60 min

#### a) Schwachstelle

```
/assets/php/logs/           ← Unter Webroot!
/assets/php/data/           ← Unter Webroot!
```

**Probleme:**
- Bei aktiviertem Directory Listing: Direkter Zugriff auf Log-Dateien
- Bei Fehlkonfiguration: Logs als Plain-Text abrufbar
- `.htaccess` schützt, aber Defense-in-Depth fehlt
- Best Practice: Sensitive Daten **außerhalb** Webroot

#### b) Zu realisierende Lösung

**Option A: Dateien außerhalb Webroot (empfohlen)**

**Schritt 1:** Neue Verzeichnisstruktur
```bash
/var/www/jozapf.de/
├── public/               ← Webroot (DocumentRoot)
│   └── assets/
│       └── php/
│           ├── dashboard.php
│           └── ...
└── storage/              ← AUSSERHALB Webroot
    ├── logs/
    └── data/
```

**Schritt 2:** Pfade in Klassen aktualisieren
```php
// ExtendedLogger.php
class ExtendedLogger {
    private string $logDir;
    
    public function __construct(?string $logDir = null) {
        $this->logDir = $logDir ?? dirname(__DIR__, 3) . '/storage/logs';
        // ... Rest
    }
}

// BlocklistManager.php
class BlocklistManager {
    private string $dataDir;
    
    public function __construct(?string $dataDir = null) {
        $this->dataDir = $dataDir ?? dirname(__DIR__, 3) . '/storage/data';
        // ... Rest
    }
}
```

**Option B: Webserver-Regel (Fallback)**

```nginx
# nginx.conf
location ~ ^/(logs|data)/ {
    deny all;
    return 404;
}
```

```apache
# .htaccess (bereits vorhanden, überprüfen)
<FilesMatch "\.(log|txt|json)$">
    Require all denied
</FilesMatch>
```

**Schritt 3:** Dateirechte setzen
```bash
chmod 700 /var/www/jozapf.de/storage/logs
chmod 700 /var/www/jozapf.de/storage/data
chmod 600 /var/www/jozapf.de/storage/logs/*.log
chmod 600 /var/www/jozapf.de/storage/data/*.txt
```

**Akzeptanzkriterien:**
- ✅ Logs/Daten außerhalb Webroot ODER hart blockiert
- ✅ Dateirechte restriktiv gesetzt (700/600)
- ✅ Test: HTTP-Zugriff auf Log-Dateien schlägt fehl
- ✅ Anwendung funktioniert weiterhin

---

### 📦 AP-06: Security-Header & CSP

**Betroffene Dateien:** Webserver-Config (nginx/Apache) oder `dashboard.php`  
**Schweregrad:** 🟡 MITTEL  
**Geschätzter Aufwand:** 30 min

#### a) Schwachstelle

**Probleme:**
- Fehlende Content-Security-Policy (CSP)
- Keine `X-Content-Type-Options`
- Kein `Referrer-Policy`
- Kein HSTS (Strict-Transport-Security)
- Defense-in-Depth fehlt

#### b) Zu realisierende Lösung

**Option A: Global (nginx)**
```nginx
# /etc/nginx/sites-available/jozapf.de
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "no-referrer" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

# HSTS (nur wenn HTTPS vollständig ausgerollt)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

**Option B: Per PHP (dashboard.php)**
```php
// dashboard.php - ANFANG DER DATEI
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
```

**Akzeptanzkriterien:**
- ✅ CSP-Header gesetzt
- ✅ X-Content-Type-Options: nosniff
- ✅ X-Frame-Options: DENY
- ✅ Test: Header mit Browser DevTools prüfen

---

### 📦 AP-07: Real-IP-Handling hinter Proxy

**Betroffene Dateien:** `assets/php/ContactFormValidator-v2.php`, `assets/php/contact-php-handler.php`  
**Schweregrad:** 🟡 MITTEL  
**Geschätzter Aufwand:** 45 min

#### a) Schwachstelle

```php
// Potentiell anfällig:
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
```

**Probleme:**
- Blindes Vertrauen in `X-Forwarded-For` ermöglicht IP-Spoofing
- Rate-Limit/Blocklist umgehbar
- Nur vertrauenswürdige Proxies dürfen Header setzen

#### b) Zu realisierende Lösung

```php
// Neue Hilfsfunktion (z.B. in ContactFormValidator-v2.php)
function getClientIP(): string {
    // Liste vertrauenswürdiger Proxy-IPs (aus .env laden!)
    $trustedProxies = explode(',', getenv('TRUSTED_PROXIES') ?: '');
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Nur wenn Request von vertrauenswürdigem Proxy kommt
    if (in_array($remoteAddr, $trustedProxies, true)) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            // Letzte IP aus Liste (Client-IP)
            $ips = array_map('trim', explode(',', $xff));
            $clientIP = end($ips);
            
            // Validierung
            if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                return $clientIP;
            }
        }
    }
    
    // Fallback: REMOTE_ADDR
    return $remoteAddr;
}

// Verwendung:
$clientIP = getClientIP();
```

**Akzeptanzkriterien:**
- ✅ Funktion `getClientIP()` implementiert
- ✅ Nur vertrauenswürdige Proxies werden berücksichtigt
- ✅ Fallback auf `REMOTE_ADDR`
- ✅ IP-Validierung vorhanden

---

### 📦 AP-08: Token-Verbesserungen (jti, Deny-List)

**Betroffene Dateien:** `assets/php/dashboard-login.php`  
**Schweregrad:** 🟡 MITTEL  
**Geschätzter Aufwand:** 60 min

#### a) Schwachstelle

```php
// Aktuell:
$payload = [
    'exp' => time() + 86400,
    'iat' => time(),
    // Kein jti, kein iss, kein aud
];
```

**Probleme:**
- Kein eindeutiger Token-Identifier (`jti`)
- Kein Logout-Mechanismus (Token-Widerruf nicht möglich)
- Feste 24h-Lebenszeit ohne Sliding Expiration

#### b) Zu realisierende Lösung

**Schritt 1:** Token-Claims erweitern
```php
// dashboard-login.php
$jti = bin2hex(random_bytes(16)); // Eindeutige Token-ID

$payload = [
    'exp' => time() + 86400,  // 24h Max-Lifetime
    'iat' => time(),
    'nbf' => time(),
    'jti' => $jti,
    'iss' => 'jozapf.de',
    'aud' => 'dashboard',
    'csrf' => $csrfToken,
    'last_activity' => time()  // Für Sliding Expiration
];
```

**Schritt 2:** Deny-List implementieren
```php
// Token-Deny-List (einfache Datei-Lösung)
function revokeToken(string $jti): void {
    $denyListFile = __DIR__ . '/data/token-denylist.json';
    $denyList = json_decode(file_get_contents($denyListFile) ?: '[]', true);
    
    $denyList[$jti] = time();
    
    // Alte Einträge entfernen (älter als 24h)
    $denyList = array_filter($denyList, fn($time) => $time > time() - 86400);
    
    file_put_contents($denyListFile, json_encode($denyList), LOCK_EX);
}

function isTokenRevoked(string $jti): bool {
    $denyListFile = __DIR__ . '/data/token-denylist.json';
    $denyList = json_decode(file_get_contents($denyListFile) ?: '[]', true);
    return isset($denyList[$jti]);
}

// In verifyToken():
$decoded = JWT::decode($token, ...);
if (isTokenRevoked($decoded->jti)) {
    return false;
}
```

**Schritt 3:** Logout-Funktion
```php
// dashboard-logout.php (neu)
<?php
require_once 'dashboard-login.php';

$decoded = JWT::decode($_COOKIE['dashboard_token'], ...);
revokeToken($decoded->jti);

setcookie('dashboard_token', '', time() - 3600, '/assets/php');
setcookie('csrf_token', '', time() - 3600, '/assets/php');

header('Location: dashboard-login.php');
```

**Akzeptanzkriterien:**
- ✅ JWT enthält `jti`, `iss`, `aud`
- ✅ Deny-List funktioniert
- ✅ Logout widerruft Token
- ✅ Widerrufene Tokens werden abgelehnt

---

### 📦 AP-09: API-Trennung (Admin vs. Public)

**Betroffene Dateien:** `assets/php/dashboard-api.php` (Refactoring)  
**Schweregrad:** 🟢 NIEDRIG (Best Practice)  
**Geschätzter Aufwand:** 90 min

#### a) Schwachstelle

**Probleme:**
- Eine API liefert sowohl Admin-Daten als auch Public-Status
- Keine klare Trennung zwischen geschützten und öffentlichen Endpunkten
- Over-Sharing von Daten

#### b) Zu realisierende Lösung

**Neue Struktur:**
```
/assets/php/
├── dashboard-api.php        ← Admin-API (Auth erforderlich)
└── public-api.php           ← Public-API (nur aggregierte Daten)
```

**Public-API (public-api.php):**
```php
<?php
// Nur aggregierte, PII-freie Daten
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Okay für Public-API

$logger = new ExtendedLogger(__DIR__ . '/logs');
$stats = $logger->getStatistics();

echo json_encode([
    'submissions_today' => $stats['submissions_today'],
    'blocks_today' => $stats['blocks_today'],
    'status' => 'operational'
    // KEINE IPs, E-Mails, detaillierte Logs
]);
```

**Akzeptanzkriterien:**
- ✅ Admin-API: Auth erforderlich
- ✅ Public-API: Keine PII
- ✅ Klare Trennung

---

### 📦 AP-10: Audit-Logging für Block/Whitelist

**Betroffene Dateien:** `assets/php/BlocklistManager.php`  
**Schweregrad:** 🟢 NIEDRIG (Best Practice)  
**Geschätzter Aufwand:** 45 min

#### a) Schwachstelle

**Probleme:**
- Änderungen an Blocklist/Whitelist werden nicht auditiert
- Kein Nachweis, wer wann was geändert hat
- Compliance-Anforderungen (z.B. SOC2) nicht erfüllt

#### b) Zu realisierende Lösung

```php
// BlocklistManager.php - Audit-Log
public function blockIP(string $ip, string $reason, string $admin): bool {
    // ... Block-Logik ...
    
    $this->logAudit([
        'action' => 'block_ip',
        'ip' => $ip,
        'reason' => $reason,
        'admin' => $admin,  // Aus JWT-Token
        'timestamp' => time()
    ]);
    
    return true;
}

private function logAudit(array $entry): void {
    $auditFile = $this->dataDir . '/audit.log';
    $line = json_encode($entry) . "\n";
    file_put_contents($auditFile, $line, FILE_APPEND | LOCK_EX);
}
```

**Akzeptanzkriterien:**
- ✅ Alle Änderungen werden geloggt
- ✅ Admin-Identifier im Log
- ✅ Audit-Log rotiert (Größe/Alter)

---

### 📦 AP-11: Unit- & E2E-Tests

**Neue Dateien:** `tests/`  
**Schweregrad:** 🟢 NIEDRIG (Best Practice)  
**Geschätzter Aufwand:** 3-4 Stunden

#### a) Schwachstelle

**Probleme:**
- Keine automatisierten Tests
- Regression-Risiko bei Änderungen
- Keine Sicherheits-Tests

#### b) Zu realisierende Lösung

**Struktur:**
```
tests/
├── Unit/
│   ├── ValidatorTest.php
│   └── BlocklistManagerTest.php
└── E2E/
    ├── LoginTest.php
    └── CSRFTest.php
```

**Beispiel: CSRF-Test**
```php
// tests/E2E/CSRFTest.php
public function testPostWithoutCSRFTokenFails() {
    $response = $this->post('/dashboard.php', [
        'action' => 'block_ip',
        'ip' => '1.2.3.4'
        // KEIN csrf_token
    ]);
    
    $this->assertEquals(403, $response->getStatusCode());
}
```

**Akzeptanzkriterien:**
- ✅ Mindestens 10 Unit-Tests
- ✅ Mindestens 5 E2E-Tests
- ✅ CI/CD-Integration

---

## 4. Fortschrittsverfolgung

### Checkliste

- [x] AP-01: Dashboard-API Auth & CORS ✅ (2025-10-05, 45min) **🟢 LIVE IN PRODUKTION**
- [ ] AP-02: CSRF-Schutz
- [ ] AP-03: Passwort-Hashing & Rate-Limit
- [ ] AP-04: Auto-Anonymisierung
- [ ] AP-05: Logs aus Webroot
- [ ] AP-06: Security-Header
- [ ] AP-07: Real-IP-Handling
- [ ] AP-08: Token-Verbesserungen
- [ ] AP-09: API-Trennung
- [ ] AP-10: Audit-Logging
- [ ] AP-11: Tests

### Abnahmekriterien (Gesamtprojekt)

- ✅ Alle KRITISCHEN Schwachstellen behoben
- ✅ Security-Scan (z.B. OWASP ZAP) ohne High-Findings
- ✅ DSGVO-Compliance erreicht
- ✅ Penetration-Test bestanden (optional)
- ✅ Dokumentation aktualisiert

---

## 5. Rollback-Plan

**Falls Probleme auftreten:**

1. **Git-Backup vor jeder Änderung:**
   ```bash
   git checkout -b security-fix-ap-XX
   git commit -am "AP-XX: [Beschreibung]"
   ```

2. **Rollback-Befehl:**
   ```bash
   git checkout main
   git reset --hard COMMIT_HASH
   ```

3. **Backup-Dateien:**
   - Vor Änderung: `cp dashboard.php dashboard.php.backup-$(date +%s)`

---

## 6. Notizen & Lessons Learned

### AP-01: Dashboard-API Auth & CORS (2025-10-05)

**Status:** ✅ ABGESCHLOSSEN (mit Verbesserung)

**Implementierte Dateien:**
- `dashboard-api.v2.php` (v2.0.1) - Token-Auth, CORS, PII-Minimierung, **KEINE hardcoded Defaults**
- `.env.prod.example.v2` - Neue ALLOWED_ORIGIN Variable (REQUIRED)
- `AP-01-implementation-log.md` - Deployment-Guide
- `AP-01-summary-report.md` - Zusammenfassung
- `PRODUCTION-CONFIG.md` - Produktionswerte-Dokumentation
- `PRODUCTION-vs-GITHUB.md` - Quick-Reference
- `AP-01-config-update.md` - Verbesserung nach User-Feedback

**Lessons Learned:**
- ✅ **WICHTIGSTE ERKENNTNIS:** Alle konfigurierbaren Werte in `.env.prod`, KEINE Defaults im Code!
  - Code ist dadurch IMMER GitHub-ready (keine manuellen Änderungen vor Push)
  - Fail-Fast: Fehlende Config führt sofort zu HTTP 500 (statt silent defaults)
  - Folgt 12-Factor-App Prinzip: "Store config in the environment"
- ✅ Versionierung im Dateinamen verhindert versehentliches Überschreiben
- ✅ Repository-Header + Timestamps in jeder Datei erhöht Transparenz
- ✅ `verifyToken()` Duplikation zeigt Bedarf für zentrale Utility-Datei (→ AP-08)
- ✅ `maskEmail()` Funktion kann auch in anderen Bereichen verwendet werden
- ✅ User-Feedback führte zu besserer Architektur (keine hardcoded Defaults mehr)
- ⚠️ `env()` Funktion existiert jetzt in 2 Dateien (dashboard-login.php, dashboard-api.php)
  - **TODO:** In AP-08 zentrale `utils.php` erstellen

**Deployment-Status:** ✅ **LIVE IN PRODUKTION** (2025-10-05, getestet, keine Auffälligkeiten)

**Test-Ergebnisse:** 6/6 bestanden + Live-Test erfolgreich ✅

**Architektur-Verbesserung:**
- V2.0.0: Code mit `example.com` Fallback → Manuell ändern vor GitHub-Push
- V2.0.1: Code OHNE Fallback → Immer GitHub-ready, Fail-Fast bei fehlender Config ✅

---

- **AP-02:** _(ausstehend)_
- **AP-03:** _(ausstehend)_

---

**Ende des Runbooks**
