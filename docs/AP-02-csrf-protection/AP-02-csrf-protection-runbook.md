# AP-02: CSRF-Schutz für Admin-Aktionen

> **Projekt:** Contact Form Abuse Prevention  
> **Arbeitspaket:** AP-02 - CSRF Protection for Admin Actions  
> **Version:** 1.0.0  
> **Datum:** 2025-10-05  
> **Status:** 📋 READY FOR IMPLEMENTATION  
> **Repository:** https://github.com/JoZapf/contact-form-abuse-prevention  
> **Priorität:** 🔴 KRITISCH  
> **Geschätzter Aufwand:** 60-90 Minuten

---

## 🎯 Zielsetzung

Implementierung von **CSRF-Schutz (Cross-Site Request Forgery)** für alle Admin-POST-Aktionen im Dashboard zur Verhinderung von unbefugten Aktionen durch Session-Hijacking oder Cross-Site-Angriffe.

**Problem:** Ein authentifizierter Admin kann durch bösartige Webseiten zu ungewollten Aktionen gezwungen werden (z.B. IP-Blocking, Whitelist-Manipulation), da keine CSRF-Token-Validierung stattfindet.

---

## 📊 Schwachstellenanalyse

### Aktueller Zustand (UNSICHER)

**Betroffene Datei:** `dashboard.php` (Zeilen 49-106)

```php
// ⚠️ KEINE CSRF-PRÜFUNG!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'block_ip':
            // Direkte Ausführung ohne Token-Validierung
            $blocklist->addToBlocklist($_POST['ip'], ...);
            break;
        // ... weitere Aktionen
    }
}
```

### Identifizierte Risiken

| Risiko | Schweregrad | Beschreibung |
|--------|-------------|--------------|
| **Unauthorized Actions** | KRITISCH | Angreifer kann eingeloggten Admin zu Aktionen zwingen |
| **IP Manipulation** | HOCH | IPs können ohne Wissen des Admins geblockt/entblockt werden |
| **Whitelist Tampering** | HOCH | Trusted IPs können entfernt werden |
| **Session Riding** | MITTEL | Automatisierte Anfragen im Namen des Admins |

### Angriffsszenario

```html
<!-- Bösartige Webseite (attacker.com) -->
<form action="https://jozapf.de/assets/php/dashboard.php" method="POST">
    <input type="hidden" name="action" value="block_ip">
    <input type="hidden" name="ip" value="8.8.8.8">
    <input type="hidden" name="reason" value="Malicious">
    <input type="hidden" name="duration" value="permanent">
</form>
<script>document.forms[0].submit();</script>
```

**Ablauf:**
1. Admin ist im Dashboard eingeloggt (`dashboard_token` Cookie vorhanden)
2. Admin besucht attacker.com
3. Formular wird automatisch abgeschickt
4. Browser sendet `dashboard_token` Cookie mit
5. ✅ Aktion wird ohne Admin-Wissen ausgeführt (KEINE CSRF-Prüfung!)

---

## ✅ Lösungskonzept

### Architektur: Double Submit Cookie Pattern + JWT Verification

**Warum dieser Ansatz?**
- ✅ Stateless (kein Server-Side Session Storage)
- ✅ Kompatibel mit bestehendem HMAC-Token-System
- ✅ Defense-in-Depth: Zwei unabhängige Prüfungen
- ✅ 12-Factor-App konform

**Komponenten:**

1. **CSRF-Token in JWT-Payload**
   - Token wird bei Login generiert
   - Als Claim im `dashboard_token` JWT gespeichert
   - Serverseitige Validierung möglich

2. **CSRF-Token als separates Cookie**
   - Separates Cookie: `csrf_token`
   - `httponly: false` → JavaScript-lesbar (für Formulare)
   - Selbe Lifetime wie `dashboard_token` (24h)

3. **Token in POST-Requests**
   - Als hidden field in allen Formularen
   - Muss mit Cookie-Wert übereinstimmen

### Sicherheitsmechanismus

```
Login erfolgreich
    ↓
[1] CSRF-Token generieren (32 Bytes)
    ↓
[2] Token in JWT-Payload speichern
    ↓
[3] Token als separates Cookie setzen
    ↓
    
Admin-POST-Request
    ↓
[4] Token aus Cookie lesen
    ↓
[5] Token aus POST-Daten lesen
    ↓
[6] Token aus JWT-Payload extrahieren
    ↓
[7] Timing-Safe Vergleich: Cookie === POST
    ↓
[8] Zusätzlich: JWT-Claim === Cookie
    ↓
    Beide OK? → Aktion erlauben
    ↓
    Sonst: HTTP 403 + Logging
```

