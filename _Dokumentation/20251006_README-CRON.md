# Cronjob Setup - GDPR-Compliant Log Anonymization

**Version:** 3.0.0  
**Status:** ✅ Production Ready  
**Last Updated:** 2025-10-06

---

## Overview

Automated IP address anonymization for contact form submission logs after 14 days, ensuring GDPR compliance through Art. 5 (1) e (storage limitation) and Art. 17 (right to erasure).

**Key Features:**
- ✅ Path configuration via `.env.prod` (centralized, GitHub-ready)
- ✅ Automatic anonymization (14-day retention period)
- ✅ Comprehensive audit trail (SHA256-hashed original IPs)
- ✅ Email notifications on failure (via STDERR)
- ✅ Detailed execution logging with statistics

---

## 📁 Directory Structure

```
/usr/home/users/
└── cron/
    └── contactform/                          ← Cronjob scripts
        ├── anonymize-logs.php
        └── test-anonymization.php

/usr/home/users/public_html/                  ← Your webroot
└── jozapf-de/                                ← Your project
    ├── index.html
    └── assets/
        └── php/
            ├── .env.prod                     ← Configuration file
            ├── ExtendedLogger.php
            └── logs/                         ← Logs stored here
                ├── detailed_submissions.log
                ├── anonymization_history.log
                └── cron-anonymization.log    ← Created by cronjob
```

---

## 🚀 Installation & Setup

### Prerequisites

- PHP 7.4+ (8.0+ recommended)
- Access to server cron configuration
- Existing ContactForm project with ExtendedLogger.php

### Step 1: Configure Paths in .env.prod

**Add to your existing `.env.prod` file:**

```bash
# ============================================================================
# Cronjob Configuration (v3.0.0+)
# ============================================================================

# Public HTML / Webroot Directory (absolute path)
CRON_PUBLIC_HTML=/usr/home/users/public_html

# Project Folder Name (relative to PUBLIC_HTML)
PROJECT_NAME=jozapf-de

# Optional: Custom retention period (default: 14 days)
# RETENTION_DAYS=14
```

**Why in .env.prod?**
- ✅ Centralized configuration
- ✅ Code stays GitHub-ready (no hardcoded paths)
- ✅ Easy deployment across servers
- ✅ Follows 12-Factor App principles

### Step 2: Upload Scripts

**Upload these files to your cron directory:**
- `anonymize-logs.php` → `/usr/home/users/cron/contactform/`
- `test-anonymization.php` → `/usr/home/users/cron/contactform/`

**Set permissions:**
```bash
chmod +x /usr/home/users/cron/contactform/*.php
```

### Step 3: Test Manually

```bash
cd /usr/home/users/cron/contactform
php anonymize-logs.php
```

**Expected output:**
```
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] === Anonymization Cronjob Started ===
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Version: 3.0.0
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Configuration Source: .env.prod
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Public HTML: /usr/home/users/public_html
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Project Name: jozapf-de
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Retention Period: 14 days
[2025-10-06T15:30:02+00:00] [SUCCESS] [PID:12345] ✓ Anonymized X entries
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345] === Cronjob Completed Successfully in 0.XXXs ===
```

**Check log file:**
```bash
tail -n 30 /usr/home/users/public_html/jozapf-de/assets/php/logs/cron-anonymization.log
```

### Step 4: Configure Cronjob

**In your server's cron configuration (e.g., Hetzner Console):**

```bash
# Daily at 3:00 AM (recommended)
0 3 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
```

**Important:**
- Use absolute paths for both PHP binary and script
- Adjust PHP version (`php83`, `php82`, etc.) to match your server
- No output on success (only errors trigger email notifications)

---

## 📋 Cronjob Schedule Examples

```bash
# Daily at 3:00 AM (RECOMMENDED - GDPR compliant)
0 3 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Twice daily (3:00 AM and 3:00 PM)
0 3,15 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Every 6 hours (0:00, 6:00, 12:00, 18:00)
0 0,6,12,18 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Weekly on Sundays at 2:00 AM
0 2 * * 0 /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php

# Monthly on 1st at 1:00 AM
0 1 1 * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
```

