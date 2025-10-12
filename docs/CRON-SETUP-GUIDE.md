/**
 * Complete Production Setup Guide - Contact Form with Abuse Prevention
 * 
 * @version     4.3.0
 * @date        2025-10-06 12:00:00 UTC
 * @repository  https://github.com/JoZapf/contact-form-abuse-prevention
 * @package     ContactFormAbusePrevention
 * @author      Jo Zapf
 * 
 * CHANGELOG v4.3.0 (2025-10-06):
 * - [FEATURE] Automated log anonymization setup (AP-04)
 * - [FEATURE] .env.prod v3 with cronjob configuration
 * - [UPDATE] Complete English documentation
 * - [SECURITY] All security features (AP-01, AP-02, AP-04) integrated
 * - [GDPR] Automated compliance with 14-day retention
 * 
 * Previous version: v4.2.0 (CSRF protection setup)
 * 
 * DESCRIPTION:
 * Complete step-by-step guide to deploy the contact form system in production
 * with all security features enabled: API authentication, CSRF protection,
 * and automated GDPR-compliant log anonymization.
 * 
 * FEATURES COVERED:
 * - Environment configuration (.env.prod v3)
 * - SMTP setup and testing
 * - Dashboard authentication (HMAC + CSRF)
 * - API security (CORS + token validation)
 * - Automated log anonymization (cronjob)
 * - Production deployment checklist
 * 
 * SECURITY FEATURES:
 *   AP-01: Dashboard API authentication & CORS hardening
 *   AP-02: CSRF protection for admin actions
 *   AP-04: Automated log anonymization (14-day retention)
 * 
 * GDPR COMPLIANCE:
 *   Art. 6 (1) f GDPR - Legitimate interest (spam protection)
 *   Art. 5 (1) e GDPR - Storage limitation (automatic deletion)
 *   Art. 17 GDPR      - Right to erasure (anonymization)
 * 
 * TIME REQUIRED:
 *   Initial setup:    30-45 minutes
 *   Testing:          15-30 minutes
 *   Cronjob setup:    10-15 minutes
 *   Total:            ~90 minutes
 */

# Complete Production Setup Guide

