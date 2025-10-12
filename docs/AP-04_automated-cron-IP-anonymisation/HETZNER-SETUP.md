# ✅ Einrichtung im Hetzner Cron-Ordner

Sie haben es **richtig** gemacht! `/usr/home/jozapf/cron/` ist genau die Hetzner-Empfehlung.

## 📁 Ihre Verzeichnisstruktur

```
/usr/home/jozapf/
├── site/                                    # Ihr Webroot
│   └── jozapf-de/
│       └── ContactFormForGithub/
│           └── assets/php/                  # PHP-Dateien & Logs
│               ├── .env.prod
│               ├── ExtendedLogger.php
│               └── logs/
│
└── cron/                                    # ✅ IHR NEUER ORDNER
    └── contactform/                         # ← Hier kommen die Scripts hin
        ├── anonymize-logs.php
        ├── test-anonymization.php
        └── README.md
```

---

## 🚀 Schritt-für-Schritt Einrichtung

### 1️⃣ Unterordner erstellen

```bash
cd /usr/home/jozapf/cron
mkdir contactform
cd contactform
```

### 2️⃣ Scripts hochladen/kopieren

**Option A: Von lokalem Rechner hochladen (SFTP/SCP)**

Die zwei angepassten Scripts befinden sich in Ihrem Projekt:
- `ContactFormForGithub/cron/anonymize-logs-HETZNER.php`
- `ContactFormForGithub/cron/test-anonymization-HETZNER.php`

Laden Sie diese hoch nach `/usr/home/jozapf/cron/contactform/` und benennen Sie um:
```bash
# Nach dem Upload:
cd /usr/home/jozapf/cron/contactform
mv anonymize-logs-HETZNER.php anonymize-logs.php
mv test-anonymization-HETZNER.php test-anonymization.php
```

**Option B: Direkt auf dem Server erstellen**

```bash
cd /usr/home/jozapf/cron/contactform

# Script 1: anonymize-logs.php erstellen
nano anonymize-logs.php
# Inhalt aus anonymize-logs-HETZNER.php einfügen, speichern

# Script 2: test-anonymization.php erstellen
nano test-anonymization.php
# Inhalt aus test-anonymization-HETZNER.php einfügen, speichern
```

### 3️⃣ Berechtigungen setzen

```bash
cd /usr/home/jozapf/cron/contactform
chmod +x anonymize-logs.php
chmod +x test-anonymization.php
```

### 4️⃣ Test-Script ausführen

```bash
php test-anonymization.php
```

**Erwartete Ausgabe:**
```
======================================================================
  ContactForm GDPR Anonymization - Test Script (Hetzner)
======================================================================

PHP Version: 8.3.0
User: jozapf
Cron Directory: /usr/home/jozapf/cron/contactform
Project Root: /usr/home/jozapf/site/jozapf-de/ContactFormForGithub

======================================================================
  TEST 0: Checking Hetzner Directory Structure
======================================================================

✓ User home directory exists: /usr/home/jozapf
✓ Project root exists: /usr/home/jozapf/site/jozapf-de/ContactFormForGithub
✓ Cron directory exists: /usr/home/jozapf/cron/contactform

...

======================================================================
  Test Summary
======================================================================

✓ ALL TESTS PASSED!

✓ The anonymization cronjob is ready to be set up.

Next Steps:
  1. Test manual execution: php /usr/home/jozapf/cron/contactform/anonymize-logs.php
  2. Set up cronjob in Hetzner Console:
     0 3 * * * php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php
  3. Monitor cronjob execution in: /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log
```

### 5️⃣ Manuellen Durchlauf testen

```bash
php anonymize-logs.php
```

**Log prüfen:**
```bash
tail -n 30 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log
```

**Erwartete Ausgabe:**
```
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] === Anonymization Cronjob Started ===
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] PHP Version: 8.3.0
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] User: jozapf
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Cron Directory: /usr/home/jozapf/cron/contactform
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Project Root: /usr/home/jozapf/site/jozapf-de/ContactFormForGithub
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Log Directory: /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Initializing ExtendedLogger...
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Retention Period: 14 days
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Scanning for entries older than 14 days...
[2025-10-06T15:30:02+00:00] [SUCCESS] [PID:12345] ✓ Anonymized X entries
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345] Log Statistics (30 days):
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345]   - Total submissions: XX
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345]   - Blocked: X
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345]   - Allowed: XX
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345]   - Avg Spam Score: X.XX
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345] === Cronjob Completed Successfully in 0.145s ===
```

---

## 🎯 Cronjob in Hetzner Console einrichten

### Im Hetzner Web-Interface

1. **Einloggen:** https://konsoleh.hostingkunde.de
2. **Navigation:** Menü → Cronjobs
3. **Button:** "Neuer Cronjob" klicken
4. **Interpreter:** Wie im Screenshot - "PHP Interpreter" wählen (z.B. PHP 8.3)

### Cronjob-Zeile

