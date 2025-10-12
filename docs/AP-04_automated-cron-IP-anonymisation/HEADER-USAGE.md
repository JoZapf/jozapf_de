# Script-Header: Lokale vs. GitHub Versionen

## 📄 Zwei Versionen erstellt

### 1. **HEADER-LOCAL.php** (Für Ihren Server)
- ✅ Mit Ihren **echten Pfaden** (`/jozapf-de`)
- ✅ Mit Ihrem **Autor-Namen** (JoZapf)
- ✅ Mit **spezifischen Pfad-Beispielen**
- ✅ Für `anonymize-logs.php` auf Ihrem Server

### 2. **HEADER-GITHUB.php** (Für GitHub)
- ✅ **Anonymisiert** - generische Pfade
- ✅ **Generischer Autor** (Contact Form Project Contributors)
- ✅ Mit **Anpassungs-Hinweisen** für andere Nutzer
- ✅ Für GitHub Repository

---

## 🔄 Header ersetzen - Anleitung

### Für Ihren lokalen Server:

**Datei:** `anonymize-logs.php` (Zeilen 1-60)

**Ersetzen Sie die Zeilen 1-60 mit:**
```php
[Kompletter Inhalt von HEADER-LOCAL.php]
```

**Ab Zeile 61 weiter mit:**
```php
// Verify paths
if (!is_dir(PROJECT_ROOT)) {
    fwrite(STDERR, "ERROR: Project root not found: " . PROJECT_ROOT . "\n");
    ...
}
```

### Für GitHub:

**Datei:** `anonymize-logs.php` (vor dem Commit)

**Ersetzen Sie die Zeilen 1-60 mit:**
```php
[Kompletter Inhalt von HEADER-GITHUB.php]
```

Dann committen!

---

## 📋 Unterschiede auf einen Blick

| Element | LOCAL | GITHUB |
|---------|-------|--------|
| **Autor** | `@author JoZapf` | `@author Contact Form Project Contributors` |
| **Copyright** | `@copyright 2025 JoZapf` | `@copyright 2025` |
| **Pfad-Beispiel** | `/usr/home/users/cron/...` | `/path/to/cron/...` |
| **PROJECT_ROOT** | `/jozapf-de` | `/your-project-name` ← CUSTOMIZE |
| **PHP-Binary** | `/usr/bin/php83` | `/usr/bin/php` |
| **Cron-Beispiel** | Spezifisch für Hetzner | Generisch |

---

## ✨ Neue Header-Features

### Verbesserte Dokumentation:

```php
/**
 * Features:
 *   - Relative path resolution (portable across servers)
 *   - GDPR-compliant auto-anonymization (Art. 5 (1) e)
 *   - Comprehensive logging with audit trail
 *   - Email notifications on errors
 *   - Safe fail-fast error handling
 */
```

### Mehrere Cron-Beispiele:

```php
 * Recommended Schedules:
 *   Daily at 3:00 AM:    0 3 * * *      (recommended)
 *   Twice daily:         0 3,15 * * *
 *   Every 6 hours:       0 */6 * * *
 *   Weekly (Sundays):    0 2 * * 0
```

### Pfad-Auflösung erklärt:

```php
 * Path Resolution:
 *   Uses relative paths for portability:
 *   CRON_DIR (__DIR__) → ../../ → PUBLIC_HTML → /jozapf-de → PROJECT_ROOT
```

### Log-Pfade dokumentiert:

```php
 * Logs:
 *   Execution log:        /path/to/project/assets/php/logs/cron-anonymization.log
 *   Anonymization audit:  /path/to/project/assets/php/logs/anonymization_history.log
```

---

## 🎯 Anwendung in beiden Scripts

**Die gleichen Header sollten auch in verwendet werden:**
1. `anonymize-logs.php` (Hauptscript)
2. `test-anonymization.php` (Test-Script)

**Anpassungen für `test-anonymization.php`:**
- Purpose: "Test and validate..." statt "Automatically anonymize..."
- Usage: Nur "Manually: php test-anonymization.php"
- Keine Cronjob-Konfiguration (wird nicht per Cron ausgeführt)

---

## 📝 Wichtige Hinweise

### GITHUB-Version:

Der Kommentar in der GitHub-Version hilft anderen Nutzern:

```php
// ⚠️ CUSTOMIZE THIS: Set your project folder name
define('PROJECT_ROOT', $PUBLIC_HTML . '/your-project-name');  // ← CHANGE THIS!

// Common structures:
// - Shared hosting:  $PUBLIC_HTML . '/public_html/your-site'
// - VPS/Dedicated:   $PUBLIC_HTML . '/var/www/your-domain.com'
// - Direct webroot:  $PUBLIC_HTML (no subfolder)
```

### LOCAL-Version:

Enthält Ihre spezifischen Pfade ohne Platzhalter:

```php
define('PROJECT_ROOT', $PUBLIC_HTML . '/jozapf-de');  // ← Customize this for your project
define('PHP_DIR',      PROJECT_ROOT . '/assets/php');
define('LOG_DIR',      PHP_DIR . '/logs');
define('CRON_LOG',     LOG_DIR . '/cron-anonymization.log');
```

---

## ✅ Checkliste

### Für Ihren Server:
- [ ] `anonymize-logs.php` Header mit HEADER-LOCAL.php ersetzen
- [ ] `test-anonymization.php` Header entsprechend anpassen
- [ ] Pfade prüfen (sollten zu `/jozapf-de` führen)
- [ ] Manuell testen: `php anonymize-logs.php`

### Für GitHub:
- [ ] `anonymize-logs.php` Header mit HEADER-GITHUB.php ersetzen
- [ ] `test-anonymization.php` Header entsprechend anpassen
- [ ] Alle spezifischen Pfade/Namen entfernt
- [ ] README-GITHUB.md → README.md umbenennen
- [ ] Committen & pushen

---

## 📊 Version & Changelog

Beide Header enthalten jetzt:

```php
 * @version    3.0.0
 * @since      2025-10-06
 * 
 * @changelog
 *   3.0.0 (2025-10-06) - Relative path resolution, portable solution
 *   2.0.0 (2025-10-06) - Initial cronjob implementation
```

Dies dokumentiert die Evolution des Scripts!

---

**Fertig! Beide Header-Versionen sind bereit zum Einsatz.** ✅