**Version:** 4.3.0  
**Status:** ✅ Production Ready  
**Last Updated:** 2025-10-06  
**Security Level:** 🔒 Hardened (AP-01, AP-02, AP-04)

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Project Structure](#project-structure)
3. [Initial Setup](#initial-setup)
4. [Environment Configuration](#environment-configuration)
5. [SMTP Setup & Testing](#smtp-setup--testing)
6. [Dashboard Authentication](#dashboard-authentication)
7. [Security Configuration](#security-configuration)
8. [Cronjob Setup (Automated Anonymization)](#cronjob-setup-automated-anonymization)
9. [Testing & Verification](#testing--verification)
10. [Production Deployment](#production-deployment)
11. [Monitoring & Maintenance](#monitoring--maintenance)
12. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting, ensure you have:

### Required
- ✅ **PHP 7.4+** (PHP 8.0+ recommended)
- ✅ **Apache/Nginx** web server with mod_rewrite
- ✅ **Composer** installed
- ✅ **HTTPS enabled** (required for secure cookies)
- ✅ **SMTP mail server** credentials
- ✅ **Shell access** (SSH) for cronjob setup
- ✅ **Text editor** (nano, vim, or IDE)

### Recommended
- ✅ Domain with SSL certificate (Let's Encrypt)
- ✅ Access to hosting control panel (cPanel, Plesk, etc.)
- ✅ Email account for testing
- ✅ Backup strategy in place

### Knowledge Required
- Basic PHP understanding
- Command-line familiarity
- Apache/Nginx configuration
- Cron job configuration
- Git basics (for version control)

---

## Project Structure

After setup, your directory structure will look like this:

```
/path/to/webroot/
└── your-project/
    ├── assets/
    │   ├── css/
    │   │   └── contact-form.css
    │   ├── js/
    │   │   ├── contact-form-logic.js
    │   │   └── chart.js
    │   └── php/
    │       ├── contact-php-handler.php
    │       ├── ContactFormValidator-v2.php
    │       ├── ExtendedLogger.php
    │       ├── BlocklistManager.php
    │       ├── dashboard.php
    │       ├── dashboard-login.php
    │       ├── dashboard-api.php
    │       ├── .env.prod ⭐ (CREATE THIS)
    │       ├── .env.prod.example.v3
    │       ├── .htaccess
    │       ├── data/ (auto-created)
    │       │   ├── blocklist.json
    │       │   ├── whitelist.json
    │       │   └── domain-blacklist.txt
    │       └── logs/ (auto-created)
    │           ├── detailed_submissions.log
    │           ├── anonymization_history.log
    │           ├── cron-anonymization.log
    │           └── sent-eml/
    ├── cron/ ⭐ (OUTSIDE WEBROOT)
    │   ├── anonymize-logs.php
    │   ├── test-anonymization.php
    │   └── README.md
    ├── vendor/
    │   └── phpmailer/
    ├── Documentation/
    ├── .htaccess
    ├── composer.json
    ├── index.html
    └── README.md

/path/to/user/home/ ⭐
└── cron/ (RECOMMENDED LOCATION)
    └── contactform/
        ├── anonymize-logs.php
        └── test-anonymization.php
```

---

## Initial Setup

### Step 1: Upload Files

**Via FTP/SFTP:**
```bash
# Upload entire project to your webroot
/path/to/your/webroot/your-project/
```

**Via Git (recommended):**
```bash
cd /path/to/your/webroot
git clone https://github.com/yourusername/contact-form-abuse-prevention.git your-project
cd your-project
```

### Step 2: Install Dependencies

```bash
cd /path/to/your/webroot/your-project
composer install
```

**Expected output:**
```
Loading composer repositories with package information
Installing dependencies from lock file
Package operations: 1 install, 0 updates, 0 removals
  - Installing phpmailer/phpmailer (v6.x.x): Downloading (100%)
Generating autoload files
```

### Step 3: Set Permissions

```bash
# Make directories writable by web server
chmod 755 assets/php/data
chmod 755 assets/php/logs

# Secure .env.prod (will create in next step)
chmod 600 assets/php/.env.prod

# Optional: Set ownership (adjust user:group to your server)
chown -R www-data:www-data assets/php/data
chown -R www-data:www-data assets/php/logs
```

---

## Environment Configuration

### Step 1: Create .env.prod

```bash
cd assets/php
cp .env.prod.example.v3 .env.prod
chmod 600 .env.prod
```

### Step 2: Generate Secrets

**Generate Dashboard Secret (required):**
```bash
openssl rand -base64 32
```

**Example output:** `7K9mL2pQ5nR8wX3vB6tY1uH4jG7fD0cE9sA2zW5xI=`

Copy this value - you'll need it for `DASHBOARD_SECRET`.

### Step 3: Configure .env.prod

Edit `.env.prod` with your actual values:

```bash
nano .env.prod
```

**Complete configuration template:**

```bash
# ============================================================================
# Contact Form - Production Environment Configuration
# ============================================================================
# Version: 3.0.0
# Date: 2025-10-06

# ----------------------------------------------------------------------------
# EMAIL RECIPIENT CONFIGURATION
# ----------------------------------------------------------------------------
RECIPIENT_EMAIL=your-email@yourdomain.com

# ----------------------------------------------------------------------------
# SMTP SERVER CONFIGURATION
# ----------------------------------------------------------------------------
# Find these in your hosting control panel (cPanel → Email Accounts → Configure)

SMTP_HOST=mail.yourdomain.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=noreply@yourdomain.com
SMTP_PASS=your-smtp-password-here

# Common SMTP settings by provider:
# 
# Hetzner:
#   SMTP_HOST=mail.your-server.de
#   SMTP_PORT=587
#   SMTP_SECURE=tls
#
# IONOS:
#   SMTP_HOST=smtp.ionos.de
#   SMTP_PORT=587
#   SMTP_SECURE=tls
#
# Gmail (requires App Password):
#   SMTP_HOST=smtp.gmail.com
#   SMTP_PORT=587
#   SMTP_SECURE=tls
#   Generate App Password: https://myaccount.google.com/apppasswords

# ----------------------------------------------------------------------------
# DASHBOARD AUTHENTICATION
# ----------------------------------------------------------------------------

# Dashboard password - choose a STRONG password!
# Bad: password123, admin
# Good: K9#mPq$7wX2nL5@vB8tR (16+ characters, mixed case, numbers, symbols)
DASHBOARD_PASSWORD=your-very-secure-password-here

# Dashboard secret - MUST be output from: openssl rand -base64 32
# CRITICAL: Never reuse across installations!
DASHBOARD_SECRET=paste-output-from-openssl-rand-base64-32

# ----------------------------------------------------------------------------
# SECURITY CONFIGURATION (v2.0.0+)
# ----------------------------------------------------------------------------

# Allowed Origin for Dashboard API (CORS)
# ⚠️ REQUIRED! Application will fail without this.
#
# SET THIS TO YOUR ACTUAL DOMAIN:
#   Production:  https://yourdomain.com
#   Subdomain:   https://contact.yourdomain.com
#   Local dev:   http://localhost:8080
#
# IMPORTANT: 
#   - Must include protocol (http:// or https://)
#   - No trailing slash
#   - Exact match required
#
ALLOWED_ORIGIN="https://yourdomain.com"

# ----------------------------------------------------------------------------
# CRONJOB CONFIGURATION (v3.0.0+) ⭐ NEW
# ----------------------------------------------------------------------------

# Public HTML / Webroot Directory (absolute path)
# Find your path: pwd (when in webroot via SSH)
#
# Common structures:
#   Shared hosting (cPanel):  /home/username/public_html
#   Shared hosting (Plesk):   /var/www/vhosts/domain.com/httpdocs
#   VPS/Dedicated:            /var/www/html
#   Hetzner:                  /usr/home/users/public_html
#
CRON_PUBLIC_HTML=/path/to/your/webroot

# Project Folder Name (relative to PUBLIC_HTML)
# The folder containing your ContactForm project
# If project is directly in webroot (no subfolder), use: .
#
PROJECT_NAME=your-project-folder

# Optional: Custom retention period (default: 14 days)
# Legal minimum: 7 days for spam analysis
# GDPR-compliant: 14 days (recommended)
# Extended: 30 days (requires justification)
#
# RETENTION_DAYS=14

# ============================================================================
# CONFIGURATION COMPLETE!
# ============================================================================
```

### Step 4: Verify Configuration

```bash
# Check file exists
ls -la .env.prod

# Verify permissions (should show: -rw-------)
ls -l .env.prod

# Test PHP can read it
php -r "var_dump(file_exists('.env.prod'));"
# Expected: bool(true)
```

---

## SMTP Setup & Testing

### Step 1: Verify SMTP Credentials

Most hosting providers offer SMTP details in their control panel:

**cPanel:**
1. Login → Email Accounts
2. Click "Configure Email Client" next to your email
3. Copy SMTP details

**Plesk:**
1. Mail → Mail Settings
2. View outgoing mail server settings

**Hetzner:**
1. KonsoleH → Email
2. Check mailbox settings

### Step 2: Test SMTP Connection

Create a test file `_smtp_probe.php` in `assets/php/`:

```php
<?php
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load environment
function env($key) {
    static $env = null;
    if ($env === null) {
        $env = parse_ini_file('.env.prod');
    }
    return $env[$key] ?? null;
}

try {
    $mail = new PHPMailer(true);
    
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host = env('SMTP_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = env('SMTP_USER');
    $mail->Password = env('SMTP_PASS');
    $mail->SMTPSecure = env('SMTP_SECURE');
    $mail->Port = env('SMTP_PORT');
    
    // Test message
    $mail->setFrom(env('SMTP_USER'), 'Contact Form Test');
    $mail->addAddress(env('RECIPIENT_EMAIL'));
    $mail->Subject = 'SMTP Connection Test';
    $mail->Body = 'If you receive this, SMTP is configured correctly!';
    
    $mail->send();
    echo "✓ SUCCESS: SMTP connection working!\n";
    echo "Check your inbox: " . env('RECIPIENT_EMAIL') . "\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: SMTP connection failed\n";
    echo "Message: {$mail->ErrorInfo}\n";
}
?>
```

**Run test:**
```bash
cd assets/php
php _smtp_probe.php
```

**Expected output:**
```
✓ SUCCESS: SMTP connection working!
Check your inbox: your-email@yourdomain.com
```

**If it fails:**
- Check credentials in `.env.prod`
- Verify firewall allows port 587/465
- Test with telnet: `telnet mail.yourdomain.com 587`
- Check hosting provider's SMTP documentation

**After successful test:**
```bash
rm _smtp_probe.php  # Delete test file
```

---

## Dashboard Authentication

### Step 1: Verify Dashboard Secret

Your `.env.prod` should contain:
```bash
DASHBOARD_SECRET=<output-from-openssl-rand-base64-32>
```

**Security checklist:**
- ✅ At least 32 characters (44 recommended for base64)
- ✅ Generated with `openssl rand -base64 32`
- ✅ Unique (never reused from another installation)
- ✅ Never committed to Git

### Step 2: Set Dashboard Password

Choose a strong password and add to `.env.prod`:
```bash
DASHBOARD_PASSWORD=your-very-secure-password
```

**Password requirements:**
- ✅ Minimum 16 characters
- ✅ Mixed case (upper + lower)
- ✅ Numbers and symbols
- ✅ No dictionary words
- ✅ Not reused from other services

**Generate strong password:**
```bash
openssl rand -base64 16
```

### Step 3: Test Login

**Via browser:**
1. Navigate to: `https://yourdomain.com/assets/php/dashboard-login.php`
2. Enter your dashboard password
3. Click "Login"

**Expected result:**
- ✅ Redirect to `dashboard.php`
- ✅ See dashboard overview with statistics
- ✅ Two cookies set: `dashboard_token` and `csrf_token`

**Check cookies (browser DevTools → Application → Cookies):**
```
Name:     dashboard_token
Value:    [BASE64].[HMAC_SIGNATURE]
Path:     /assets/php/
Secure:   true
HttpOnly: true
SameSite: Strict

Name:     csrf_token
Value:    [64-hex-chars]
Path:     /assets/php/
Secure:   true
HttpOnly: true
SameSite: Strict
```

---

## Security Configuration

### Step 1: Configure ALLOWED_ORIGIN

This is **CRITICAL** for dashboard API security (AP-01).

Edit `.env.prod`:
```bash
ALLOWED_ORIGIN="https://yourdomain.com"
```

**Important:**
- ✅ Must include protocol: `https://`
- ✅ No trailing slash
- ✅ Exact domain match
- ✅ Use your actual domain

**Examples:**
```bash
# Production
ALLOWED_ORIGIN="https://example.com"

# Subdomain
ALLOWED_ORIGIN="https://contact.example.com"

# Local development
ALLOWED_ORIGIN="http://localhost:8080"

# ❌ WRONG: No protocol
ALLOWED_ORIGIN="example.com"

# ❌ WRONG: Trailing slash
ALLOWED_ORIGIN="https://example.com/"

# ❌ WRONG: Wildcard
ALLOWED_ORIGIN="*"
```

### Step 2: Verify API Security

Test that API requires authentication:

```bash
# Without token (should fail)
curl -i https://yourdomain.com/assets/php/dashboard-api.php
# Expected: HTTP 401 Unauthorized

# With valid token (should succeed - login first, then extract cookie)
curl -i -H "Cookie: dashboard_token=YOUR_TOKEN_HERE" \
     https://yourdomain.com/assets/php/dashboard-api.php
# Expected: HTTP 200 OK with JSON data
```

### Step 3: Verify CSRF Protection

All dashboard forms should include CSRF tokens (AP-02).

**View page source** of `dashboard.php` after login:

```html
<!-- Block IP Modal -->
<form method="POST">
    <input type="hidden" name="csrf_token" value="[64-hex-chars]">
    <input type="hidden" name="action" value="block_ip">
    <!-- form fields -->
</form>
```

**Test CSRF validation:**
```bash
# Without CSRF token (should fail)
curl -X POST https://yourdomain.com/assets/php/dashboard.php \
     -H "Cookie: dashboard_token=VALID_TOKEN" \
     -d "action=block_ip&ip=192.168.1.100"
# Expected: HTTP 403 Forbidden
```

---

## Cronjob Setup (Automated Anonymization)

**⭐ NEW in v4.3.0 (AP-04)**

Automated GDPR-compliant log anonymization runs daily to ensure data protection compliance.

### Step 1: Upload Cronjob Scripts

**Recommended location (outside webroot):**
```bash
/path/to/user/home/cron/contactform/
├── anonymize-logs.php
└── test-anonymization.php
```

**Upload via SFTP or create manually:**
```bash
mkdir -p ~/cron/contactform
cd ~/cron/contactform

# Upload files here
```

### Step 2: Configure Paths in .env.prod

Already done if you completed [Environment Configuration](#environment-configuration):

```bash
CRON_PUBLIC_HTML=/path/to/your/webroot
PROJECT_NAME=your-project-folder
```

**Find your paths:**
```bash
# 1. Login via SSH

# 2. Navigate to your webroot
cd /path/to/webroot

# 3. Get absolute path
pwd
# Example output: /usr/home/users/public_html
# Use this for CRON_PUBLIC_HTML

# 4. List folders
ls -la
# Find your project folder name (e.g., "my-site", "contact-form")
# Use this for PROJECT_NAME
```

### Step 3: Test Manually

```bash
cd ~/cron/contactform
php anonymize-logs.php
```

**Expected output:**
```
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] === Anonymization Cronjob Started ===
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Version: 3.0.0
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Configuration Source: .env.prod
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Public HTML: /usr/home/users/public_html
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Project Name: your-project
[2025-10-06T15:30:01+00:00] [INFO] [PID:12345] Retention Period: 14 days
[2025-10-06T15:30:02+00:00] [SUCCESS] [PID:12345] ✓ Anonymized 3 entries
[2025-10-06T15:30:02+00:00] [INFO] [PID:12345] === Cronjob Completed Successfully in 0.123s ===
```

**If it fails:**
- Check `.env.prod` has `CRON_PUBLIC_HTML` and `PROJECT_NAME`
- Verify paths exist: `ls -la /usr/home/users/public_html/your-project`
- Check permissions: `ls -la /usr/home/users/public_html/your-project/assets/php/logs`

### Step 4: Configure Cronjob

**Find your PHP binary:**
```bash
which php
# or
which php83
which php82
```

**cPanel:**
1. Login → Advanced → Cron Jobs
2. Add new cron job:
   - **Minute:** 0
   - **Hour:** 3
   - **Day:** * (every day)
   - **Month:** * (every month)
   - **Weekday:** * (every weekday)
   - **Command:** `/usr/bin/php83 /home/username/cron/contactform/anonymize-logs.php`

**Plesk:**
1. Tools & Settings → Scheduled Tasks
2. Add Task:
   - **Task type:** Run a PHP script
   - **Script path:** `/home/username/cron/contactform/anonymize-logs.php`
   - **Schedule:** Daily at 03:00

**Direct crontab:**
```bash
crontab -e
```

Add line:
```bash
# Contact Form Log Anonymization - Daily at 3:00 AM
0 3 * * * /usr/bin/php83 /home/username/cron/contactform/anonymize-logs.php
```

**Alternative schedules:**
```bash
# Twice daily (3 AM and 3 PM)
0 3,15 * * * /usr/bin/php83 /path/to/anonymize-logs.php

# Every 6 hours
0 0,6,12,18 * * * /usr/bin/php83 /path/to/anonymize-logs.php

# Weekly on Sundays at 2 AM
0 2 * * 0 /usr/bin/php83 /path/to/anonymize-logs.php
```

### Step 5: Verify Execution

**Wait 24 hours, then check log:**
```bash
tail -n 50 /path/to/your/project/assets/php/logs/cron-anonymization.log
```

**Or test immediately:**
```bash
# Run manually to verify
php /home/username/cron/contactform/anonymize-logs.php

# Check execution log
tail -n 20 /path/to/your/project/assets/php/logs/cron-anonymization.log
```

**See:** `cron/README.md` for complete cronjob documentation.

---

## Testing & Verification

### 1. Contact Form Submission Test

**Test allowed submission:**
```bash
curl -X POST https://yourdomain.com/assets/php/contact-php-handler.php \
     -d "name=Test User" \
     -d "email=test@example.com" \
     -d "subject=Test Subject" \
     -d "message=This is a test message" \
     -d "captcha_answer=4"
```

**Expected:**
- ✅ Email received at `RECIPIENT_EMAIL`
- ✅ Entry in `detailed_submissions.log`
- ✅ `.eml` backup in `logs/sent-eml/`

**Test blocked submission (spam):**
```bash
curl -X POST https://yourdomain.com/assets/php/contact-php-handler.php \
     -d "name=Spammer" \
     -d "email=spam@tempmail.com" \
     -d "message=Buy cheap products! http://spam.com"
```

**Expected:**
- ❌ Email NOT sent
- ✅ Entry in `detailed_submissions.log` with `"blocked": true`
- ✅ High spam score (> 30)

### 2. Dashboard Functionality Test

**Login test:**
1. Navigate to: `https://yourdomain.com/assets/php/dashboard-login.php`
2. Enter dashboard password
3. Verify redirect to dashboard

**Dashboard features:**
- [ ] Statistics display correctly
- [ ] Chart.js visualization loads
- [ ] Recent submissions table shows entries
- [ ] Blocklist management works
- [ ] Block IP modal appears
- [ ] Unblock button functions
- [ ] Whitelist management works

**Admin action tests:**
1. Block an IP manually
2. Verify entry appears in blocklist
3. Unblock the IP
4. Verify entry removed
5. Add IP to whitelist
6. Submit form from whitelisted IP
7. Verify submission allowed (spam score ignored)

### 3. API Security Test

**Test 1: Unauthenticated access**
```bash
curl -i https://yourdomain.com/assets/php/dashboard-api.php
```
**Expected:** HTTP 401 Unauthorized

**Test 2: Authenticated access**
```bash
# First, login and extract token
curl -i -c cookies.txt -X POST https://yourdomain.com/assets/php/dashboard-login.php \
     -d "password=your-dashboard-password"

# Then, use token to access API
curl -i -b cookies.txt https://yourdomain.com/assets/php/dashboard-api.php
```
**Expected:** HTTP 200 OK with JSON data

**Test 3: CORS validation**
```bash
curl -i -b cookies.txt https://yourdomain.com/assets/php/dashboard-api.php \
     | grep -i access-control
```
**Expected:** `Access-Control-Allow-Origin: https://yourdomain.com`

### 4. CSRF Protection Test

**Test 1: Missing CSRF token**
```bash
curl -i -b cookies.txt -X POST https://yourdomain.com/assets/php/dashboard.php \
     -d "action=block_ip&ip=192.168.1.100"
```
**Expected:** HTTP 403 Forbidden

**Test 2: Invalid CSRF token**
```bash
curl -i -b cookies.txt -X POST https://yourdomain.com/assets/php/dashboard.php \
     -H "Cookie: csrf_token=invalid123" \
     -d "action=block_ip&ip=192.168.1.100&csrf_token=different456"
```
**Expected:** HTTP 403 Forbidden

### 5. Cronjob Execution Test

**Manual execution:**
```bash
cd ~/cron/contactform
php anonymize-logs.php
echo $?  # Should output: 0
```

**Check logs:**
```bash
tail -n 30 /path/to/your/project/assets/php/logs/cron-anonymization.log
```

**Verify anonymization:**
```bash
# Check if old entries are anonymized
grep "anonymized.*true" /path/to/your/project/assets/php/logs/detailed_submissions.log
```

**View audit trail:**
```bash
tail -n 20 /path/to/your/project/assets/php/logs/anonymization_history.log
```

---

## Production Deployment

### Pre-Deployment Checklist

**Configuration:**
- [ ] `.env.prod` created and configured
- [ ] `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS` correct
- [ ] `RECIPIENT_EMAIL` verified
- [ ] `DASHBOARD_PASSWORD` strong (16+ chars)
- [ ] `DASHBOARD_SECRET` generated with openssl
- [ ] `ALLOWED_ORIGIN` matches your domain exactly
- [ ] `CRON_PUBLIC_HTML` and `PROJECT_NAME` configured

**Files:**
- [ ] `.gitignore` excludes `.env.prod`
- [ ] `.htaccess` in place (blocks `.env` access)
- [ ] Composer dependencies installed
- [ ] Directory permissions correct (755/644)
- [ ] `.env.prod` permissions: 600

**Testing:**
- [ ] SMTP connection successful
- [ ] Form submission works (allowed)
- [ ] Form submission blocked (spam)
- [ ] Dashboard login successful
- [ ] Dashboard API requires authentication
- [ ] CSRF tokens in all forms
- [ ] Cronjob executes successfully

**Security:**
- [ ] HTTPS enabled site-wide
- [ ] Secure cookies (HTTPS, HttpOnly, SameSite)
- [ ] API CORS restricted to domain
- [ ] CSRF protection validated
- [ ] No sensitive data in Git
- [ ] Error logging disabled in production

**GDPR:**
- [ ] Privacy policy updated
- [ ] 14-day retention documented
- [ ] Anonymization cronjob configured
- [ ] Audit trail logging enabled
- [ ] Data subject rights documented

### Deployment Steps

**1. Final file check:**
```bash
# Verify all files uploaded
ls -la assets/php/
ls -la vendor/phpmailer/
ls -la ~/cron/contactform/

# Check .env.prod exists and is protected
ls -l assets/php/.env.prod
# Should show: -rw------- (600)
```

**2. Set production error logging:**

Create `php.ini` or `.user.ini` in `assets/php/`:
```ini
display_errors = Off
log_errors = On
error_log = logs/php-errors.log
```

**3. Test from production domain:**
```bash
# Test form submission
curl -X POST https://yourdomain.com/assets/php/contact-php-handler.php \
     -d "name=Production Test" \
     -d "email=test@yourdomain.com" \
     -d "message=Testing production deployment" \
     -d "captcha_answer=4"

# Check email received
```

**4. Verify dashboard access:**
- Login: `https://yourdomain.com/assets/php/dashboard-login.php`
- Verify all features work
- Test admin actions (block/unblock)
- Check CSRF protection

**5. Monitor logs for 24 hours:**
```bash
# PHP errors
tail -f assets/php/logs/php-errors.log

# Submission log
tail -f assets/php/logs/detailed_submissions.log

# Cronjob log (after 3 AM)
tail -f assets/php/logs/cron-anonymization.log
```

**6. Schedule first backup:**
```bash
# Backup critical files
tar -czf backup-$(date +%Y%m%d).tar.gz \
    assets/php/.env.prod \
    assets/php/data/ \
    assets/php/logs/

# Store securely (not in webroot)
mv backup-*.tar.gz ~/backups/
```

---

## Monitoring & Maintenance

### Daily Monitoring (Automated)

**Cronjob email notifications:**
- Configure email in hosting control panel
- Receive alerts on cronjob failures (STDERR output)
- Check daily for execution confirmations

**Dashboard overview:**
- Login daily to check statistics
- Review spam trends
- Monitor blocked IPs
- Check for unusual patterns

### Weekly Tasks

**1. Review logs:**
```bash
# Last 100 submissions
tail -n 100 assets/php/logs/detailed_submissions.log

# Recent anonymizations
tail -n 50 assets/php/logs/anonymization_history.log

# Cronjob executions
grep "Cronjob Started" assets/php/logs/cron-anonymization.log | tail -n 7
```

**2. Check blocklist:**
- Review blocked IPs
- Remove expired blocks
- Update domain blacklist if needed

**3. Statistics:**
```bash
# Count submissions this week
grep "$(date +%Y-%m)" assets/php/logs/detailed_submissions.log | wc -l

# Count blocked submissions
grep '"blocked":true' assets/php/logs/detailed_submissions.log | wc -l

# Spam score average
grep '"spamScore":' assets/php/logs/detailed_submissions.log | \
    sed -E 's/.*"spamScore":([0-9]+).*/\1/' | \
    awk '{sum+=$1; count++} END {print sum/count}'
```

### Monthly Tasks

**1. Log rotation:**
```bash
# Archive old logs
cd assets/php/logs
tar -czf archive-$(date +%Y%m).tar.gz detailed_submissions.log.old
mv archive-*.tar.gz ~/backups/

# Truncate if needed (>100MB)
ls -lh detailed_submissions.log
# If too large:
tail -n 10000 detailed_submissions.log > detailed_submissions.log.tmp
mv detailed_submissions.log.tmp detailed_submissions.log
```

**2. Security audit:**
- [ ] Review dashboard access logs
- [ ] Check for failed login attempts
- [ ] Verify CSRF protection still active
- [ ] Test API authentication
- [ ] Update dependencies: `composer update`

**3. Backup verification:**
```bash
# Test restore from backup
tar -tzf backup-latest.tar.gz
# Verify all files present
```

**4. GDPR compliance check:**
- [ ] Verify cronjob executed all month
- [ ] Check anonymization history
- [ ] Confirm no IPs >14 days old
- [ ] Review privacy policy current
- [ ] Document retention policy

### Performance Optimization

**If logs become too large (>500MB):**

1. **Enable log rotation:**
```bash
# Create logrotate config
cat > /etc/logrotate.d/contact-form << EOF
/path/to/project/assets/php/logs/*.log {
    weekly
    rotate 12
    compress
    delaycompress
    notifempty
    missingok
    create 644 www-data www-data
}
EOF
```

2. **Archive old entries:**
```bash
# Move entries older than 90 days to archive
php assets/php/archive-old-logs.php --days=90
```

3. **Database migration (advanced):**
- Consider moving to MySQL/PostgreSQL if >1GB logs
- Improves query performance
- Enables advanced analytics

---

## Troubleshooting

### SMTP Issues

**Problem: Emails not sending**

```bash
# Test SMTP directly
telnet mail.yourdomain.com 587

# If connection fails:
# - Check firewall rules (allow port 587/465)
# - Verify SMTP credentials
# - Check hosting provider's outbound mail settings
# - Try alternative port (587 ↔ 465)
```

**Problem: "Could not authenticate"**

```bash
# Verify credentials
php -r "var_dump(parse_ini_file('assets/php/.env.prod'));"

# Check SMTP user/pass correct
# For Gmail: Generate App Password
# For others: Check hosting panel
```

### Dashboard Issues

**Problem: "Could not locate .env.prod"**

```bash
# Check file exists
ls -la assets/php/.env.prod

# Verify permissions
chmod 600 assets/php/.env.prod
```

**Problem: Login fails immediately**

```bash
# Check DASHBOARD_SECRET is set
grep DASHBOARD_SECRET assets/php/.env.prod

# Verify at least 32 characters
php -r "echo strlen('YOUR_SECRET_HERE');"
# Should output: 44 (for base64) or 32+
```

**Problem: "Configuration error - ALLOWED_ORIGIN required"**

```bash
# Add to .env.prod
echo 'ALLOWED_ORIGIN="https://yourdomain.com"' >> assets/php/.env.prod

# Reload PHP (if using PHP-FPM)
sudo systemctl reload php8.2-fpm
```

### Cronjob Issues

**Problem: "Could not locate .env.prod file"**

```bash
# Verify paths in .env.prod
grep CRON_ assets/php/.env.prod

# Test paths exist
ls -la /usr/home/users/public_html/your-project
```

**Problem: Cronjob not executing**

```bash
# Check crontab entry
crontab -l

# Verify PHP binary path
which php83

# Test manual execution
/usr/bin/php83 ~/cron/contactform/anonymize-logs.php
```

**Problem: "Permission denied"**

```bash
# Set execute permission
chmod +x ~/cron/contactform/anonymize-logs.php

# Check log directory writable
chmod 755 /path/to/project/assets/php/logs
```

### Form Issues

**Problem: All submissions blocked**

```bash
# Check spam threshold
grep blockThreshold assets/php/ContactFormValidator-v2.php
# Default: 30

# Lower threshold temporarily for testing
# Or add test IP to whitelist
```

**Problem: Rate limit blocking legitimate users**

```bash
# Increase rate limit
# Edit ContactFormValidator-v2.php:
'rateLimitMax' => 10,  // Increase from 5
'rateLimitWindow' => 3600
```

### CSRF Issues

**Problem: Form returns HTTP 403**

```bash
# Check CSRF token in form
curl -s https://yourdomain.com/assets/php/dashboard.php | grep csrf_token

# Verify cookie set on login
curl -i -c - https://yourdomain.com/assets/php/dashboard-login.php \
     -d "password=your-password" | grep csrf_token
```

**Problem: "CSRF validation failed"**

```bash
# Re-login to get new token
# Clear browser cookies
# Verify HTTPS enabled (required for secure cookies)
# Check SameSite compatibility
```

### Log Analysis

**Find specific issues:**
```bash
# High spam score submissions
grep '"spamScore":[5-9][0-9]' assets/php/logs/detailed_submissions.log

# Blocked submissions
grep '"blocked":true' assets/php/logs/detailed_submissions.log

# Specific IP
grep '"ip":"192.168.1.100"' assets/php/logs/detailed_submissions.log

# Recent errors (if logging enabled)
tail -n 50 assets/php/logs/php-errors.log
```

---

## Additional Resources

### Documentation

- **Main README:** `/README.md`
- **Cronjob Setup:** `/cron/README.md`
- **CSRF Protection:** `/Documentation/CSRF-PROTECTION.md`
- **HMAC Auth:** `/Documentation/HMAC-AUTHENTICATION.md`
- **Security Policy:** `/Documentation/SECURITY.md`
- **Deployment Guide:** `/Documentation/PRODUCTION-vs-GITHUB.md`

### External Resources

- **GDPR Compliance:** https://gdpr.eu/
- **PHPMailer Docs:** https://github.com/PHPMailer/PHPMailer
- **Cron Syntax:** https://crontab.guru/
- **Let's Encrypt SSL:** https://letsencrypt.org/
- **OWASP Security:** https://owasp.org/

### Support

- **GitHub Issues:** https://github.com/yourusername/contact-form-abuse-prevention/issues
- **Security:** See `SECURITY.md` for responsible disclosure
- **Email:** support@yourdomain.com (replace with actual)

---

## Success Criteria

Your setup is complete when:

✅ **Functionality:**
- [ ] Contact form accepts valid submissions
- [ ] Emails sent successfully via SMTP
- [ ] Spam submissions blocked (score ≥ 30)
- [ ] Dashboard accessible with password
- [ ] Admin actions work (block/unblock/whitelist)

✅ **Security:**
- [ ] HTTPS enabled site-wide
- [ ] Dashboard API requires authentication
- [ ] CORS restricted to domain
- [ ] CSRF tokens in all forms
- [ ] `.env.prod` protected (600 permissions)
- [ ] No sensitive data in Git

✅ **GDPR Compliance:**
- [ ] Privacy policy updated
- [ ] 14-day retention configured
- [ ] Automated anonymization cronjob running
- [ ] Audit trail logging enabled
- [ ] Email masking in dashboard API

✅ **Monitoring:**
- [ ] Cronjob executes daily (check logs)
- [ ] Email notifications configured
- [ ] Dashboard statistics display correctly
- [ ] Logs rotate to prevent overflow

---

**🎉 Congratulations! Your contact form is now production-ready with enterprise-level security and GDPR compliance!**

---

**Version:** 4.3.0  
**Last Updated:** 2025-10-06  
**Estimated Setup Time:** 90 minutes  
**Security Level:** 🔒 Hardened (95% risk reduction)  
**GDPR Compliant:** ✅ Automated (14-day retention)

---

**For questions or issues, see:** [Troubleshooting](#troubleshooting) or open a GitHub issue.