---

## 🔧 Implementierungsdetails

### Phase 1: Token-Generierung (`dashboard-login.php` → v2.0)

**Änderungen:**

1. **Funktion `generateToken()` erweitern**

```php
/**
 * Generate HMAC token with embedded CSRF token
 * 
 * @param string $secret DASHBOARD_SECRET from .env
 * @return array [jwt_token, csrf_token]
 */
function generateToken($secret) {
    // CSRF-Token generieren (32 Bytes = 64 Hex-Zeichen)
    $csrfToken = bin2hex(random_bytes(32));
    
    $data = [
        'user' => 'dashboard_admin',
        'exp' => time() + (24 * 3600),  // 24h
        'iat' => time(),
        'csrf' => $csrfToken  // ← NEU in v2.0
    ];
    
    $payload = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $payload, $secret);
    $jwtToken = $payload . '.' . $signature;
    
    return [$jwtToken, $csrfToken];
}
```

2. **Cookie-Handling nach Login**

```php
if (isset($_POST['password']) && $_POST['password'] === $DASHBOARD_PASSWORD) {
    // Token-Generierung mit CSRF-Token
    [$token, $csrfToken] = generateToken($DASHBOARD_SECRET);
    
    // Dashboard-Token (HttpOnly, da JWT-Payload)
    setcookie('dashboard_token', $token, [
        'expires' => time() + (24 * 3600),
        'path' => '/assets/php/',
        'secure' => true,
        'httponly' => true,   // ← Nicht JavaScript-lesbar
        'samesite' => 'Strict'
    ]);
    
    // CSRF-Token (NICHT HttpOnly, für JavaScript-Zugriff)
    setcookie('csrf_token', $csrfToken, [
        'expires' => time() + (24 * 3600),
        'path' => '/assets/php/',
        'secure' => true,
        'httponly' => false,  // ← WICHTIG: Muss für Formulare lesbar sein!
        'samesite' => 'Strict'
    ]);
    
    header('Location: dashboard.php');
    exit;
}
```

**Akzeptanzkriterien Phase 1:**
- ✅ Funktion `generateToken()` gibt Array zurück: `[jwt, csrf]`
- ✅ CSRF-Token hat 64 Zeichen (32 Bytes hex)
- ✅ JWT-Payload enthält `csrf` Claim
- ✅ Zwei separate Cookies werden gesetzt
- ✅ `csrf_token` Cookie ist NICHT HttpOnly

---

### Phase 2: Token-Validierung (`dashboard.php` → v2.0)

**Änderungen:**

1. **CSRF-Validierungsfunktion erstellen**

```php
/**
 * Validate CSRF token from POST request
 * 
 * Performs two-stage validation:
 * 1. Cookie value must match POST value (Double Submit Cookie)
 * 2. JWT claim must match Cookie value (Token binding)
 * 
 * @param string $token Dashboard JWT token
 * @param string $secret DASHBOARD_SECRET from .env
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token, $secret) {
    // Token aus Cookie und POST-Daten
    $csrfCookie = $_COOKIE['csrf_token'] ?? '';
    $csrfPost = $_POST['csrf_token'] ?? '';
    
    // Prüfung 1: Cookie und POST müssen übereinstimmen
    if (empty($csrfCookie) || empty($csrfPost)) {
        error_log("CSRF validation failed: Missing token (Cookie: " . 
                  (empty($csrfCookie) ? 'NO' : 'YES') . 
                  ", POST: " . (empty($csrfPost) ? 'NO' : 'YES') . ")");
        return false;
    }
    
    if (!hash_equals($csrfCookie, $csrfPost)) {
        error_log("CSRF validation failed: Cookie/POST mismatch");
        return false;
    }
    
    // Prüfung 2: JWT-Payload muss mit Cookie übereinstimmen
    if (strpos($token, '.') === false) {
        error_log("CSRF validation failed: Invalid JWT format");
        return false;
    }
    
    [$payload, $signature] = explode('.', $token, 2);
    $jwtData = json_decode(base64_decode($payload), true);
    
    if (!isset($jwtData['csrf'])) {
        error_log("CSRF validation failed: No CSRF claim in JWT");
        return false;
    }
    
    if (!hash_equals($jwtData['csrf'], $csrfCookie)) {
        error_log("CSRF validation failed: JWT/Cookie mismatch");
        return false;
    }
    
    // ✅ Alle Prüfungen bestanden
    return true;
}
```

2. **POST-Handler mit CSRF-Prüfung**

