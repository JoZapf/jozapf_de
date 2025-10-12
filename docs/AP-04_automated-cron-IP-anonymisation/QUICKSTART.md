# Quick Start: Cronjob in Hetzner einrichten

## ⚡ Schnellanleitung (5 Minuten)

### Schritt 1: Login-Name herausfinden

```bash
ssh zu-ihrem-server
whoami
```

**Ergebnis notieren:** z.B. `u12345678`

---

### Schritt 2: Script testen

```bash
cd /usr/home/IHR-LOGIN/site/jozapf-de/ContactFormForGithub/cron

# Berechtigungen setzen
chmod +x anonymize-logs.php
chmod +x test-anonymization.php

# Test ausführen
php test-anonymization.php
```

**Erwartete Ausgabe:** "ALL TESTS PASSED! ✓"

---

### Schritt 3: Manuellen Test-Durchlauf

```bash
php anonymize-logs.php
```

**Log prüfen:**
```bash
tail -n 20 ../assets/php/logs/cron-anonymization.log
```

**Erwartete Ausgabe:**
```
[2025-10-06T03:00:01+00:00] [INFO] [PID:12345] === Anonymization Cronjob Started ===
[2025-10-06T03:00:01+00:00] [SUCCESS] [PID:12345] ✓ Anonymized X entries
[2025-10-06T03:00:01+00:00] [INFO] [PID:12345] === Cronjob Completed Successfully ===
```

---

### Schritt 4: Cronjob in Hetzner Console einrichten

**Im Hetzner Web-Interface:**

1. **Einloggen:** https://konsoleh.hostingkunde.de
2. **Navigation:** Menü → Cronjobs
3. **Button:** "Neuer Cronjob" klicken
4. **Interpreter wählen:** PHP Interpreter auswählen (z.B. "PHP 8.3")

**Cronjob-Zeile eintragen:**

```
0 3 * * * php8.3 /usr/home/IHR-LOGIN/site/jozapf-de/ContactFormForGithub/cron/anonymize-logs.php
```

**WICHTIG:** `IHR-LOGIN` durch Ihren tatsächlichen Login ersetzen!

**Bedeutung:** Täglich um 3:00 Uhr nachts

5. **Speichern** klicken

---

### Schritt 5: Erste Ausführung abwarten

**Am nächsten Morgen prüfen:**

```bash
tail -n 50 /usr/home/IHR-LOGIN/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log
```

**Falls Probleme:**
- Email-Benachrichtigung von Hetzner prüfen
- Hetzner Console → Cronjobs → Log anzeigen

---

## 🎯 Fertig!

Der Cronjob ist jetzt aktiv und läuft täglich automatisch.

### Monitoring-Befehle

```bash
# Cronjob-Log live verfolgen
tail -f /pfad/zu/cron-anonymization.log

# Nur Fehler anzeigen
grep ERROR /pfad/zu/cron-anonymization.log

# Erfolgreiche Ausführungen zählen
grep "Completed Successfully" /pfad/zu/cron-anonymization.log | wc -l

# Anonymisierungs-Historie
tail -n 20 /pfad/zu/anonymization_history.log
```

---

## 📋 Zeitpläne (Alternativen)

Ändern Sie die Cron-Syntax für andere Zeitpläne:

```bash
# Täglich um 3:00 Uhr (Standard)
0 3 * * * php8.3 /pfad/zum/script.php

# Zweimal täglich (3:00 und 15:00 Uhr)
0 3,15 * * * php8.3 /pfad/zum/script.php

# Alle 6 Stunden
0 */6 * * * php8.3 /pfad/zum/script.php

# Wöchentlich Sonntags um 2:00 Uhr
0 2 * * 0 php8.3 /pfad/zum/script.php
```

---

## 🆘 Troubleshooting

### Problem: Script läuft nicht

**1. Manuell testen:**
```bash
php /voller/pfad/zum/anonymize-logs.php
echo $?  # Muss 0 sein!
```

**2. Berechtigungen prüfen:**
```bash
ls -la /pfad/zum/anonymize-logs.php
# Sollte: -rwxr-xr-x sein
```

**3. PHP-Version prüfen:**
```bash
which php8.3
php8.3 --version
```

### Problem: "Permission denied"

```bash
chmod +x /pfad/zum/anonymize-logs.php
```

### Problem: "Log directory not writable"

```bash
chmod 755 /pfad/zu/logs/
chown -R www-data:www-data /pfad/zu/logs/
```

### Problem: Keine Email-Benachrichtigungen

**In Hetzner Console:**
- Account → Einstellungen
- Email für Cronjobs aktivieren

---

## ✅ Checkliste

- [ ] Login-Name herausgefunden
- [ ] Test-Script erfolgreich ausgeführt
- [ ] Manueller Durchlauf erfolgreich
- [ ] Cronjob in Hetzner eingerichtet
- [ ] Nach 24h Log geprüft
- [ ] Email-Benachrichtigungen aktiviert

---

**Bei Fragen:** Siehe vollständige `README.md` im cron-Verzeichnis.
