# ⚠️ WICHTIG: Scripts in Hetzner-Cron-Ordner verschieben

## Sie haben richtig gemacht!

Sie haben `/usr/home/jozapf/cron/` erstellt - das ist **exakt** die Hetzner-Empfehlung!

## Scripts verschieben

Die Scripts aus diesem Verzeichnis müssen nach `/usr/home/jozapf/cron/` verschoben werden:

```bash
# 1. Ins Hetzner-Cron-Verzeichnis wechseln
cd /usr/home/jozapf/cron

# 2. Unterordner für ContactForm erstellen
mkdir -p contactform

# 3. Scripts von diesem Repo-Ordner kopieren
cp /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/cron/*.php contactform/
cp /usr/home/jozapf/site/jozapf-de/ContactFormForGithub/cron/*.md contactform/

# 4. Berechtigungen setzen
chmod +x contactform/*.php

# 5. Testen
php contactform/test-anonymization.php
```

## Neue angepasste Scripts

Ich erstelle jetzt neue Versionen der Scripts mit korrekten Pfaden für:
- `/usr/home/jozapf/cron/contactform/`

Diese finden Sie im nächsten Response.