```php
// dashboard.php - NACH Token-Verifikation (Zeile ~48)

// CSRF-Validierung VOR jeder POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token validieren
    if (!validateCsrfToken($token, $secret)) {
        http_response_code(403);
        die('CSRF validation failed. Please refresh the page and try again.');
    }
    
    // ✅ CSRF-Validierung erfolgreich - POST-Aktionen erlaubt
    $action = $_POST['action'] ?? '';
    $message = '';
    $type = '';
    
    try {
        switch ($action) {
            case 'block_ip':
                // ... bestehende Logik (unverändert)
                break;
            // ... weitere Cases
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $type = 'error';
    }
    
    // PRG Pattern: Redirect after POST
    // ... bestehende Logik (unverändert)
}
```

3. **Token in HTML-Formulare einfügen**

**Alle Formulare müssen das CSRF-Token enthalten:**

```php
<!-- Block IP Modal Form -->
<form method="POST">
    <!-- ⭐ CSRF-Token als Hidden Field -->
    <input type="hidden" name="csrf_token" 
           value="<?= htmlspecialchars($_COOKIE['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    
    <input type="hidden" name="action" value="block_ip">
    <input type="hidden" name="ip" id="blockIP">
    <!-- ... weitere Felder ... -->
</form>

<!-- Unblock IP Form -->
<form method="POST" style="display: inline;">
    <input type="hidden" name="csrf_token" 
           value="<?= htmlspecialchars($_COOKIE['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="unblock_ip">
    <input type="hidden" name="ip" value="<?= htmlspecialchars($entry['ip']) ?>">
    <button type="submit">Unblock</button>
</form>

<!-- Whitelist IP Form -->
<form method="POST">
    <input type="hidden" name="csrf_token" 
           value="<?= htmlspecialchars($_COOKIE['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="whitelist_ip">
    <!-- ... weitere Felder ... -->
</form>

<!-- Remove from Whitelist Form -->
<form method="POST" style="display: inline;">
    <input type="hidden" name="csrf_token" 
           value="<?= htmlspecialchars($_COOKIE['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="remove_whitelist">
    <input type="hidden" name="ip" value="<?= htmlspecialchars($entry['ip']) ?>">
    <button type="submit">Remove</button>
</form>
```

**Betroffene Stellen im HTML (Zeilen-Nummern aus dashboard.php):**

- ✅ Block IP Modal: Zeile ~635 (Formular)
- ✅ Unblock IP Forms: Zeile ~595 (in Blocklist-Tabelle)
- ✅ Whitelist IP Modal: Zeile ~667 (Formular)
- ✅ Remove Whitelist Forms: Zeile ~635 (in Whitelist-Tabelle)

**Akzeptanzkriterien Phase 2:**
- ✅ Funktion `validateCsrfToken()` implementiert
- ✅ Alle POST-Requests prüfen CSRF-Token
- ✅ Fehlerhafter Token → HTTP 403 + Error-Log
- ✅ Alle 4 Formular-Typen enthalten Hidden Field
- ✅ Token-Wert wird korrekt escaped (`htmlspecialchars`)

---

## 🧪 Testing-Strategie

### Unit-Tests (Lokal)

**Test 1: Token-Generierung**
```php
// test-csrf-generation.php
require_once 'dashboard-login.php';

$secret = 'test_secret_123';
[$jwt, $csrf] = generateToken($secret);

echo "JWT Token: " . substr($jwt, 0, 50) . "...\n";
echo "CSRF Token: " . $csrf . "\n";
echo "CSRF Length: " . strlen($csrf) . " (expected: 64)\n";

// JWT-Payload decodieren
[$payload, $sig] = explode('.', $jwt);
$data = json_decode(base64_decode($payload), true);

echo "JWT contains CSRF: " . (isset($data['csrf']) ? 'YES' : 'NO') . "\n";
echo "JWT CSRF matches: " . ($data['csrf'] === $csrf ? 'YES' : 'NO') . "\n";
```

**Test 2: CSRF-Validierung (Positiv)**
```php
// test-csrf-validation-success.php
require_once 'dashboard.php';

$secret = 'test_secret_123';
[$jwt, $csrf] = generateToken($secret);

// Simuliere Cookies + POST
$_COOKIE['csrf_token'] = $csrf;
$_POST['csrf_token'] = $csrf;

$result = validateCsrfToken($jwt, $secret);
echo "Validation result: " . ($result ? 'PASS ✅' : 'FAIL ❌') . "\n";
```