**Cron syntax reference:**
```
┌───────────── Minute (0-59)
│ ┌───────────── Hour (0-23)
│ │ ┌───────────── Day of month (1-31)
│ │ │ ┌───────────── Month (1-12)
│ │ │ │ ┌───────────── Day of week (0-7, 0=Sunday)
│ │ │ │ │
0 3 * * * /usr/bin/php83 /path/to/script.php
```

**Test your schedule:** https://crontab.guru/

---

## 🔍 Monitoring & Logs

### View Execution Logs

```bash
# Last 50 lines
tail -n 50 /usr/home/users/public_html/jozapf-de/assets/php/logs/cron-anonymization.log

# Live monitoring
tail -f /usr/home/users/public_html/jozapf-de/assets/php/logs/cron-anonymization.log

# Show errors only
grep ERROR /usr/home/users/public_html/jozapf-de/assets/php/logs/cron-anonymization.log

# Count successful runs
grep "Completed Successfully" /usr/home/users/public_html/jozapf-de/assets/php/logs/cron-anonymization.log | wc -l

# Last 7 executions
grep "Anonymization Cronjob Started" /usr/home/users/public_html/jozapf-de/assets/php/logs/cron-anonymization.log | tail -n 7
```

### View Anonymization History

```bash
# Last 20 anonymizations
tail -n 20 /usr/home/users/public_html/jozapf-de/assets/php/logs/anonymization_history.log

# Anonymizations today
grep "$(date +%Y-%m-%d)" /usr/home/users/public_html/jozapf-de/assets/php/logs/anonymization_history.log

# Count total anonymizations
wc -l /usr/home/users/public_html/jozapf-de/assets/php/logs/anonymization_history.log
```

### Email Notifications

Most cron implementations send email notifications on:
- ❌ Non-zero exit codes (script failure)
- ⚠️ Output to STDERR

Configure email in your hosting control panel (e.g., Hetzner Console → Account Settings).

---

## 🛡️ GDPR Compliance

### Legal Basis

| GDPR Article | Purpose | Implementation |
|--------------|---------|----------------|
| Art. 6 (1) f | Legitimate Interest | Spam protection & abuse prevention |
| Art. 5 (1) e | Storage Limitation | Automatic deletion after 14 days |
| Art. 17 | Right to Erasure | Anonymization = deletion of personal reference |
| Art. 5 (1) a | Lawfulness & Transparency | Documented processes, audit trail |

### Retention Policy

```
Day 0:     IP: 192.168.1.100    (Fully stored for spam analysis)
Day 1-13:  IP: 192.168.1.100    (Still complete)
Day 14:    IP: 192.168.1.100    (Last day before anonymization)
Day 15:    IP: 192.168.1.XXX    (Automatically anonymized)
Day 15+:   IP: 192.168.1.XXX    (Remains anonymized, no personal reference)
```

**Anonymization Process:**
- IPv4: `192.168.1.100` → `192.168.1.XXX`
- IPv6: `2001:db8::1` → `2001:db8::XXX`

**Audit Trail:**
Each anonymization is logged with:
- Original timestamp
- Anonymized timestamp
- SHA256 hash of original IP (for compliance proof)
- Anonymized IP address
- Retention period used

---

## 🆘 Troubleshooting

### Problem: "Could not locate .env.prod file"

**Error:**
```
FATAL: Could not locate .env.prod file
```

**Solution:**

1. Verify .env.prod exists:
```bash
ls -la /usr/home/users/public_html/jozapf-de/assets/php/.env.prod
```

2. Check file permissions:
```bash
chmod 644 /usr/home/users/public_html/jozapf-de/assets/php/.env.prod
```

3. Verify search paths in script match your structure.

### Problem: "Cronjob paths not configured in .env.prod"

**Warning:**
```
WARNING: Cronjob paths not configured in .env.prod
```

**Solution:**

Add to `.env.prod`:
```bash
CRON_PUBLIC_HTML=/usr/home/users/public_html
PROJECT_NAME=jozapf-de
```

### Problem: "Project root not found"

