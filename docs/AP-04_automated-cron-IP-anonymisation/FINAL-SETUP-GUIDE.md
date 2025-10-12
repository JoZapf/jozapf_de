# ⚡ FINALE Anleitung - Ihre echten Hetzner-Pfade

## ✅ Ihre korrekte Verzeichnisstruktur

```
/usr/home/users/
└── cron/
    └── contactform/                           ← Cronjob-Scripts hier
        ├── anonymize-logs.php
        └── test-anonymization.php

/usr/www/users/jozapf/
└── jozapf-de/                                 ← IHR PROJEKT
    ├── index.html
    └── assets/
        └── php/
            ├── .env.prod
            ├── ExtendedLogger.php
            └── logs/                          ← Logs werden hier gespeichert
                ├── detailed_submissions.log
                ├── anonymization_history.log
                └── cron-anonymization.log     ← Neu angelegt
```

---

## 🚀 Schritt-für-Schritt (5 Minuten)

### 1️⃣ Cronjob-Ordner erstellen

```bash
ssh zu-ihrem-server

# Ordner anlegen
mkdir -p /usr/home/users/cron/contactform
cd /usr/home/users/cron/contactform
```

### 2️⃣ Scripts hochladen

**Die zwei FINALEN Scripts aus Ihrem Projekt:**
- `ContactFormForGithub/cron/anonymize-logs-FINAL.php`
- `ContactFormForGithub/cron/test-anonymization-FINAL.php`

**Hochladen nach:**
- `/usr/home/users/cron/contactform/`

**Dann umbenennen:**
```bash
cd /usr/home/users/cron/contactform
mv anonymize-logs-FINAL.php anonymize-logs.php
mv test-anonymization-FINAL.php test-anonymization.php
chmod +x *.php
```

### 3️⃣ Test ausführen

```bash
cd /usr/home/users/cron/contactform
php test-anonymization.php
```

**Erwartete Ausgabe:**
```
======================================================================
  ContactForm GDPR Anonymization - Test Script (Final)
======================================================================

PHP Version: 8.3.X
User: jozapf
Cron Directory: /usr/home/users/cron/contactform
Web Root: /usr/www/users/jozapf
Project Root: /usr/www/users/jozapf/jozapf-de

...

✓ ALL TESTS PASSED!

✓ The anonymization cronjob is ready to be set up.
```

### 4️⃣ Manueller Durchlauf

```bash
php anonymize-logs.php
```

**Log prüfen:**
```bash
tail -n 30 /usr/www/users/jozapf/jozapf-de/assets/php/logs/cron-anonymization.log
```

**Erwartete Ausgabe:**
```
[2025-10-06T...] [INFO] [PID:...] === Anonymization Cronjob Started ===
[2025-10-06T...] [INFO] [PID:...] PHP Version: 8.3.X
[2025-10-06T...] [INFO] [PID:...] User: jozapf
[2025-10-06T...] [INFO] [PID:...] Cron Directory: /usr/home/users/cron/contactform
[2025-10-06T...] [INFO] [PID:...] Project Root: /usr/www/users/jozapf/jozapf-de
[2025-10-06T...] [INFO] [PID:...] Retention Period: 14 days
[2025-10-06T...] [SUCCESS] [PID:...] ✓ Anonymized X entries
[2025-10-06T...] [INFO] [PID:...] === Cronjob Completed Successfully in 0.XXXs ===
```

### 5️⃣ Cronjob in Hetzner einrichten

**Im Hetzner Web-Interface:**
1. Einloggen: https://konsoleh.hostingkunde.de
2. Menü → **Cronjobs**
3. **"Neuer Cronjob"**
4. Interpreter: **Anderer Interpreter/Direktaufruf** wählen

**EXAKT DIESE ZEILE eintragen:**

```
0 3 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
```

**Wichtig:**
- ✅ `/usr/bin/php83` (Ihr PHP-Binary)
- ✅ `/usr/home/users/cron/contactform/` (Ihr Cron-Ordner)
- ✅ `0 3 * * *` (Täglich um 3:00 Uhr)

---

## 📊 Monitoring

### Nach 24h (erste automatische Ausführung)