**Test 3: CSRF-Validierung (Negativ - Cookie/POST Mismatch)**
```php
// test-csrf-validation-fail.php
require_once 'dashboard.php';

$secret = 'test_secret_123';
[$jwt, $csrf] = generateToken($secret);

// Simuliere Angriff: Falscher POST-Token
$_COOKIE['csrf_token'] = $csrf;
$_POST['csrf_token'] = 'fake_token_1234';

$result = validateCsrfToken($jwt, $secret);
echo "Validation result: " . ($result ? 'FAIL ❌' : 'PASS ✅ (correctly rejected)') . "\n";
```

---

### Integration-Tests (Browser)

**Test 4: Login & Cookie-Prüfung**
1. Login: https://jozapf.de/assets/php/dashboard-login.php
2. DevTools → Application → Cookies
3. Prüfen:
   - ✅ `dashboard_token` existiert (HttpOnly: Yes)
   - ✅ `csrf_token` existiert (HttpOnly: No, 64 Zeichen)
   - ✅ Beide Cookies haben selbe `Expires` Zeit

**Test 5: Formular-Inspektion**
1. Dashboard öffnen
2. DevTools → Elements → Inspect Forms
3. Prüfen:
   - ✅ Alle 4 Formular-Typen haben `<input name="csrf_token">`
   - ✅ Token-Wert entspricht Cookie-Wert
   - ✅ Keine XSS-Anfälligkeit (`htmlspecialchars` korrekt?)

**Test 6: POST-Request (Erfolg)**
1. IP blocken über Dashboard-UI
2. Network-Tab prüfen:
   - ✅ Request-Payload enthält `csrf_token`
   - ✅ Response: HTTP 302 (Redirect - PRG Pattern)
   - ✅ Blocklist aktualisiert sich

**Test 7: POST-Request (Fehlschlag - Manipulierter Token)**
```bash
# Terminal-Test mit cURL
curl -X POST https://jozapf.de/assets/php/dashboard.php \
     -H "Cookie: dashboard_token=VALID_JWT; csrf_token=VALID_CSRF" \
     -d "action=block_ip&ip=1.2.3.4&csrf_token=FAKE_TOKEN"
```
**Erwartung:** HTTP 403 + "CSRF validation failed"

---

### Security-Tests (Penetration)

**Test 8: CSRF-Angriff simulieren**

1. Bösartige HTML-Datei erstellen:
```html
<!-- csrf-attack.html -->
<!DOCTYPE html>
<html>
<body>
<h1>CSRF Attack Simulation</h1>
<form id="attack" action="https://jozapf.de/assets/php/dashboard.php" method="POST">
    <input type="hidden" name="action" value="block_ip">
    <input type="hidden" name="ip" value="8.8.8.8">
    <input type="hidden" name="reason" value="Attack Test">
    <input type="hidden" name="duration" value="permanent">
    <!-- ⚠️ Kein CSRF-Token! -->
</form>
<script>
    console.log('Submitting attack form...');
    document.getElementById('attack').submit();
</script>
</body>
</html>
```

2. Test-Ablauf:
   - Im Browser: Dashboard einloggen
   - Neuer Tab: `csrf-attack.html` öffnen (lokal)
   - Formular wird automatisch abgeschickt

**Erwartung:**
- ❌ Aktion wird NICHT ausgeführt (HTTP 403)
- ✅ IP 8.8.8.8 ist NICHT in Blocklist
- ✅ Error-Log enthält "CSRF validation failed: Missing token"

**Test 9: Double Submit Cookie Bypass (sollte fehlschlagen)**
```bash
# Angreifer versucht, Cookie + POST zu fälschen
curl -X POST https://jozapf.de/assets/php/dashboard.php \
     -H "Cookie: dashboard_token=VALID_JWT; csrf_token=attacker_token" \
     -d "action=block_ip&ip=1.2.3.4&csrf_token=attacker_token"
```
**Erwartung:** HTTP 403 (weil JWT-Claim nicht mit gefälschtem Token übereinstimmt)

---

## 📝 Deployment-Checkliste

### Pre-Deployment

- [ ] **Code-Review durchgeführt**
  - [ ] `dashboard-login.v2.php` geprüft
  - [ ] `dashboard.v2.php` geprüft
  - [ ] Alle Formulare haben CSRF-Token
  
- [ ] **Lokale Tests bestanden**
  - [ ] Test 1-3: Unit-Tests erfolgreich
  - [ ] Keine PHP-Syntax-Fehler
  
- [ ] **Backup erstellt**
  ```bash
  cp dashboard-login.php dashboard-login.backup-$(date +%s)
  cp dashboard.php dashboard.php.backup-$(date +%s)
  ```