**Error:**
```
FATAL: Project root not found: /usr/home/users/public_html/jozapf-de
```

**Solution:**

1. Check if path exists:
```bash
ls -la /usr/home/users/public_html/jozapf-de
```

2. Verify `PROJECT_NAME` in `.env.prod` matches actual folder name:
```bash
ls -la /usr/home/users/public_html/
```

3. Update `PROJECT_NAME` in `.env.prod` if different.

### Problem: "Log directory not writable"

**Error:**
```
FATAL: Log directory is not writable
```

**Solution:**
```bash
chmod 755 /usr/home/users/public_html/jozapf-de/assets/php/logs/
```

If that doesn't work:
```bash
chown -R www-data:www-data /usr/home/users/public_html/jozapf-de/assets/php/logs/
```

### Problem: "Permission denied" when running script

**Solution:**
```bash
chmod +x /usr/home/users/cron/contactform/anonymize-logs.php
```

### Problem: PHP version not found

**Error in cronjob:** `/usr/bin/php83: not found`

**Solution:**

1. Find available PHP versions:
```bash
ls -la /usr/bin/php*
```

Example output:
```
/usr/bin/php81
/usr/bin/php82
/usr/bin/php83
```

2. Use correct version in cronjob:
```bash
# If only php82 available:
0 3 * * * /usr/bin/php82 /usr/home/users/cron/contactform/anonymize-logs.php
```

### Problem: Cronjob runs but nothing happens

**Debug steps:**

1. **Run manually to see output:**
```bash
/usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
echo $?  # Should output: 0
```

2. **Check if entries exist to anonymize:**
```bash
# Count total log entries
wc -l /usr/home/users/public_html/jozapf-de/assets/php/logs/detailed_submissions.log

# Check if any entries are old enough (>14 days)
head -n 5 /usr/home/users/public_html/jozapf-de/assets/php/logs/detailed_submissions.log
```

3. **Verify ExtendedLogger.php exists:**
```bash
ls -la /usr/home/users/public_html/jozapf-de/assets/php/ExtendedLogger.php
```

### Problem: No entries to anonymize

**This is normal if:**
- All submissions are less than 14 days old
- All old entries are already anonymized
- No submissions have been made yet

**To test with shorter retention:**

Add to `.env.prod`:
```bash
RETENTION_DAYS=1
```

Run script manually and check results. **Don't forget to remove this line after testing!**

---

## 🧪 Testing

### Test with Custom Retention Period

For testing purposes, temporarily shorten retention:

**Option 1: Via .env.prod (recommended)**

Add to `.env.prod`:
```bash
RETENTION_DAYS=1
```

Run cronjob:
```bash
php anonymize-logs.php
```

**Important:** Remove `RETENTION_DAYS=1` after testing!

**Option 2: Directly in ExtendedLogger.php (temporary)**

Edit `ExtendedLogger.php`, line 24:
```php
// Original:
private $retentionDays = 14;

// For testing:
private $retentionDays = 1;
```

**Important:** Change back to 14 after testing!

### Create Test Entries

Create old test entries in `detailed_submissions.log`:

```json
{"timestamp":"2025-09-20T10:00:00+00:00","ip":"192.168.1.200","userAgent":"TestBrowser","fingerprint":"test123","formData":{"email":"test@example.com","subject":"Test"},"spamScore":0,"blocked":false,"anonymized":false}
```

These entries (from September) will be anonymized on next run.

---

## 📊 Statistics & Reporting

The cronjob logs comprehensive statistics on each run:

**Example log output:**
```
[2025-10-06T03:00:02] [INFO] Log Statistics (30 days):
[2025-10-06T03:00:02] [INFO]   - Total submissions: 142
[2025-10-06T03:00:02] [INFO]   - Blocked: 23
[2025-10-06T03:00:02] [INFO]   - Allowed: 119
[2025-10-06T03:00:02] [INFO]   - Avg Spam Score: 8.7
[2025-10-06T03:00:02] [INFO]   - Unique IPs: 87
```

