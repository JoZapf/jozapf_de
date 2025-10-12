# DEPLOYMENT — Hetzner Shared Hosting (and quick notes for systemd)

This file provides concise, copy-paste friendly steps to deploy the project to a Hetzner shared hosting environment (or similar shared hosts). It focuses on where to place secrets, how to protect them with `.htaccess` (or nginx rules), and sample cron/systemd wrappers.

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
