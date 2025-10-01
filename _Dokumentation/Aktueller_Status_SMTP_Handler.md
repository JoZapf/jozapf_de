# Aktuelle Situation – SMTP/Handler (Kurzbericht)

## Problem (IST)
- **Formular-POST auf :8081** (Apache) lieferte sporadisch **500 Internal Server Error**.
- In der UI: generisches „Senden fehlgeschlagen“, Konsole zunächst ohne Detail.

## Umgebung
- Dev-Docker: Nginx :8080 → Proxy auf Apache `php-examples:80`, Apache direkt :8081.
- MailHog: SMTP **1025**, UI **8025**.
- PHP 8.2.x, PHPMailer 6.9, Composer-Autoloader eingebunden.
- Pfade: Docroot `/var/www/html`, Projekt unter `/var/www/html/jozapf/test11.1`.

## Diagnose (done)
- **Autoloader vorhanden** (`OK`-Test via `class_exists`).
- **Konnektivität** zu MailHog geprüft (`fsockopen('mailhog',1025) → OK`).
- **ENV zuerst leer** (parse_ini Arrays) → **neuen .env-Loader** implementiert (eigener Parser).  
- `_env_check.php`: `APP_ENV=dev`, `SMTP_HOST=mailhog`, `SMTP_PORT=1025`, `SMTP_SECURE=<leer>` ✅
- **CSP**: Für Dev gelockert (Loader lief danach).

## Hauptursache
- PHPMailer-Default **AutoTLS**/STARTTLS kann bei MailHog (Plain auf 1025) zu Fehlern führen.  
- Außerdem musste sichergestellt werden: **keine Auth**, **kein TLS** in Dev.

## Implementierte Änderungen
- SMTP-Block im Handler ersetzt:
  - Auth **nur** wenn `SMTP_USER` **und** `SMTP_PASS` gesetzt.
  - TLS/SSL **nur**, wenn `SMTP_SECURE`=tls/ssl; andernfalls **leer** und `SMTPAutoTLS=false` (MailHog-kompatibel).
  - Dev-Logging: `$mail->SMTPDebug=2` → `[SMTP]` im Apache-Log.
- Frontend: Vorschlag integriert, beim Fehlpfad `console.error({status, body})` auszugeben (sichtbar in DevTools).

## Erwartetes Verhalten (Dev)
- POST → `200` + `{ "success": true }`  
- Mail erscheint in MailHog (`http://localhost:8025`).

## Falls weiterhin Fehler auftreten
1. **Network → POST → Response** lesen → `detail` zeigt Ursache (Auth/TLS/Port).
2. Apache-Logs beobachten: `docker compose logs -f php-examples` (Sucht nach `[SMTP]`).
3. **Quick-Bypass** (nur zur Eingrenzung): `DEV_FAKE_SEND=true` in `.env.local` → POST muss **grün** sein.
4. Prüfen, dass `.env.local` exakt so gesetzt ist:
   ```ini
   APP_ENV=dev
   SMTP_HOST=mailhog
   SMTP_PORT=1025
   SMTP_SECURE=
   DEV_FAKE_SEND=false
   ```

## Nächste Schritte (kurz)
- **Frontend-Loader** endgültig aus `index.html` auslagern → CSP wieder härten (`script-src 'self'`).
- **Fehleranzeige** im UI belassen (Status/Body loggen), um künftige Issues sofort zu sehen.
- **Prod**: SMTP-Daten in `.env.prod`, TLS `tls`, Port `587`, SPF/DKIM/DMARC prüfen.