**Use cases:**
- Compliance reporting (number of anonymized entries)
- Trend analysis (spam activity over time)
- Monitoring (verify cronjob executes regularly)
- Performance tracking (execution time)

---

## 🔒 Security Best Practices

### ✅ DO

- ✅ Run daily at low-traffic hours (3:00 AM recommended)
- ✅ Monitor logs weekly (check for errors)
- ✅ Enable email notifications for failures
- ✅ Keep `.env.prod` with restrictive permissions (chmod 600)
- ✅ Test after updates (manual run before going live)
- ✅ Backup logs before major changes
- ✅ Review anonymization history monthly

### ❌ DON'T

- ❌ Run too frequently (daily is sufficient for GDPR)
- ❌ Use retention period < 7 days (too short for spam analysis)
- ❌ Leave test settings in production (e.g., RETENTION_DAYS=1)
- ❌ Commit `.env.prod` to version control
- ❌ Run multiple instances in parallel (file lock conflicts)
- ❌ Ignore email notifications from cron
- ❌ Store logs in publicly accessible directories

### Data Protection

- **Cronjob logs contain IP addresses** until anonymization
- **Scripts outside webroot** (not accessible via HTTP)
- **Restrictive file permissions** (755 dirs, 644 files, 600 .env)
- **No credentials in code** (all in .env.prod)
- **Audit trail preserved** (SHA256 hashes for compliance)

---

## 🔄 Updates & Maintenance

### Updating the Script

1. **Backup current version:**
```bash
cp anonymize-logs.php anonymize-logs.php.backup
```

2. **Upload new version**

3. **Test manually:**
```bash
php anonymize-logs.php
```

4. **Check logs for errors**

5. **Monitor first automatic execution**

### Log Rotation

If logs grow too large (>100 MB):

**Manual rotation:**
```bash
cd /usr/home/users/public_html/jozapf-de/assets/php/logs
mv cron-anonymization.log cron-anonymization.log.$(date +%Y%m%d)
touch cron-anonymization.log
chmod 644 cron-anonymization.log
```

**Automatic cleanup (via separate cronjob):**
```bash
# Delete logs older than 90 days - runs monthly
0 0 1 * * find /usr/home/users/public_html/jozapf-de/assets/php/logs/ -name "cron-*.log" -mtime +90 -delete
```

---

## ✅ Pre-Deployment Checklist

- [ ] `.env.prod` contains `CRON_PUBLIC_HTML` and `PROJECT_NAME`
- [ ] Paths in `.env.prod` verified (directories exist)
- [ ] Scripts uploaded to cron directory
- [ ] Permissions set (`chmod +x *.php`)
- [ ] Test script executed successfully (`php anonymize-logs.php`)
- [ ] Log file created and writable
- [ ] No errors in execution log
- [ ] Cronjob configured in control panel
- [ ] Email notifications enabled
- [ ] After 24h: Verify automatic execution
- [ ] Anonymization history shows correct entries

---

## 📚 Further Documentation

- **GDPR/GDPR:** https://gdpr.eu/
- **Cron Syntax:** https://crontab.guru/
- **Project Main README:** `/README.md`
- **Environment Config:** `/.env.prod.example.v3`
- **ExtendedLogger Class:** `/assets/php/ExtendedLogger.php`

---

## 📝 Changelog

### Version 3.0.0 (2025-10-06)
- **[FEATURE]** Path configuration via `.env.prod` (CRON_PUBLIC_HTML, PROJECT_NAME)
- **[FEATURE]** Optional RETENTION_DAYS override in `.env.prod`
- **[FEATURE]** Fallback to relative path detection (backward compatible)
- **[IMPROVEMENT]** Centralized configuration (12-Factor App)
- **[IMPROVEMENT]** Enhanced error messages with troubleshooting hints
- **[SECURITY]** Fail-fast configuration validation
- **[DOCS]** Comprehensive documentation in English

### Version 2.0.0 (2025-10-06)
- Initial cronjob implementation
- Relative path resolution
- GDPR-compliant anonymization

---

**Created:** 2025-10-06  
**Author:** Jo Zapf  
**License:** MIT  
**Status:** ✅ Production Ready
