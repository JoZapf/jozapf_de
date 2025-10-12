# Advanced Contact Form with Abuse Prevention

A production-ready, GDPR-compliant contact form system with robust spam protection, extended logging, IP blocklist management, domain blacklist, **hardened dashboard API security**, **CSRF-protected admin actions**, and **automated log anonymization**.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![GDPR Compliant](https://img.shields.io/badge/GDPR-Compliant-success)](https://gdpr.eu/)
[![Security Hardened](https://img.shields.io/badge/Security-Hardened-brightgreen)](./)
[![Production Ready](https://img.shields.io/badge/Status-Production%20Ready-brightgreen)](/)

---

## 🚀 What You Can Expect

**This project is designed for anyone who values security, privacy, and reliability in web communication.**  

**Project objectives:**
- **Learning, consolidating and internalizing deployment workflows**
- **Exercises with GitHub**
- **Pursue personal interests alongside official training**
- **Apply Model Context Protocol with data protection-compliant IDEs and containerized systems and actions**
- **A reliable, easy to expand and robust contact-form system**
- **Create interfaces that I can continue to build on**
- **having fun**
---
- **GDPR-compliant contact form** with multi-layered spam and abuse prevention
- **Security-first dashboard** with stateless authentication and rate limiting
- **Comprehensive documentation** and didactic code comments for easy onboarding
- **Ready for production** and extensible for future features

---

## 🔒 AP-03: Password Hashing & Login Rate Limiting (NEW)

**Latest Security Fix — October 2025**

> **Why is this important?**  
> Passwords are the first line of defense. Weak handling exposes users and systems to brute-force and credential theft attacks.  
> This fix brings the dashboard authentication up to modern standards.

### Key Features

- **Argon2id Password Hashing:**  
  Passwords are never stored or compared in plaintext.  
  Uses PHP's `password_hash()` and `password_verify()` for secure verification.

- **HMAC Token Authentication:**  
  Stateless, cryptographically signed tokens (HMAC-SHA256) replace vulnerable PHP sessions.  
  Tokens are stored in secure, HttpOnly cookies.

- **IP-Based Rate Limiting:**  
  Max 5 failed login attempts per IP in 15 minutes.  
  Brute-force attacks are blocked and logged.

- **Audit Logging:**  
  All failed login attempts are logged for monitoring and compliance.

- **Secure Cookie Handling:**  
  Cookies use `HttpOnly`, `Secure`, and `SameSite=Strict` flags to prevent theft and CSRF.

### Technical Implementation

- `.env.prod` stores only the Argon2id password hash and a random secret key.
- Login logic verifies passwords using `password_verify()`.
- After successful login, a signed token is generated and set as a cookie.
- Dashboard access is granted only if the token is valid and unexpired.
- Rate limiting and logging are handled per IP address.

**See:**  
- `Documentation/runbook-security-fixes.md`  
- `HMAC-AUTHENTICATION.md`  
- `assets/php/dashboard-login.php` and `assets/php/dashboard.php`

---

## Table of Contents

- [Features](#features)
- [Security Notice](#security-notice)
- [System Architecture](#system-architecture)
- [File Structure](#file-structure)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Security Features](#security-features)
- [GDPR Compliance](#gdpr-compliance)
- [Dashboard Features](#dashboard-features)
- [API Security](#api-security)
- [CSRF Protection](#csrf-protection)
- [Automated Log Anonymization](#automated-log-anonymization)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Security Disclosures](#security-disclosures)
- [Changelog](#changelog)
- [About](#about)
- [License](#license)

---

## Security Notice

**October 2025 Security Updates:**

This project has undergone comprehensive security hardening following professional security audit practices.

### AP-03: Password Hashing & Login Rate Limiting (NEW)

- Argon2id password hashing
- HMAC token authentication (stateless, secure)
- IP-based brute-force protection
- Secure cookie handling
- Audit logging of failed attempts

### AP-04: Automated Log Anonymization

- Cronjob-based IP anonymization after 14 days
- GDPR-compliant storage limitation
- Audit trail and statistics

### AP-02: CSRF Protection

- Double Submit Cookie pattern
- JWT token binding for CSRF
- Audit logging of failed attempts

### AP-01: Dashboard API Security

- Token-based authentication
- CORS hardening
- PII protection in API responses

---

## Features

### 🔐 Core Functionality

- PHPMailer integration for reliable SMTP email delivery
- Server-side captcha (no third-party services)
- Multi-layer validation and sanitization
- Honeypot protection against bots
- Post-Redirect-Get pattern for UX

### 🛡️ Advanced Abuse Prevention

- Extended logging system (GDPR-compliant)
- IP blocklist/whitelist management
- Domain blacklist for disposable/spam emails
- Rate limiting for abuse prevention
- Spam score calculation and pattern detection
- Browser fingerprinting (non-invasive)

### 🔒 Security & Privacy

- Automated log anonymization (cronjob)
- CSRF protection for all admin actions
- Dashboard API authentication with CORS hardening
- Email masking in API responses
- HMAC token authentication (stateless)
- Secure cookie handling
- Input sanitization against XSS, SQL/email injection

### 📊 Management Dashboard

- CSRF-protected actions (block/unblock/whitelist)
- Secured API endpoint (JSON)
- Real-time analytics and trend visualization
- Block duration and statistics display
- Recent submissions view
- One-click blocking from logs

### 🤖 Automated Operations

- Cronjob-based anonymization
- Configurable retention period
- Execution logging and audit trail
- Email notifications on failures

---

## File Structure

```
contact-form-abuse-prevention/
│
├── assets/
│   ├── php/
│   │   ├── dashboard-login.php          # Secure login (AP-03)
│   │   ├── dashboard.php                # Protected dashboard
│   │   ├── dashboard-api.php            # Secured API
│   │   ├── .env.prod                    # Secrets (not in repo)
│   │   ├── logs/                        # Login and anonymization logs
│   │   └── ...                          # Other backend files
│   ├── css/
│   └── js/
├── cron/
│   ├── anonymize-logs.php               # GDPR cronjob
│   └── README.md                        # Setup guide
├── Documentation/
│   ├── runbook-security-fixes.md        # Security audit
│   ├── HMAC-AUTHENTICATION.md           # Token system
│   └── ...                              # Other docs
├── .env.prod.example                    # Template config
├── README.md                            # This file
└── index.html                           # Documentation viewer
```

---

## Installation

1. **Clone the repository:**  
   `git clone https://github.com/<your-org>/contact-form-abuse-prevention.git`

2. **Configure `.env.prod`:**  
   - Generate Argon2id password hash  
   - Generate a random secret key  
   - Set up SMTP credentials and other options

3. **Set up cronjob for log anonymization:**  
   - See `cron/README.md` for details

4. **Secure your server:**  
   - Use HTTPS  
   - Protect `.env.prod` from public access

---

## Usage

- **Contact form:**  
  Users submit messages via the frontend form.
- **Dashboard:**  
  Admins log in securely, manage blocklists, and view analytics.
- **API:**  
  Authenticated access to dashboard data.
- **Automated operations:**  
  Cronjob anonymizes logs for GDPR compliance.

---

## Security Features

- Argon2id password hashing
- HMAC-SHA256 token authentication
- IP-based rate limiting
- CSRF protection (double submit + JWT binding)
- Secure cookie flags
- Automated log anonymization
- Audit logging for all sensitive actions

---

## GDPR Compliance

- Data minimization and retention policies
- Automated anonymization after 14 days
- Audit trail for compliance verification
- Privacy policy included

---

## Dashboard Features

- Secure login (AP-03)
- CSRF-protected admin actions (AP-02)
- Real-time analytics and blocklist management
- API access with authentication (AP-01)

---

## API Security

- Token-based authentication
- CORS restricted to configured origin
- PII masking in responses

---

## CSRF Protection

- Double Submit Cookie pattern
- JWT token binding
- Audit logging

---

## Automated Log Anonymization

- Cronjob-based IP anonymization
- GDPR-compliant retention
- Execution statistics and audit trail

---

## Testing

- Manual and automated tests for all security features
- Test scripts included for cronjob and dashboard

---

## Troubleshooting

- Detailed error messages and logs
- Documentation for common issues

---

## Contributing

- Open to pull requests and suggestions
- Please follow code style and documentation guidelines

---

## Security Disclosures

- See `SECURITY.md` for responsible disclosure policy

---

## Changelog

- **v4.3.0 (2025-10-06):** AP-03 Password Hashing & Login Rate Limiting (NEW)
- **v4.2.0 (2025-10-05):** CSRF Protection
- **v4.1.0 (2025-10-05):** Dashboard API Security
- **v4.0.0 (2025-10-04):** Domain blacklist, dashboard improvements

---

## About

**Created by a passionate developer in training.**  
This project is a showcase of modern PHP security, privacy, and reliability.  
I believe in learning by doing — every feature is documented and commented for maximum transparency and educational value.

---

## License

MIT

---

**If you value security, privacy, and maintainability, this project is for you.  
Star ⭐ this repo if you find it useful or want to support my journey!**