```bash
# Cronjob-Log anzeigen
tail -n 50 /usr/www/users/jozapf/jozapf-de/assets/php/logs/cron-anonymization.log

# Nur Erfolge
grep "Completed Successfully" /usr/www/users/jozapf/jozapf-de/assets/php/logs/cron-anonymization.log

# Nur Fehler
grep ERROR /usr/www/users/jozapf/jozapf-de/assets/php/logs/cron-anonymization.log

# Live-Monitoring
tail -f /usr/www/users/jozapf/jozapf-de/assets/php/logs/cron-anonymization.log
```

### Anonymisierungs-Historie prüfen

```bash
tail -n 20 /usr/www/users/jozapf/jozapf-de/assets/php/logs/anonymization_history.log
```

---

## 🔧 Alternative Zeitpläne

```bash
# Täglich 3:00 Uhr (EMPFOHLEN)
0 3 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Zweimal täglich (3:00 und 15:00)
0 3,15 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Alle 6 Stunden
0 */6 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Wöchentlich Sonntags 2:00
0 2 * * 0 /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
```

---

## ✅ Checkliste

- [ ] Ordner `/usr/home/users/cron/contactform/` erstellt
- [ ] Scripts `*-FINAL.php` hochgeladen und umbenannt
- [ ] Berechtigungen gesetzt (`chmod +x *.php`)
- [ ] Test-Script erfolgreich (`php test-anonymization.php`)
- [ ] Manueller Durchlauf erfolgreich (`php anonymize-logs.php`)
- [ ] Log geprüft (keine Fehler in cron-anonymization.log)
- [ ] Cronjob in Hetzner Console mit EXAKTER Zeile eingerichtet
- [ ] Nach 24h erste automatische Ausführung geprüft

---

## 🆘 Troubleshooting

### Problem: "Project root not found"

```bash
# Prüfen Sie die Struktur:
ls -la /usr/www/users/jozapf/
ls -la /usr/www/users/jozapf/jozapf-de/
ls -la /usr/www/users/jozapf/jozapf-de/assets/php/

# ExtendedLogger.php sollte existieren:
ls -la /usr/www/users/jozapf/jozapf-de/assets/php/ExtendedLogger.php
```

### Problem: "Permission denied"

```bash
chmod +x /usr/home/users/cron/contactform/anonymize-logs.php
chmod +x /usr/home/users/cron/contactform/test-anonymization.php
```

### Problem: "Log directory not writable"

```bash
chmod 755 /usr/www/users/jozapf/jozapf-de/assets/php/logs/
```

### Problem: PHP nicht gefunden

```bash
# PHP-Version prüfen
which php83
/usr/bin/php83 --version

# Falls php83 nicht existiert:
ls -la /usr/bin/php*
# Dann die richtige Version im Cronjob verwenden (z.B. php82, php81)
```

---

## 🎯 Was der Cronjob macht

```
Tag 0-13:  IP: 192.168.1.100  (Vollständig für Spam-Analyse)
Tag 14:    IP: 192.168.1.100  (Letzter Tag vollständig)
Tag 15+:   IP: 192.168.1.XXX  (Automatisch anonymisiert)
```

✅ **GDPR-konform** (Art. 5 (1) e DSGVO)  
✅ **Automatisch** (täglich um 3:00 Uhr)  
✅ **Audit-Trail** (jede Anonymisierung protokolliert)  
✅ **Email-Benachrichtigung** bei Fehlern

---

## 📚 Zusammenfassung der Pfade

| Was | Pfad |
|-----|------|
| **Cronjob-Scripts** | `/usr/home/users/cron/contactform/` |
| **Web-Projekt** | `/usr/www/users/jozapf/jozapf-de/` |
| **PHP-Dateien** | `/usr/www/users/jozapf/jozapf-de/assets/php/` |
| **Logs** | `/usr/www/users/jozapf/jozapf-de/assets/php/logs/` |
| **PHP-Binary** | `/usr/bin/php83` |

---

## 🎉 Fertig!

Nach dem Einrichten läuft der Cronjob täglich automatisch und anonymisiert alte IP-Adressen GDPR-konform.

**Bei Problemen:** Siehe Troubleshooting oder kontaktieren Sie mich.

---

**Viel Erfolg! 🚀**
