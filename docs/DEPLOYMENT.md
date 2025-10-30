# DEPLOYMENT — Hetzner Shared Hosting & CI/CD (SFTP via GitHub Actions)

> Dieses Dokument kombiniert die vorhandenen Shared-Hosting-Hinweise mit einer aktualisierten,
> sicheren CI/CD-Anleitung für SFTP-Deployments auf Hetzner.
> Ziel: **sicher**, **nicht-destruktiv**, **wiederholbar**.

---

## Table of Contents
1. [CI/CD via GitHub Actions (SFTP)](#cicd-via-github-actions-sftp)
   1. [Secrets & Repository Variables](#secrets--repository-variables)
   2. [Workflow Triggers](#workflow-triggers)
   3. [Dry-Run vs. Produktivlauf](#dry-run-vs-produktivlauf)
   4. [Build-Metadaten & `.env.local` (CI überschreibt)](#build-metadaten--envlocal-ci-überschreibt)
   5. [Sicherheits-Hinweise](#sicherheits-hinweise)
   6. [Troubleshooting](#troubleshooting)
2. [Shared Hosting Essentials (bestehende Doku, konsolidiert)](#shared-hosting-essentials-bestehende-doku-konsolidiert)
   1. [`.app.env` anlegen und schützen](#appenv-anlegen-und-schützen)
   2. [Cron / systemd Wrapper](#cron--systemd-wrapper)
   3. [Tests nach Deploy](#tests-nach-deploy)
   4. [Empfehlungen](#empfehlungen)

---

## CI/CD via GitHub Actions (SFTP)

**Ziel:** Statische Next.js-Site (`out/`) via **SFTP (Port 22)** nicht-destruktiv auf Hetzner bereitstellen.  
**Remote-Zielpfade:**
- **SITE:** `/public_html/jozapf-de/` (Domain `jozapf.de`)
- **ASSETS:** `/public_html/jozapf-de-assets/` (Subdomain `assets.jozapf.de`)

### Secrets & Repository Variables

**Repository Secrets**  
_Settings → Actions → Secrets and variables → Secrets_
| Name               | Zweck                                  | Format / Beispiel                                                                 | Hinweise |
|--------------------|-----------------------------------------|------------------------------------------------------------------------------------|----------|
| `SFTP_HOST`        | Zielhost (Hetzner)                      | `sshXY.webhostXX.host.tld` oder `your-domain.tld`                                  | Nicht loggen. |
| `SFTP_PORT`        | SFTP-Port                               | `22`                                                                               | Fix 22. |
| `SFTP_USER`        | Hauptbenutzer                           | `u123456`                                                                          | Webroot-Konto. |
| `SFTP_PRIVATE_KEY` | Privater SSH-Key (Auth)                 | **PEM** als Secret-String (BEGIN/END OPENSSH …)                                    | Kein Base64 nötig; Datei-Recht 600. Optional `SFTP_PASSPHRASE`. |

**Repository Variables**  
_Settings → Actions → Secrets and variables → Variables_
| Name                     | Zweck                      | Beispiel                      |
|--------------------------|----------------------------|-------------------------------|
| `HETZNER_DOCROOT_SITE`   | Remote-Zielpfad Site       | `public_html/jozapf-de`       |
| `HETZNER_DOCROOT_ASSETS` | Remote-Zielpfad Assets     | `public_html/jozapf-de-assets`|
| `ASSETS_SRC`             | Lokaler Assets-Ordner      | `assets`                      |

> Im Workflow werden Remote-Pfade **absolut** verwendet: `/${{ vars.HETZNER_DOCROOT_* }}` → z. B. `/public_html/jozapf-de`.

### Workflow Triggers
- `push` auf `main` → **Produktivlauf**
- `workflow_dispatch` → Manuell mit `dry_run=true|false`

### Dry-Run vs. Produktivlauf
- **Dry-Run (`true`)**: Verbindet per SFTP, prüft **nur** Pfade/Listing – **kein Upload**.  
- **Produktivlauf (`false`/Push)**: Pre-Listing → Upload → Post-Listing.

### Build-Metadaten & `.env.local` (CI überschreibt)
- Lokal (`.env.local`) kann (als Fallback) z. B. enthalten:

- **In CI werden diese Werte dynamisch gesetzt** und **überschreiben** lokale Defaults:
- `GIT_TAG` = Tag-Name (bei Tag-Build) **oder** Commit-ShortSHA (bei Branch-Build).
- `BUILD_DATE` = UTC-Zeit im ISO-8601-Format.
- Vorteil: **Keine Divergenz** zwischen lokalen Tests und CI-Builds – der veröffentlichte Stand ist eindeutig datiert/versioniert.

### Sicherheits-Hinweise
- Private Key wird in `~/.ssh/deploy_key` gespeichert, **chmod 600**.  
- Server-Fingerprint via `ssh-keyscan -p "$SFTP_PORT" "$SFTP_HOST"` in `~/.ssh/known_hosts` → **kein** `StrictHostKeyChecking=no`.  
- Keinerlei `echo`/Masking-Breaks von Secrets.

### Troubleshooting
- **Permission denied (publickey)** → Key/Passphrase/Benutzer/Host prüfen; Rechte `600`.  
- **No such file or directory (cd/put)** → Absolute Pfade `/public_html/...` & `lcd`-Verzeichnisse prüfen.  
- **Missing out/index.html** → Build/Export nicht erfolgt oder falsches Arbeitsverzeichnis.  
- **Host key verification failed** → `ssh-keyscan`/`known_hosts` prüfen.

---

## Shared Hosting Essentials (bestehende Doku, konsolidiert)

### `.app.env` anlegen und schützen
1. **Keine Secrets ins Repo** (`.gitignore`).  
2. `.app.env` anlegen **nur falls nötig** (Shared Hosting), z. B.:
 ```bash
 cd /path/to/webroot
 cat > .app.env <<'EOF'
 # Beispiel
 DASHBOARD_SECRET=REPLACE_WITH_BASE64_32
 DASHBOARD_PASSWORD_HASH=REPLACE_WITH_ARGON2ID_HASH
 SMTP_HOST=smtp.example.com
 SMTP_USER=...
 SMTP_PASS=...
 RECIPIENT_EMAIL=admin@example.com
 EOF
 chown <deploy-user>:<deploy-group> .app.env
 chmod 600 .app.env


WARNING: Never commit your `app.env`/`.app.env` to the repository. Add it to `.gitignore`.

1) Place code on server
- Upload the repository to the webroot (e.g. `/www/htdocs/your-site` or `/var/www/html/your-site`).
- Do NOT upload your local `app.env`/`.app.env`. Instead, create it directly on the server.

2) Create `.app.env` in webroot (if you cannot place secrets outside webroot)
- On the server (SSH):

```bash
cd /path/to/webroot
cat > .app.env <<'EOF'
# Example
DASHBOARD_SECRET=REPLACE_WITH_BASE64_32
DASHBOARD_PASSWORD_HASH=REPLACE_WITH_ARGON2ID_HASH
SMTP_HOST=smtp.example.com
SMTP_USER=...
SMTP_PASS=...
RECIPIENT_EMAIL=admin@example.com
# ... other keys your app needs
EOF
```

- Set ownership and permissions (restrictive):

```bash
chown <deploy-user>:<deploy-group> .app.env
chmod 600 .app.env
```

3) Protect `.app.env` via `.htaccess` (Apache)
- Ensure the project root `.htaccess` contains rules that block dotfiles and explicitly block `app.env`/`.app.env` while allowing only needed public endpoints (we include the recommended rules in `README-*.md`).

4) Cron (shared-hosting / control panel)
- If your control panel allows adding a cron job, point it at PHP and ensure environment is loaded. Two safe approaches:

a) Wrapper script that sources `.app.env` before running the PHP script (recommended when the host's cron does not allow EnvironmentFile):

```bash
#!/bin/sh
# /path/to/webroot/cron-runner.sh
set -a
. /path/to/webroot/.app.env
set +a
/usr/bin/php /path/to/webroot/cron/anonymize-logs.php >> /var/log/jozapf-anonymize.log 2>&1
```

Make it executable: `chmod 700 /path/to/webroot/cron-runner.sh`.
Then add a cron entry (via control panel or `crontab -e`):

```
15 3 * * * /path/to/webroot/cron-runner.sh
```

b) If the host supports `EnvironmentFile` (systemd), use a systemd unit (see below).

5) systemd unit (for VPS or dedicated server with systemd)
- Example unit that runs daily and uses an EnvironmentFile outside webroot: create `/etc/systemd/system/jozapf-anonymize.service`:

```ini
[Unit]
Description=Run jozapf anonymize logs

[Service]
Type=oneshot
EnvironmentFile=/etc/jozapf/app.env
ExecStart=/usr/bin/php /var/www/html/your-site/cron/anonymize-logs.php
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

- Example timer `/etc/systemd/system/jozapf-anonymize.timer`:

```ini
[Unit]
Description=Run jozapf anonymize daily

[Timer]
OnCalendar=*-*-* 03:15:00
Persistent=true

[Install]
WantedBy=timers.target
```

- Place your secrets in `/etc/jozapf/app.env` (NOT in the webroot). Format is simple KEY=VALUE lines; set `chmod 600` and owned by `root`.

6) Test after deploy
- Verify `.app.env` is not world-readable: `ls -l .app.env` -> should be `-rw-------` (600).
- Visit `https://your-site/assets/php/dashboard-login.php` and ensure login works.
- If the dashboard shows "Unexpected token '<'" in console: this indicates the API returned HTML. Ensure you are logged in (token cookie) or use `curl -i -H "Cookie: dashboard_token=..."` to test the API.
- Check logs: `assets/php/logs/`, and cron output log if you configured one.

7) Notes & recommendations
- Prefer placing env/secrets outside webroot when possible. On VPS/VPS-like hosting you can use `/etc/jozapf/app.env` and systemd `EnvironmentFile` to keep secrets out of the webroot.
- Keep the dashboard secret long and random: `openssl rand -base64 32`.
- Use `DASHBOARD_PASSWORD_HASH` with Argon2id for production passwords. Generate hash via PHP `password_hash($pw, PASSWORD_ARGON2ID)` locally.

If you want, I can produce a one-click `deploy.sh` for your Hetzner workflow that uploads files, sets permissions and (optionally) configures a systemd timer on VPS. If you're using Hetzner's managed shared hosting without SSH root, use the wrapper script approach for cron.