**Genau diese Zeile eintragen:**

```
0 3 * * * php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php
```

**Erklärung:**
```
Minute: 0     (Punkt um :00)
Stunde: 3     (3:00 Uhr nachts)
Tag:    *     (Jeden Tag)
Monat:  *     (Jeden Monat)
Wochentag: *  (Jeden Wochentag)

Interpreter: php8.3
Script:      /usr/home/jozapf/cron/contactform/anonymize-logs.php
```

**Bedeutung:** Täglich um 3:00 Uhr nachts

---

## 📋 Alternative Zeitpläne

```bash
# Täglich 3:00 Uhr (EMPFOHLEN - GDPR-konform)
0 3 * * * php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php

# Zweimal täglich (3:00 und 15:00)
0 3,15 * * * php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php

# Alle 6 Stunden (0:00, 6:00, 12:00, 18:00)
0 */6 * * * php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php

# Wöchentlich Sonntags 2:00 Uhr
0 2 * * 0 php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php
```

---

## 🔍 Monitoring

### Log-Befehle

```bash
# Cronjob-Log anzeigen (letzte 50 Zeilen)
tail -n 50 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log

# Live-Monitoring während Ausführung
tail -f /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log

# Nur Fehler anzeigen
grep ERROR /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log

# Erfolgreiche Ausführungen zählen
grep "Completed Successfully" /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log | wc -l

# Anonymisierungs-Historie
tail -n 20 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/anonymization_history.log
```

### Erste Ausführung prüfen

**Am nächsten Morgen (nach 3:00 Uhr):**

```bash
# Log der letzten Cronjob-Ausführung anzeigen
tail -n 100 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log | grep "2025-10-07"
```

---

## 🆘 Troubleshooting

### Problem: Test-Script findet Project Root nicht

**Fehlermeldung:**
```
✗ Project root NOT FOUND: /usr/home/jozapf/site/jozapf-de/ContactFormForGithub
```

**Lösung:**

Prüfen Sie Ihre tatsächliche Verzeichnisstruktur:

```bash
ls -la /usr/home/jozapf/
ls -la /usr/home/jozapf/site/
```

Falls Ihr Webroot anders heißt (z.B. `public_html`, `html`, `www`), passen Sie die Scripts an:

**In beiden Scripts ändern (Zeile 34):**
```php
// VORHER:
define('PROJECT_ROOT', USER_HOME . '/site/jozapf-de/ContactFormForGithub');

// NACHHER (wenn Ihr Webroot z.B. "public_html" heißt):
define('PROJECT_ROOT', USER_HOME . '/public_html/jozapf-de/ContactFormForGithub');
```

### Problem: "Permission denied"

```bash
chmod +x /usr/home/jozapf/cron/contactform/anonymize-logs.php
chmod +x /usr/home/jozapf/cron/contactform/test-anonymization.php
```

### Problem: "Log directory not writable"

```bash
chmod 755 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/
chown -R www-data:www-data /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/
```

### Problem: PHP-Version nicht gefunden

```bash
# Verfügbare PHP-Versionen anzeigen
ls -la /usr/bin/php*

# Output könnte sein:
# /usr/bin/php8.1
# /usr/bin/php8.2
# /usr/bin/php8.3

# Im Cronjob die richtige Version verwenden:
0 3 * * * php8.2 /usr/home/jozapf/cron/contactform/anonymize-logs.php
```

---

## ✅ Checkliste

- [ ] Ordner `/usr/home/jozapf/cron/contactform/` erstellt
- [ ] Scripts hochgeladen/erstellt
- [ ] Berechtigungen gesetzt (`chmod +x`)
- [ ] Test-Script erfolgreich (`php test-anonymization.php`)
- [ ] Manueller Durchlauf erfolgreich (`php anonymize-logs.php`)
- [ ] Log geprüft (keine Fehler)
- [ ] Cronjob in Hetzner Console eingerichtet
- [ ] Nach 24h erste automatische Ausführung geprüft
- [ ] Email-Benachrichtigungen in Hetzner aktiviert

---

## 🎯 Vorteile Ihrer Struktur

✅ **Sicherheit:** Scripts außerhalb des Webroots  
✅ **Hetzner Best Practice:** Genau wie empfohlen  
✅ **Organisation:** Klare Trennung Cron ↔ Web  
✅ **Skalierbar:** Weitere Cronjobs einfach hinzufügbar

**Beispiel für weitere Cronjobs:**
```
/usr/home/jozapf/cron/
├── contactform/
│   └── anonymize-logs.php
├── backup/
│   └── daily-backup.php
└── maintenance/
    └── cleanup.php
```

---

## 📚 Weiterführende Dokumentation

- **Hetzner Cron-Doku:** In der Hetzner Console unter "Hilfe"
- **Cron-Syntax:** https://crontab.guru/
- **GDPR-Compliance:** Projekt-README.md

---

**Viel Erfolg! Bei Fragen melden Sie sich.** 🚀
