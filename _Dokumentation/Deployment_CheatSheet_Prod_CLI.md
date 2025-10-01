# Deployment Cheat Sheet (Prod) – Copy/Paste

> **Ziel:** Schnell & sicher deployen. Platzhalter in `{{…}}` anpassen.  
> **Annahme:** SSH auf Server, Projekt unter `/var/www/{{project}}`.

---

## 0) Preflight (lokal/Server)
```bash
# Backup (Server)
sudo tar -C /var/www -czf /root/backup_{{project}}_$(date +%F).tgz {{project}} || true
```

---

## 1) Code ausrollen (Server)
```bash
# 1) Verzeichnis vorbereiten
sudo mkdir -p /var/www/{{project}} && sudo chown -R $USER:www-data /var/www/{{project}}

# 2) Code holen (eine Option wählen)
# a) Git-Checkout
cd /var/www/{{project}} && git clone {{git_url}} src || (cd src && git pull)
# b) Artifact/Zip (lokal gebaut) entpacken
# unzip /path/to/build.zip -d /var/www/{{project}}/src

# 3) Docroot wählen
cd /var/www/{{project}}/src
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
RECIPIENT_EMAIL=dev@localhost
NOREPLY_EMAIL=no-reply@localhost
SUBJECT_PREFIX=[Kontakt]
DEV_FAKE_SEND=false
EOF

# Prod-Overrides
cat > assets/php/.env.prod <<'EOF'
APP_ENV=prod
SMTP_HOST={{smtp_host}}
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER={{smtp_user}}
SMTP_PASS={{smtp_pass}}
RECIPIENT_EMAIL={{recipient_email}}
NOREPLY_EMAIL={{noreply_email}}
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
# /etc/nginx/sites-available/{{project}}.conf
server {
  listen 80;
  server_name {{domain}};
  root /var/www/{{project}}/src;
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
sudo ln -s /etc/nginx/sites-available/{{project}}.conf /etc/nginx/sites-enabled/{{project}}.conf || true
sudo nginx -t && sudo systemctl reload nginx
```

> **Nur Apache?** Dann die CSP/Security-Header in den `<VirtualHost>` oder `.htaccess` (analog).

---

## 5) Sanity-Checks
```bash
# Handler: GET muss 405 liefern
curl -I http://{{domain}}/assets/php/contact-php-handler.php

# Formular-POST (Smoke)
curl -s -X POST http://{{domain}}/assets/php/contact-php-handler.php   -H 'Content-Type: application/x-www-form-urlencoded'   --data 'name=Test&email=test@example.com&message=Hallo' | jq .
```

---

## 6) Mail-Deliverability (DNS)
```
SPF   v=spf1 include:{{smtp_provider_spf}} -all
DKIM  (TXT unter {{selector}}._domainkey.{{domain}}) -> Key vom Provider
DMARC v=DMARC1; p=quarantine; rua=mailto:dmarc@{{domain}}
```

---

## 7) Rollback
```bash
# Vorheriges Backup zurückspielen
sudo systemctl stop nginx || true
sudo tar -C /var/www -xzf /root/backup_{{project}}_YYYY-MM-DD.tgz
sudo systemctl start nginx || true
```

---

## 8) Notizen (Projekt-spezifisch anpassen)
- Docroot ist hier `src/` angenommen. Falls dein Docroot anders liegt (z. B. `public/`), `root` entsprechend ändern.
- Der PHP-Handler nutzt `require __DIR__ . '/../../vendor/autoload.php';` – **prüfe Pfade**.
- **Keine Inline-Skripte** im HTML, sonst CSP anpassen (Nonce/Hash).
