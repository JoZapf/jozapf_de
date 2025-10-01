# Deployment Cheat Sheet (Prod) – Copy/Paste (JoZapf)

> **Ziel:** Schnell & sicher deployen. Platzhalter schon **vorausgefüllt** für eure Umgebung – bitte nur anpassen, falls abweichend.
> **Annahme:** SSH auf Server, Projekt unter `/var/www/jozapf_site`, Domain `jozapf.de`, SMTP bei Hetzner (your-server.de).

---

## 0) Preflight (lokal/Server)
```bash
# Backup (Server)
sudo tar -C /var/www -czf /root/backup_jozapf_site_$(date +%F).tgz jozapf_site || true
```

---

## 1) Code ausrollen (Server)
```bash
# 1) Verzeichnis vorbereiten
sudo mkdir -p /var/www/jozapf_site && sudo chown -R $USER:www-data /var/www/jozapf_site

# 2) Code holen (eine Option wählen)
# a) Git-Checkout (URL anpassen, falls nötig)
cd /var/www/jozapf_site && git clone <GIT_URL_HIER> src || (cd src && git pull)

# b) Artifact/Zip (lokal gebaut) entpacken
# unzip /path/to/build.zip -d /var/www/jozapf_site/src

# 3) Docroot wählen
cd /var/www/jozapf_site/src
```

---

## 2) Composer (PHPMailer)
```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

---

## 3) ENV (Variante B)
```bash
# Basis (ohne Secrets)
cat > assets/php/.env <<'EOF'
RECIPIENT_EMAIL=kontakt@jozapf.de
NOREPLY_EMAIL=no-reply@jozapf.de
SUBJECT_PREFIX=[Kontakt]
DEV_FAKE_SEND=false
EOF

# Prod-Overrides (Hetzner/your-server.de)
cat > assets/php/.env.prod <<'EOF'
APP_ENV=prod
SMTP_HOST=mail.your-server.de
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=mail@jozapf.de
SMTP_PASS=<SECRET>
RECIPIENT_EMAIL=kontakt@jozapf.de
NOREPLY_EMAIL=no-reply@jozapf.de
SUBJECT_PREFIX=[Kontakt]
DEV_FAKE_SEND=false
EOF

# Schutz (.env* sperren), falls Apache:
cat > assets/php/.htaccess <<'EOF'
<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
</IfModule>
<FilesMatch "^\.env(\..*)?$">
  Require all denied
</FilesMatch>
EOF
```

---

## 4) Nginx (vor Apache/PHP-FPM) – minimal
```nginx
# /etc/nginx/sites-available/jozapf_site.conf
server {
  listen 80;
  server_name jozapf.de www.jozapf.de;
  root /var/www/jozapf_site/src;
  index index.html index.php;

  location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|webp|woff2?)$ {
    access_log off; expires 7d;
    add_header Cache-Control "public, max-age=604800, immutable";
    try_files $uri =404;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;     # bei PHP-FPM
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
  }

  # Security-Header/CSP (ohne Inline-Scripts!)
  add_header X-Frame-Options "DENY" always;
  add_header X-Content-Type-Options "nosniff" always;
  add_header Referrer-Policy "strict-origin-when-cross-origin" always;
  add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'" always;
}
```

```bash
# aktivieren + reload
sudo ln -s /etc/nginx/sites-available/jozapf_site.conf /etc/nginx/sites-enabled/jozapf_site.conf || true
sudo nginx -t && sudo systemctl reload nginx
```

> **Nur Apache?** Dann die CSP/Security-Header in den `<VirtualHost>` oder `.htaccess` (analog).

---

## 5) Sanity-Checks
```bash
# Handler: GET muss 405 liefern
curl -I http://jozapf.de/assets/php/contact-php-handler.php

# Formular-POST (Smoke)
curl -s -X POST http://jozapf.de/assets/php/contact-php-handler.php   -H 'Content-Type: application/x-www-form-urlencoded'   --data 'name=Test&email=test@example.com&message=Hallo' | jq .
```

---

## 6) Mail-Deliverability (DNS – Hetzner/your-server.de)
```
SPF   v=spf1 include:spf.your-server.de -all
DKIM  (TXT unter <selector>._domainkey.jozapf.de) -> Key vom Provider
DMARC v=DMARC1; p=quarantine; rua=mailto:dmarc@jozapf.de
```
> **Absender-Domain-Alignment:** `NOREPLY_EMAIL` und SMTP-Login-Domain identisch halten.

---

## 7) Rollback
```bash
# Vorheriges Backup zurückspielen
sudo systemctl stop nginx || true
sudo tar -C /var/www -xzf /root/backup_jozapf_site_YYYY-MM-DD.tgz
sudo systemctl start nginx || true
```

---

## 8) Notizen (projektspezifisch)
- Docroot ist hier `src/` angenommen (falls anders, `root` anpassen).
- PHP-Handler nutzt `require __DIR__ . '/../../vendor/autoload.php';` – Pfad prüfen.
- **Keine Inline-Skripte** im HTML, sonst CSP anpassen (Nonce/Hash).