### Deployment

- [ ] **Dateien hochladen**
  ```bash
  # Via SFTP/SCP
  scp dashboard-login.v2.php user@jozapf.de:/var/www/jozapf.de/assets/php/
  scp dashboard.v2.php user@jozapf.de:/var/www/jozapf.de/assets/php/
  ```

- [ ] **Alte Dateien umbenennen**
  ```bash
  ssh user@jozapf.de
  cd /var/www/jozapf.de/assets/php/
  mv dashboard-login.php dashboard-login.v1.backup
  mv dashboard.php dashboard.v1.backup
  ```

- [ ] **Neue Dateien aktivieren**
  ```bash
  mv dashboard-login.v2.php dashboard-login.php
  mv dashboard.v2.php dashboard.php
  ```

- [ ] **Dateirechte prüfen**
  ```bash
  chmod 644 dashboard-login.php dashboard.php
  chown www-data:www-data dashboard-login.php dashboard.php
  ```

### Post-Deployment Testing

- [ ] **Browser-Tests (Test 4-6)**
  - [ ] Login funktioniert
  - [ ] Cookies werden korrekt gesetzt
  - [ ] Dashboard-Funktionen arbeiten normal
  
- [ ] **Security-Tests (Test 7-9)**
  - [ ] CSRF-Angriff wird blockiert
  - [ ] Error-Logs werden geschrieben
  - [ ] Keine False-Positives

- [ ] **Monitoring (24h)**
  - [ ] Error-Logs prüfen: `tail -f /var/log/php-errors.log`
  - [ ] Dashboard-Usage überwachen
  - [ ] User-Feedback sammeln

### Rollback-Plan

**Falls Probleme auftreten:**

```bash
# Schneller Rollback (< 1 Minute)
ssh user@jozapf.de
cd /var/www/jozapf.de/assets/php/
mv dashboard-login.php dashboard-login.v2.broken
mv dashboard.php dashboard.v2.broken
mv dashboard-login.v1.backup dashboard-login.php
mv dashboard.v1.backup dashboard.php
systemctl reload php8.2-fpm  # Falls nötig
```

**Rollback-Trigger:**
- ❌ Login nicht möglich
- ❌ Dashboard-Funktionen defekt
- ❌ Formular-Submissions schlagen fehl (False-Positives)
- ❌ Kritische PHP-Fehler in Logs

---

## 📊 Risiko-Reduktion

**Vor AP-02:**
- 🔴 CSRF-Angriffe: 100% erfolgreich
- 🔴 Session Riding: Ungeschützt
- 🔴 Admin-Aktionen: Keine Validierung

**Nach AP-02:**
- 🟢 CSRF-Angriffe: ~98% blockiert*
- 🟢 Session Riding: Effektiv verhindert
- 🟢 Admin-Aktionen: Double-Validated

*Rest-Risiko: Browser-Bugs, Zero-Day-Exploits (< 2%)

**Geschätzte Risiko-Reduktion: ~95%**

---

## 🔗 Abhängigkeiten & Kompatibilität

### Kompatibel mit:
- ✅ AP-01 (Dashboard-API Authentication)
- ✅ Bestehendes HMAC-Token-System
- ✅ PRG-Pattern (Post-Redirect-Get)
- ✅ ExtendedLogger
- ✅ BlocklistManager

### Inkompatibel mit:
- ❌ Ältere Browser ohne SameSite-Cookie-Support (< 2020)
- ❌ Clients mit deaktivierten Cookies

### Abhängigkeiten:
- PHP >= 7.4 (random_bytes, hash_equals)
- OpenSSL-Extension (für HMAC)
- Cookies aktiviert

---

## 📚 Referenzen

**OWASP Guidelines:**
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Double Submit Cookie Pattern](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#double-submit-cookie)

**12-Factor App:**
- [Factor III: Config](https://12factor.net/config)
- [Factor VI: Processes (Stateless)](https://12factor.net/processes)

**Security Best Practices:**
- [PHP Security Guide: CSRF](https://phpsecurity.readthedocs.io/en/latest/Cross-Site-Request-Forgery-(CSRF).html)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)

---

## 📝 Changelog

### v1.0.0 (2025-10-05)
- Initial runbook creation
- Defined CSRF protection strategy
- Detailed implementation plan
- Comprehensive testing strategy

---

## ✅ Sign-Off

**Erstellt von:** Jo Zapf  
**Geprüft von:** _________________  
**Genehmigt von:** _________________  
**Datum:** _________________

---

**Ende AP-02 Runbook**
