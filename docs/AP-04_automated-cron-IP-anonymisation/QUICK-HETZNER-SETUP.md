# ⚡ Schnellanleitung: Cronjob in 5 Minuten einrichten

## 📋 Was Sie brauchen

- ✅ Sie haben `/usr/home/jozapf/cron/` erstellt (PERFEKT!)
- ✅ SSH-Zugang zum Server
- ✅ 5 Minuten Zeit

---

## 🚀 Los geht's!

### 1️⃣ Scripts hochladen (2 Minuten)

**Per SFTP/SCP auf Ihren Server hochladen:**

Quelle (von Ihrem Projekt):
```
ContactFormForGithub/cron/anonymize-logs-HETZNER.php
ContactFormForGithub/cron/test-anonymization-HETZNER.php
```

Ziel (auf dem Server):
```
/usr/home/jozapf/cron/contactform/anonymize-logs.php
/usr/home/jozapf/cron/contactform/test-anonymization.php
```

**Oder direkt auf dem Server:**

```bash
mkdir -p /usr/home/jozapf/cron/contactform
cd /usr/home/jozapf/cron/contactform

# Scripts erstellen (Inhalt aus den -HETZNER.php Dateien kopieren)
nano anonymize-logs.php
nano test-anonymization.php

chmod +x *.php
```

---

### 2️⃣ Testen (1 Minute)

```bash
cd /usr/home/jozapf/cron/contactform

# Test-Script ausführen
php test-anonymization.php
```

**Erwartete Ausgabe:** "✓ ALL TESTS PASSED!"

---

### 3️⃣ Manueller Durchlauf (1 Minute)

```bash
php anonymize-logs.php

# Log prüfen
tail -n 20 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log
```

**Erwartete Ausgabe:** "=== Cronjob Completed Successfully ==="

---

### 4️⃣ Cronjob einrichten (1 Minute)

**Im Hetzner Web-Interface:**

1. Einloggen: https://konsoleh.hostingkunde.de
2. Menü → **Cronjobs**
3. **"Neuer Cronjob"**
4. Interpreter: **PHP 8.3** (oder wie verfügbar)
5. **Diese Zeile eintragen:**

```
0 3 * * * php8.3 /usr/home/jozapf/cron/contactform/anonymize-logs.php
```

6. **Speichern**

---

## ✅ Fertig!

Der Cronjob läuft jetzt täglich um 3:00 Uhr automatisch.

### Monitoring:

```bash
# Nach 24h prüfen:
tail -n 50 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/cron-anonymization.log
```

---

## 🆘 Probleme?

**Siehe detaillierte Anleitung:**
- `cron/HETZNER-SETUP.md` (ausführlich)
- `cron/README.md` (technisch)

**Häufigste Fehler:**

```bash
# Berechtigungen fehlen
chmod +x /usr/home/jozapf/cron/contactform/*.php

# Logs nicht beschreibbar
chmod 755 /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/assets/php/logs/
```

---

## 📊 Was der Cronjob macht

```
Tag 0-13:  IP: 192.168.1.100  (Vollständig)
Tag 14+:   IP: 192.168.1.XXX  (Anonymisiert)
```

✅ GDPR-konform  
✅ Automatisch  
✅ Email-Benachrichtigung bei Fehlern  

---

**Das war's! 🎉**
