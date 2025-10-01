# Deploy- & Betrieb-Checkliste – Kontaktformular

## Vorbereitungen
- [ ] Dateien **ohne BOM** speichern (`UTF-8`).
- [ ] **Composer (prod)** ausführen: `composer install --no-dev -o --classmap-authoritative` → `vendor/autoload.php` vorhanden.
- [ ] Dateirechte: `assets/php/logs/` & `assets/php/logs/sent-eml/` beschreibbar (z.B. `0775`).

## ENV (prod)
`.env.prod` in `assets/php/` prüfen:
```
APP_ENV=prod
DEV_FAKE_SEND=false

RECIPIENT_EMAIL=mail@jozapf.de
NOREPLY_EMAIL=noreply@jozapf.de
SUBJECT_PREFIX=Kontakt via www.jozapf.de: 

SMTP_HOST=mail.your-server.de
SMTP_PORT=587        # alternativ: 465
SMTP_SECURE=tls      # alternativ: ssl (bei 465)
SMTP_USER=mail@jozapf.de
SMTP_PASS=••••••••
```
- [ ] `NOREPLY_EMAIL` Domain entspricht eigener Domain.
- [ ] `SMTP_USER` existiert & ist inboxfähig (für `Sender`/Bounces).

## Frontend
- [ ] `contact-form-wrapper.html` eingebunden.
- [ ] `contact-form-logic.js` als **ES Module** geladen; `initContactForm` exportiert.
- [ ] Security-Question (Captcha) sichtbar.

## Funktionstest
- [ ] Formular ausfüllen (echte Adresse).
- [ ] Netzwerkanalyse → `200 OK` + JSON `{ ok: true }`.
- [ ] Posteingang prüfen.
- [ ] Logs checken:
  - `assets/php/logs/mail-debug.log`
  - `assets/php/logs/sent-eml/*.eml`

## Zustellbarkeit
- [ ] SPF: erlaubt Versand über Hetzner.
- [ ] DKIM: Signatur aktiv (falls möglich).
- [ ] DMARC: Policy gesetzt (zunächst `p=none` zum Beobachten).

## Sicherheit
- [ ] Rate-Limiting (optional per IP/Zeit).
- [ ] CSRF-Token oder doppelte Origin-Prüfung (optional; aktuell same-origin + CORS minimal).
- [ ] Honeypot-Feld (optional).

## Betrieb
- [ ] Logrotation für `assets/php/logs/`.
- [ ] Regelmäßige **.EML-Prüfung** (Plattenplatz).
- [ ] Backup-Konzept (Code, ENV-Dateien).
