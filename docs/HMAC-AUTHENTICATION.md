# HMAC Token Authentication System

Stateless, cryptographically signed authentication for the dashboard without PHP session vulnerabilities.

---

## Table of Contents

- [Overview](#overview)
- [Why HMAC Instead of Sessions](#why-hmac-instead-of-sessions)
- [How It Works](#how-it-works)
- [Security Features](#security-features)
- [Installation](#installation)
- [Configuration](#configuration)
- [File Structure](#file-structure)
- [Token Lifecycle](#token-lifecycle)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)
- [Migration from Basic Auth](#migration-from-basic-auth)

---

## Overview

This system uses HMAC-SHA256 signed tokens stored in HTTP-only cookies to authenticate dashboard access. The token contains user data and expiration timestamp, cryptographically signed to prevent tampering.

**Key Characteristics:**
- Stateless (no server-side session storage)
- Tamper-proof (HMAC signature validation)
- Auto-expiring (24-hour default)
- Secure cookies (HttpOnly, Secure, SameSite)

---

## Why HMAC Instead of Sessions

### Problems with PHP Sessions

| Vulnerability | Description |
|---------------|-------------|
| **Session Hijacking** | Attacker steals session ID via XSS or network sniffing |
| **Session Fixation** | Attacker forces user to use known session ID |
| **Server Storage** | Sessions stored on disk/database (I/O overhead) |
| **Scaling Issues** | Difficult to share sessions across multiple servers |
| **Predictable IDs** | PHP session IDs can be predictable if not configured properly |

### HMAC Token Advantages

| Feature | Benefit |
|---------|---------|
| **Stateless** | No server-side storage required |
| **Cryptographically Signed** | Cannot be forged without secret key |
| **Self-Contained** | All data embedded in token itself |
| **Scalable** | Works across multiple servers without shared storage |
| **Expiration Built-In** | Token contains expiry timestamp |

---

## How It Works

### Token Structure

```
[BASE64_PAYLOAD].[HMAC_SIGNATURE]
```

**Example:**
```
eyJ1c2VyIjoiZGFzaGJvYXJkX2FkbWluIiwiZXhwIjoxNzMwMTIzNDU2LCJpYXQiOjE3MzAwMzcwNTZ9.a3f8b9c2d1e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0
```

### Payload Contents

```json
{
  "user": "dashboard_admin",
  "exp": 1730123456,  // Unix timestamp (24h from login)
  "iat": 1730037056   // Issued at timestamp
}
```

### Generation Process

1. **Create payload** with user identifier and timestamps
2. **Base64 encode** the JSON payload
3. **Generate HMAC signature** using SHA-256 and secret key
4. **Concatenate** payload and signature with dot separator
5. **Store in cookie** with secure flags

### Verification Process

1. **Read cookie** from HTTP request
2. **Split** token into payload and signature
3. **Recompute signature** using secret key
4. **Compare** signatures using timing-safe comparison
5. **Check expiration** from payload timestamp
6. **Grant or deny** access based on validation

---

## Security Features

### 1. HMAC-SHA256 Signature

**Algorithm:** HMAC (Hash-based Message Authentication Code) with SHA-256

**Security Properties:**
- Cryptographically secure
- Computationally infeasible to forge without key
- Resistant to length extension attacks
- Industry standard (NIST FIPS 198-1)

**Code:**
```php
$signature = hash_hmac('sha256', $payload, $secret);
```

### 2. Timing-Safe Comparison

Prevents timing attacks by using constant-time string comparison:

```php
if (!hash_equals($expected, $actual)) {
    return false;
}
```

**Why Important:**
- Standard `===` comparison leaks timing information
- Attacker could use timing differences to guess signature bytes
- `hash_equals()` always takes same time regardless of match

### 3. Secure Cookie Flags

```php
setcookie('dashboard_token', $token, [
    'expires' => time() + 86400,
    'path' => '/assets/php/',
    'secure' => true,      // HTTPS only
    'httponly' => true,    // No JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);
```

**Flag Explanations:**

| Flag | Purpose | Attack Prevented |
|------|---------|------------------|
| `secure` | Cookie only sent over HTTPS | Man-in-the-middle |
| `httponly` | JavaScript cannot read cookie | XSS token theft |
| `samesite` | Cookie not sent on cross-site requests | CSRF attacks |

### 4. Token Expiration

Token contains `exp` timestamp checked on every request:

```php
if ($data['exp'] < time()) {
    return false; // Token expired
}
```

**Default:** 24 hours  
**Configurable:** Change `time() + (24 * 3600)` in `generateToken()`

---

## Installation

### Prerequisites

- PHP 7.4 or higher
- HTTPS enabled (required for `secure` cookie flag)

### Step 1: Generate Secret Key

**Linux/Mac:**
```bash
openssl rand -base64 32
```

**Windows PowerShell:**
```powershell
[Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Minimum 0 -Maximum 256 }))
```

**PHP (in browser):**
```php
<?php echo base64_encode(random_bytes(32)); ?>
```

**Example Output:**
```
kJ8mN5pQ7rS9tU2vW4xY6zA1bC3dE5fG7hI9jK0lM2n=
```

### Step 2: Configure Environment

Add to `.env.prod`:

```bash
# Dashboard Authentication
DASHBOARD_PASSWORD=your-secure-password-here
DASHBOARD_SECRET=kJ8mN5pQ7rS9tU2vW4xY6zA1bC3dE5fG7hI9jK0lM2n=
```

**Security Rules:**
- Secret must be at least 32 bytes (256 bits)
- Use cryptographically random generator
- Never commit to version control
- Rotate periodically (every 90 days recommended)

### Step 3: Deploy Files

```
assets/php/
├── dashboard-login.php   # Login form + token generation
├── dashboard-api.php     # Backend JSON API
└── dashboard-view.php    # Protected frontend (or dashboard.php)
```

### Step 4: Protect with .htaccess

```apache
# Protect environment files
<FilesMatch "^\.(env|env\.prod)$">
  Require all denied
</FilesMatch>

# No Basic Auth needed - HMAC handles authentication
php_flag display_errors Off
```

### Step 5: Test

1. Access `https://yourdomain.com/assets/php/dashboard-login.php`
2. Enter password
3. Should redirect to dashboard
4. Close browser and reopen - should still be logged in (cookie persists)

---

## Configuration

### Adjust Token Expiration

In `dashboard-login.php`, function `generateToken()`:

```php
function generateToken($secret) {
    $data = [
        'user' => 'dashboard_admin',
        'exp' => time() + (24 * 3600), // <- Change here
        'iat' => time()
    ];
    // ...
}
```

**Examples:**
- 1 hour: `time() + 3600`
- 12 hours: `time() + (12 * 3600)`
- 7 days: `time() + (7 * 24 * 3600)`
- 30 days: `time() + (30 * 24 * 3600)`

### Adjust Cookie Path

If dashboard is not in `/assets/php/`:

```php
setcookie('dashboard_token', $token, [
    'path' => '/your/path/', // <- Change here
    // ...
]);
```

### Disable HTTPS Requirement (Development Only)

```php
setcookie('dashboard_token', $token, [
    'secure' => false, // WARNING: Only for local development
    // ...
]);
```

**Never disable in production!**

---

## File Structure

### dashboard-login.php

**Purpose:** Login form and token generation

**Functions:**
- `env($key, $default)` - Load from .env.prod
- `generateToken($secret)` - Create signed token
- `verifyToken($token, $secret)` - Validate existing token

**Flow:**
1. Check if valid token exists in cookie
2. If yes, redirect to dashboard
3. If no, show login form
4. On POST, validate password
5. Generate token and set cookie
6. Redirect to dashboard

### dashboard-view.php

**Purpose:** Protected frontend interface

**Security Check (top of file):**
```php
<?php
// Token verification MUST be first
function verifyToken($token, $secret) { /* ... */ }

if (!verifyToken($_COOKIE['dashboard_token'] ?? '', env('DASHBOARD_SECRET'))) {
    header('Location: dashboard-login.php');
    exit;
}
?>
```

**Critical:** This check must execute before any HTML output.

### dashboard-api.php

**Purpose:** JSON backend (no authentication - relies on frontend)

**Note:** API does not re-check token. It assumes dashboard-view.php already verified user. If you want API independently callable, add token check here too.

---

## Token Lifecycle

### 1. Login

```
User enters password
         ↓
Password validated
         ↓
Token generated with:
  - user: "dashboard_admin"
  - exp: now + 24h
  - iat: now
         ↓
Token signed with HMAC-SHA256
         ↓
Token stored in secure cookie
         ↓
User redirected to dashboard
```

### 2. Subsequent Requests

```
Browser sends cookie with request
         ↓
PHP reads cookie value
         ↓
Token split into [payload].[signature]
         ↓
Signature recomputed with secret key
         ↓
Signatures compared (timing-safe)
         ↓
Expiration timestamp checked
         ↓
Access granted or denied
```

### 3. Expiration

```
24 hours after login
         ↓
Token exp < current time
         ↓
verifyToken() returns false
         ↓
User redirected to login
         ↓
Must re-authenticate
```

### 4. Logout (Optional Feature)

Currently no logout function. To add:

```php
// In dashboard-view.php
if (isset($_GET['logout'])) {
    setcookie('dashboard_token', '', [
        'expires' => time() - 3600,
        'path' => '/assets/php/'
    ]);
    header('Location: dashboard-login.php');
    exit;
}
```

---

## Security Considerations

### Secret Key Management

**Do:**
- Generate with cryptographic RNG
- Store only in `.env.prod` (not in code)
- Protect `.env.prod` with .htaccess
- Use at least 256 bits (32 bytes)
- Rotate every 90 days

**Don't:**
- Hard-code in PHP files
- Commit to version control
- Share via email/chat
- Reuse across projects
- Use predictable values

### Token Theft Scenarios

| Scenario | Mitigation |
|----------|------------|
| **XSS Attack** | `httponly` flag prevents JavaScript access |
| **Man-in-the-Middle** | `secure` flag requires HTTPS |
| **CSRF Attack** | `samesite=Strict` blocks cross-site requests |
| **Physical Access** | 24h expiration limits window |
| **Token Reuse** | Token is valid until expiration (by design) |

### What HMAC Does NOT Prevent

1. **Keylogger:** User's password can still be stolen
2. **Compromised Server:** Attacker with file access can read secret
3. **Social Engineering:** User could be tricked into sharing password
4. **Browser Session Hijacking:** If attacker gains browser access while logged in

**Additional Protections:**
- Use strong passwords
- Enable 2FA (not implemented in basic version)
- Monitor login attempts
- Implement rate limiting
- Use IP whitelist for sensitive dashboards

---

## Troubleshooting

### "ERROR: DASHBOARD_SECRET not set"

**Cause:** Missing secret in `.env.prod`

**Fix:**
1. Generate secret: `openssl rand -base64 32`
2. Add to `.env.prod`: `DASHBOARD_SECRET=generated-value`
3. Verify file location: `assets/php/.env.prod`

### "Invalid password" but password is correct

**Possible Causes:**

1. **Password encoding issue in .env.prod**
   ```bash
   # Wrong:
   DASHBOARD_PASSWORD="mypass"  # Quotes included in value
   
   # Right:
   DASHBOARD_PASSWORD=mypass
   ```

2. **Browser autocomplete used wrong password**
   - Clear form, type manually

3. **Case sensitivity**
   - Passwords are case-sensitive

### Immediately logged out after login

**Possible Causes:**

1. **Cookie path mismatch**
   ```php
   // Login sets: 'path' => '/assets/php/'
   // Dashboard accessed at: /other/path/
   // Fix: Match paths
   ```

2. **HTTPS required but using HTTP**
   ```php
   // For dev only, set:
   'secure' => false
   ```

3. **Secret key mismatch**
   - Login uses different secret than verification
   - Check .env.prod is being read correctly

### Token validation fails randomly

**Possible Causes:**

1. **Server time drift**
   ```bash
   # Check server time
   date
   
   # Sync with NTP
   ntpdate -s time.nist.gov
   ```

2. **Secret changed but old tokens still in browser**
   - Clear cookies
   - Wait 24h for old tokens to expire

### "No resource with given identifier found"

**Cause:** Filename mismatch in redirect

**Check:**
1. Login redirects to: `dashboard-view.php`
2. But file is named: `dashboard.php`

**Fix:** Make filenames consistent everywhere

---

## Migration from Basic Auth

If you previously used HTTP Basic Authentication:

### Step 1: Remove from .htaccess

**Remove these lines:**
```apache
AuthType Basic
AuthName "Dashboard"
AuthUserFile /var/www/.htpasswd
Require valid-user
```

### Step 2: Delete .htpasswd (optional)

```bash
rm /var/www/.htpasswd
```

No longer needed.

### Step 3: Update .env.prod

```bash
# Add new variables:
DASHBOARD_PASSWORD=your-password  # Same as Basic Auth if desired
DASHBOARD_SECRET=generated-secret
```

### Step 4: Clear browser credentials

Basic Auth credentials may be cached:
- Chrome: Settings → Privacy → Clear browsing data → Passwords
- Firefox: Options → Privacy & Security → Saved Logins
- Or use private/incognito window

### Comparison

| Feature | Basic Auth | HMAC Token |
|---------|------------|------------|
| Storage | Browser sends user/pass with every request | Cookie sent automatically |
| Logout | Must close browser | Delete cookie (or wait for expiry) |
| Security | Password sent with every request | Password only sent once at login |
| Scaling | Works across servers | Works across servers |
| Session | No session (stateless) | No session (stateless) |
| Hijacking | Vulnerable if credentials leaked | Limited by expiration + secure flags |

---

## Best Practices

### Production Checklist

- [ ] HTTPS enabled and enforced
- [ ] Strong secret key (32+ bytes, random)
- [ ] `.env.prod` protected by .htaccess
- [ ] `display_errors Off` in PHP
- [ ] Strong dashboard password (12+ chars, mixed case, symbols)
- [ ] Token expiration appropriate for use case (24h default)
- [ ] Regular secret rotation schedule (90 days)
- [ ] Monitor access logs for suspicious activity

### Security Hardening

**Additional .htaccess rules:**
```apache
# Rate limiting (if mod_evasive available)
<IfModule mod_evasive20.c>
    DOSHashTableSize 3097
    DOSPageCount 5
    DOSSiteCount 50
    DOSPageInterval 1
    DOSSiteInterval 1
    DOSBlockingPeriod 10
</IfModule>

# IP whitelist (if dashboard only accessed from known IPs)
<FilesMatch "^dashboard-(login|view)\.php$">
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
</FilesMatch>
```

---

## Technical References

### HMAC Standard

- **NIST FIPS 198-1:** Hash-based Message Authentication Code (HMAC)
- **RFC 2104:** HMAC: Keyed-Hashing for Message Authentication

### Cookie Security

- **RFC 6265:** HTTP State Management Mechanism (Cookies)
- **OWASP:** Session Management Cheat Sheet

### PHP Functions Used

| Function | Purpose | Documentation |
|----------|---------|---------------|
| `hash_hmac()` | HMAC signature | [php.net/hash_hmac](https://php.net/hash_hmac) |
| `hash_equals()` | Timing-safe comparison | [php.net/hash_equals](https://php.net/hash_equals) |
| `setcookie()` | Cookie creation | [php.net/setcookie](https://php.net/setcookie) |
| `base64_encode()` | Payload encoding | [php.net/base64_encode](https://php.net/base64_encode) |
| `json_encode()` | Data serialization | [php.net/json_encode](https://php.net/json_encode) |

---

## License

MIT License - Free for personal and commercial use.

---

## Support

For security issues, please report privately rather than opening public issues.

---

**Version:** 1.0.0  
**Last Updated:** October 2025  
**Status:** Production Ready
