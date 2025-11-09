# Migration Security Documentation

<!-- Security Status -->
[![Security Policy](https://img.shields.io/badge/Security-Policy-success?logo=github)](SECURITY.md)
[![Secrets Management](https://img.shields.io/badge/Secrets-External-critical?logo=1password)](#layer-1-local-development-secrets)
[![2FA Required](https://img.shields.io/badge/2FA-Required-success?logo=authy)](#security-best-practices)

<!-- Encryption & Protocols -->
[![FTPS](https://img.shields.io/badge/FTP-TLS%2FFTPS-success?logo=letsencrypt)](#layer-2-cicd-secrets-github-actions)
[![GitHub Secrets](https://img.shields.io/badge/GitHub-Secrets-2088FF?logo=github)](https://github.com/features/actions)

<!-- Compliance -->
[![GDPR Compliant](https://img.shields.io/badge/GDPR-Compliant-success?logo=gdpr)](https://gdpr.eu/)
[![Last Audit](https://img.shields.io/badge/Last%20Audit-2024--11-blue?logo=security)](#security-audit-checklist)

<!-- Vulnerabilities -->
[![npm audit](https://img.shields.io/badge/npm%20audit-0%20vulnerabilities-success?logo=npm)](/)
[![Dependabot](https://img.shields.io/badge/Dependabot-enabled-success?logo=dependabot)](https://github.com/dependabot)

> **Comprehensive Secrets Management & Security Architecture**  
> Documentation for secure credential handling across local development, CI/CD pipelines, and production deployments.

---

## 📑 Table of Contents

- [Overview](#overview)
- [Security Architecture](#security-architecture)
- [Layer 1: Local Development Secrets](#layer-1-local-development-secrets)
- [Layer 2: CI/CD Secrets (GitHub Actions)](#layer-2-cicd-secrets-github-actions)
- [Layer 3: Build-Time Environment Variables](#layer-3-build-time-environment-variables)
- [Setup Instructions](#setup-instructions)
- [Security Best Practices](#security-best-practices)
- [Troubleshooting](#troubleshooting)
- [Security Audit Checklist](#security-audit-checklist)
- [Incident Response](#incident-response)

---

## Overview

### Security Principles

This project follows a **defense-in-depth** approach to secrets management:

1. **Zero Secrets in Git**: No credentials, API keys, or sensitive data in version control
2. **Environment Separation**: Distinct secrets for dev, staging, and production
3. **Least Privilege**: Each component receives only the secrets it needs
4. **Automated Injection**: Build-time secrets from trusted sources (Git metadata)
5. **Audit Trail**: All secret access is logged and reviewable

### Threat Model

**Protected Against**:
- ✅ Credential exposure via public repository
- ✅ Accidental commits of `.env` files
- ✅ Secrets leakage through CI/CD logs
- ✅ Unauthorized access to deployment credentials
- ✅ Man-in-the-middle attacks (FTPS with TLS)

**Out of Scope** (Acknowledged Limitations):
- ⚠️ Compromised developer workstation (physical access)
- ⚠️ GitHub account takeover (2FA required)
- ⚠️ Hetzner webspace breach (shared hosting limitations)

---

## Security Architecture

### Three-Layer Secrets Management

```
┌─────────────────────────────────────────────────────────────────┐
│                    LAYER 1: LOCAL DEVELOPMENT                   │
│                                                                 │
│  Location: External directory (E:/Secrets/jozapf-de/)          │
│  Storage:  app.env (gitignored)                                │
│  Access:   Docker Compose bind mounts                          │
│  Purpose:  Development credentials, test SMTP, local DB        │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ .env (Repository)            │ app.env (External)       │   │
│  │ ─────────────────            │ ──────────────────       │   │
│  │ PROJECT_SLUG=jozapf-de       │ SMTP_HOST=smtp.test.de   │   │
│  │ SECRETS_DIR=E:/Secrets/...   │ SMTP_PASSWORD=dev_pwd    │   │
│  │ HTTP_PORT=8088               │ DB_PASSWORD=dev_db       │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    LAYER 2: CI/CD PIPELINE                      │
│                                                                 │
│  Location: GitHub Secrets (encrypted at rest)                  │
│  Access:   GitHub Actions workflows only                       │
│  Purpose:  Deployment credentials, API keys                    │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ GitHub Repository → Settings → Secrets → Actions        │   │
│  │                                                          │   │
│  │ FTP_SERVER      ████████████████                        │   │
│  │ FTP_USERNAME    ████████████                            │   │
│  │ FTP_PASSWORD    ████████████████████                    │   │
│  │ FTP_DIR         ████████████                            │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                LAYER 3: BUILD-TIME METADATA                     │
│                                                                 │
│  Source:   Git metadata (tags, commit dates)                   │
│  Injection: CI/CD environment variables                        │
│  Purpose:  Versioning, timestamps, build info                  │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Generated at build time:                                 │   │
│  │                                                          │   │
│  │ GIT_TAG=$(git describe --tags)     → v2.0.2            │   │
│  │ BUILD_DATE=$(date -u +%Y-%m-%d)    → 2024-11-09        │   │
│  │                                                          │   │
│  │ Injected into: public/summary.json                      │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Layer 1: Local Development Secrets

### Directory Structure

```
E:/
├── Projects/
│   └── jozapf-de/               # ← Repository (Git tracked)
│       ├── .env                 # ✅ Public config (in Git)
│       ├── .env.example         # ✅ Template (in Git)
│       ├── compose.yml          # ✅ Uses ${SECRETS_DIR}
│       └── ...
│
└── Secrets/
    └── jozapf-de/               # ← NEVER in Git!
        ├── app.env              # ❌ Private secrets
        ├── .htpasswd            # ❌ Dashboard credentials
        └── backup/
            └── app.env.20241109 # ❌ Versioned backups
```

### File: `.env` (Repository)

**Purpose**: Non-sensitive configuration, pointers to secrets  
**Location**: `/workspace/.env`  
**Git Status**: ✅ Committed (safe to share)

```bash
# .env - Public configuration (safe to commit)

# Project identification
PROJECT_SLUG=jozapf-de
PROJECT_ROOT_WINDOWS=E:/Projects/jozapf-de

# Secrets location (OUTSIDE repository)
SECRETS_DIR=E:/Secrets/jozapf-de

# Service ports
HTTP_PORT=8088
NEXT_PORT=3000

# Docker build target
DOCKER_TARGET=dev

# PHP configuration
PHP_TAG=8.3-fpm-alpine
XDEBUG_MODE=off
```

### File: `app.env` (External)

**Purpose**: Sensitive credentials for local development  
**Location**: `${SECRETS_DIR}/app.env` (e.g., `E:/Secrets/jozapf-de/app.env`)  
**Git Status**: ❌ NEVER committed (listed in `.gitignore`)

```bash
# app.env - SECRETS (NEVER commit this file!)
# Location: E:/Secrets/jozapf-de/app.env

# SMTP Configuration (Development)
SMTP_HOST=smtp.mailtrap.io
SMTP_PORT=2525
SMTP_USER=your_mailtrap_user
SMTP_PASSWORD=your_mailtrap_password
SMTP_FROM=dev@jozapf.local

# Database Credentials (Development)
DB_HOST=db
DB_PORT=3306
DB_NAME=jozapf_dev
DB_USER=dev_user
DB_PASSWORD=dev_password_change_me

# Dashboard Authentication (Development)
DASHBOARD_PASSWORD_HASH=$argon2id$v=19$m=65536,t=4,p=1$base64_salt$base64_hash
DASHBOARD_SECRET_KEY=generate_with_openssl_rand_hex_32

# Application Keys (Development)
APP_SECRET=dev_secret_key_change_me
API_KEY=dev_api_key_change_me

# Debugging
APP_DEBUG=true
APP_ENV=development
```

### Docker Compose Integration

```yaml
# compose.yml - Secrets mounting
services:
  php:
    # Load secrets from external file
    env_file:
      - "${SECRETS_DIR}/app.env"
    
    # Bind-mount for legacy PHP code that reads files directly
    volumes:
      - type: bind
        source: "${SECRETS_DIR}/app.env"
        target: /var/www/html/assets/php/app.env
        read_only: true
      
      # Project code
      - ${PROJECT_ROOT_WINDOWS}:/var/www/html:rw

  next-dev:
    # Next.js doesn't need app.env - uses build-time injection
    environment:
      NEXT_TELEMETRY_DISABLED: "1"
    volumes:
      - .:/app
      - next_node_modules:/app/node_modules
```

### Creating Local Secrets

```bash
# 1. Create secrets directory (outside repository)
mkdir -p E:/Secrets/jozapf-de

# 2. Copy template
cp .env.example E:/Secrets/jozapf-de/app.env

# 3. Generate secure passwords
openssl rand -hex 32  # For DASHBOARD_SECRET_KEY
openssl rand -base64 24  # For DB_PASSWORD

# 4. Hash dashboard password
php -r "echo password_hash('your_password', PASSWORD_ARGON2ID);"

# 5. Edit app.env with your credentials
nano E:/Secrets/jozapf-de/app.env

# 6. Secure the file (Windows)
icacls E:\Secrets\jozapf-de\app.env /inheritance:r /grant:r "%USERNAME%:F"

# 6. Secure the file (Linux/WSL)
chmod 600 ~/Secrets/jozapf-de/app.env
```

---

## Layer 2: CI/CD Secrets (GitHub Actions)

### GitHub Secrets Setup

**Location**: `Repository → Settings → Secrets and variables → Actions`

#### Required Secrets

| Secret Name | Description | How to Obtain | Example |
|-------------|-------------|---------------|---------|
| `FTP_SERVER` | Hetzner FTP hostname | Hetzner KonsoleH → FTP-Zugang | `ftp.jozapf.de` |
| `FTP_USERNAME` | FTP username | Hetzner KonsoleH | `u123456789` |
| `FTP_PASSWORD` | FTP password | Hetzner KonsoleH → FTP-Passwort | `***` |
| `FTP_DIR` | Target directory | Server directory structure | `/public_html/` |

#### Step-by-Step Setup

1. **Navigate to GitHub Secrets**
   ```
   GitHub Repository
   → Settings (tab)
   → Secrets and variables (left sidebar)
   → Actions
   → New repository secret (button)
   ```

2. **Add FTP_SERVER**
   ```
   Name: FTP_SERVER
   Secret: ftp.jozapf.de
   → Add secret
   ```

3. **Add FTP_USERNAME**
   ```
   Name: FTP_USERNAME
   Secret: u123456789
   → Add secret
   ```

4. **Add FTP_PASSWORD**
   ```
   Name: FTP_PASSWORD
   Secret: [paste from Hetzner KonsoleH]
   → Add secret
   ```

5. **Add FTP_DIR**
   ```
   Name: FTP_DIR
   Secret: /public_html/
   → Add secret
   ```

### Workflow Usage

```yaml
# .github/workflows/deploy.yml
name: Build and Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Hetzner
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          # Secrets are accessed via ${{ secrets.NAME }}
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          local-dir: ./out/
          server-dir: ${{ secrets.FTP_DIR }}
          
          # Security: Exclude sensitive files
          exclude: |
            **/.git*
            **/.env*
            **/node_modules/**
            **/*.log
```

### Secret Rotation Procedure

**When to Rotate**:
- ✅ Every 90 days (scheduled)
- ✅ After team member departure
- ✅ On suspected compromise
- ✅ After failed security audit

**How to Rotate**:

```bash
# 1. Generate new FTP password in Hetzner KonsoleH
# 2. Update GitHub Secret
Repository → Settings → Secrets → FTP_PASSWORD → Update

# 3. Test deployment with new credentials
git commit --allow-empty -m "test: verify secret rotation"
git push

# 4. Monitor GitHub Actions for successful deployment
gh run watch

# 5. Document rotation in security log
echo "$(date -u): FTP_PASSWORD rotated" >> SECURITY_LOG.md
```

---

## Layer 3: Build-Time Environment Variables

### Automated Metadata Injection

**Purpose**: Inject version and timestamp information from Git metadata into the build  
**Source**: Git tags and commit timestamps  
**Target**: `public/summary.json`

### Workflow Implementation

```yaml
# .github/workflows/deploy.yml
jobs:
  build-deploy:
    steps:
      - name: Checkout with full history
        uses: actions/checkout@v4
        with:
          fetch-depth: 0  # ← Required for git describe --tags

      - name: Extract build metadata
        run: |
          # Get latest Git tag (e.g., v2.0.2)
          GIT_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo '')
          echo "GIT_TAG=${GIT_TAG}" >> $GITHUB_ENV
          
          # Get current timestamp (ISO 8601)
          BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ')
          echo "BUILD_DATE=${BUILD_DATE}" >> $GITHUB_ENV
          
          # Log for audit trail
          echo "Build metadata: ${GIT_TAG} @ ${BUILD_DATE}"

      - name: Build with injected metadata
        env:
          GIT_TAG: ${{ env.GIT_TAG }}
          BUILD_DATE: ${{ env.BUILD_DATE }}
        run: |
          npm run build
          
          # Verify injection worked
          cat public/summary.json | jq '.'
```

### Script: `generate-summary.ts`

```typescript
// scripts/generate-summary.ts
import fs from "node:fs";
import path from "node:path";
import { execSync } from "node:child_process";

// Fallback functions for local development
function getPkgVersion(): string {
  const pkgPath = path.join(process.cwd(), "package.json");
  const pkg = JSON.parse(fs.readFileSync(pkgPath, "utf-8"));
  return pkg.version ?? "0.0.0";
}

function getGitIsoDate(): string {
  try {
    const iso = execSync('git log -1 --pretty="%cI"')
      .toString()
      .trim()
      .replace(/"/g, "");
    return iso;
  } catch {
    return new Date().toISOString();
  }
}

async function main() {
  // Priority: CI environment variables → fallbacks
  const version =
    process.env.GIT_TAG?.replace(/^v/, "") ||  // CI: from Git tag
    process.env.npm_package_version ||          // npm context
    getPkgVersion();                             // package.json

  const last_updated =
    process.env.BUILD_DATE ||                    // CI: from workflow
    getGitIsoDate();                             // Git log

  const summary = {
    project: "jozapf.de",
    version,
    last_updated,
    key_points: [
      "Static export for shared hosting",
      "Automated CI/CD via GitHub Actions",
      "Docker-based development workflow",
      "TypeScript + Next.js 16",
    ],
  };

  // Write to public/ for inclusion in build
  const outDir = path.join(process.cwd(), "public");
  fs.mkdirSync(outDir, { recursive: true });
  
  const outFile = path.join(outDir, "summary.json");
  fs.writeFileSync(outFile, JSON.stringify(summary, null, 2) + "\n");

  console.log(`✅ Generated summary.json: ${version} @ ${last_updated}`);
}

main().catch((err) => {
  console.error("❌ Failed to generate summary:", err);
  process.exit(1);
});
```

### Verification

```bash
# Local build (uses fallbacks)
npm run build
cat out/summary.json

# Expected output (local):
{
  "project": "jozapf.de",
  "version": "2.0.2",
  "last_updated": "2024-11-09T12:34:56Z",
  "key_points": [...]
}

# CI build (uses GIT_TAG and BUILD_DATE)
# Check GitHub Actions logs or deployed site:
curl https://jozapf.de/summary.json | jq .
```

---

## Setup Instructions

### New Developer Onboarding

#### 1. Clone Repository
```bash
git clone git@github.com:JoZapf/jozapf-de.git
cd jozapf-de
```

#### 2. Create Secrets Directory
```bash
# Windows (PowerShell)
New-Item -Path "E:\Secrets\jozapf-de" -ItemType Directory -Force

# Linux/macOS
mkdir -p ~/Secrets/jozapf-de
```

#### 3. Copy Template and Fill Secrets
```bash
# Copy template
cp .env.example E:/Secrets/jozapf-de/app.env  # Windows
cp .env.example ~/Secrets/jozapf-de/app.env   # Linux/macOS

# Edit with your credentials
nano ~/Secrets/jozapf-de/app.env
```

#### 4. Generate Secure Passwords
```bash
# Dashboard secret key (32 bytes hex)
openssl rand -hex 32

# Database password (24 bytes base64)
openssl rand -base64 24

# Hash dashboard password (Argon2id)
php -r "echo password_hash('YourSecurePassword123', PASSWORD_ARGON2ID) . PHP_EOL;"
```

#### 5. Verify Setup
```bash
# Check that secrets directory is outside repo
ls -la $(git rev-parse --show-toplevel)/../Secrets/jozapf-de/

# Start development environment
docker compose --profile next up next-dev

# Check that secrets are mounted correctly
docker compose exec php cat /var/www/html/assets/php/app.env
```

### Production Deployment Setup

#### 1. Obtain Hetzner Credentials
```
1. Log into Hetzner KonsoleH
2. Navigate to: FTP-Zugang
3. Note: Hostname, Username, Password
4. Note: Target directory (usually /public_html/)
```

#### 2. Add GitHub Secrets
```
1. GitHub → Repository → Settings → Secrets → Actions
2. Add: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD, FTP_DIR
```

#### 3. Test Deployment
```bash
# Create a test commit
git commit --allow-empty -m "test: verify deployment"
git push

# Watch GitHub Actions
gh run watch

# Verify deployment
curl https://jozapf.de/summary.json
```

---

## Security Best Practices

### ✅ Do's

1. **Use Strong Passwords**
   ```bash
   # Minimum 32 characters, random
   openssl rand -base64 32
   ```

2. **Enable 2FA on GitHub**
   ```
   GitHub → Settings → Password and authentication
   → Two-factor authentication → Enable
   ```

3. **Rotate Secrets Regularly**
   ```
   - Dashboard passwords: Every 90 days
   - FTP credentials: Every 180 days
   - API keys: On every team change
   ```

4. **Use Read-Only Mounts**
   ```yaml
   volumes:
     - type: bind
       source: "${SECRETS_DIR}/app.env"
       target: /var/www/html/assets/php/app.env
       read_only: true  # ← Prevents accidental overwrites
   ```

5. **Audit Secret Access**
   ```bash
   # Check GitHub Actions logs for secret usage
   gh run view --log | grep -i "secret"
   
   # Check Docker logs for mounted secrets
   docker compose logs php | grep "app.env"
   ```

6. **Use FTPS, Not Plain FTP**
   ```yaml
   # Always use protocol: ftps
   with:
     protocol: ftps  # ← TLS encryption
     port: 21
   ```

### ❌ Don'ts

1. **Never Commit Secrets**
   ```bash
   # Check before committing
   git diff --cached | grep -i "password\|secret\|key"
   
   # Use git-secrets for automated checks
   git secrets --scan
   ```

2. **Never Log Secrets**
   ```typescript
   // ❌ BAD
   console.log("DB_PASSWORD:", process.env.DB_PASSWORD);
   
   // ✅ GOOD
   console.log("DB connection configured");
   ```

3. **Never Share Secrets via Chat/Email**
   ```
   ❌ Slack: "Here's the FTP password: hunter2"
   ✅ Use: 1Password shared vault or similar
   ```

4. **Never Use Weak Passwords**
   ```bash
   # ❌ BAD: Dictionary words, sequential
   password123, qwerty, admin
   
   # ✅ GOOD: Random, 32+ chars
   openssl rand -base64 32
   ```

5. **Never Reuse Passwords**
   ```
   Each service needs unique credentials:
   - Dev SMTP ≠ Prod SMTP
   - Local DB ≠ Staging DB ≠ Prod DB
   ```

---

## Troubleshooting

### Issue: "Secrets not found in Docker container"

**Symptoms**:
```
Error: Cannot read app.env: No such file or directory
```

**Diagnosis**:
```bash
# Check if SECRETS_DIR is set
echo $SECRETS_DIR

# Check if file exists on host
ls -la E:/Secrets/jozapf-de/app.env  # Windows
ls -la ~/Secrets/jozapf-de/app.env   # Linux

# Check Docker mount
docker compose config | grep app.env
```

**Solution**:
```bash
# 1. Verify .env file has correct SECRETS_DIR
cat .env | grep SECRETS_DIR

# 2. Create missing directory
mkdir -p E:/Secrets/jozapf-de

# 3. Copy template
cp .env.example E:/Secrets/jozapf-de/app.env

# 4. Restart containers
docker compose down
docker compose --profile next up next-dev
```

---

### Issue: "GitHub Actions deployment fails with 530 Login incorrect"

**Symptoms**:
```
Error: FTP login failed: 530 Login incorrect
```

**Diagnosis**:
```bash
# Test FTP credentials locally
ftp ftp.jozapf.de
# Username: [enter FTP_USERNAME]
# Password: [enter FTP_PASSWORD]
```

**Solution**:
```bash
# 1. Verify credentials in Hetzner KonsoleH
# 2. Update GitHub Secret
GitHub → Settings → Secrets → FTP_PASSWORD → Update

# 3. Test with manual workflow dispatch
GitHub → Actions → deploy.yml → Run workflow

# 4. Check logs
gh run view --log
```

---

### Issue: "Build succeeds but summary.json has wrong version"

**Symptoms**:
```json
{
  "version": "0.0.0-dev",
  "last_updated": "2024-01-01T00:00:00Z"
}
```

**Diagnosis**:
```bash
# Check if Git tags exist
git describe --tags

# Check GitHub Actions environment
gh run view --log | grep "GIT_TAG\|BUILD_DATE"
```

**Solution**:
```bash
# 1. Ensure fetch-depth: 0 in workflow
cat .github/workflows/deploy.yml | grep fetch-depth

# 2. Create Git tag if missing
git tag v2.0.2
git push --tags

# 3. Trigger new build
git commit --allow-empty -m "chore: trigger build"
git push
```

---

### Issue: "Secrets visible in CI/CD logs"

**Symptoms**:
```
[deploy] Connecting to ftp.jozapf.de with password: hunter2
```

**Diagnosis**:
```bash
# Check workflow for echo statements
cat .github/workflows/deploy.yml | grep "echo.*secret"
```

**Solution**:
```yaml
# Remove debug statements that print secrets
# ❌ BAD
- run: echo "Password is ${{ secrets.FTP_PASSWORD }}"

# ✅ GOOD
- run: echo "FTP credentials configured"

# GitHub automatically masks secrets, but don't echo them
```

---

## Security Audit Checklist

### Monthly Review

- [ ] No `.env` files with secrets committed to Git
- [ ] All secrets stored in external directory (`SECRETS_DIR`)
- [ ] GitHub Actions secrets up-to-date
- [ ] No secrets logged in CI/CD output
- [ ] No secrets in Docker images (use `docker history`)
- [ ] `app.env` has correct file permissions (600)
- [ ] `.gitignore` includes all secret paths
- [ ] Team members have 2FA enabled
- [ ] FTP password complexity meets requirements (16+ chars)
- [ ] No secrets shared via insecure channels

### Quarterly Review

- [ ] Rotate FTP credentials
- [ ] Rotate dashboard passwords
- [ ] Review GitHub Actions audit log
- [ ] Review Docker Compose secret mounts
- [ ] Test secret recovery procedure
- [ ] Update `.env.example` template
- [ ] Verify backups of `app.env` exist
- [ ] Check for leaked secrets with `git-secrets` or `trufflehog`
- [ ] Review this document for updates
- [ ] Onboard new team members with security training

### Commands for Auditing

```bash
# Check for committed secrets (basic)
git log -p | grep -i "password\|secret\|key" | head -20

# Check for committed secrets (advanced)
trufflehog git file://. --only-verified

# Check .gitignore effectiveness
git check-ignore -v E:/Secrets/jozapf-de/app.env

# Verify Docker mount permissions
docker compose exec php ls -la /var/www/html/assets/php/app.env

# Check GitHub Actions recent runs
gh run list --limit 10

# Verify FTPS (not FTP) in workflow
cat .github/workflows/deploy.yml | grep "protocol:"
```

---

## Incident Response

### Scenario 1: Secrets Accidentally Committed

**Immediate Actions**:
```bash
# 1. DO NOT just delete the file and commit
# (History still contains secrets!)

# 2. Remove from history with git-filter-repo
git filter-repo --path .env --invert-paths
git filter-repo --path app.env --invert-paths

# 3. Force push (requires team coordination)
git push origin --force --all

# 4. Rotate ALL exposed secrets immediately
# - Generate new passwords
# - Update GitHub Secrets
# - Update local app.env

# 5. Notify team
echo "SECURITY INCIDENT: Secrets exposed in commit $(git rev-parse HEAD)"
```

### Scenario 2: Compromised FTP Credentials

**Immediate Actions**:
```bash
# 1. Change FTP password in Hetzner KonsoleH
# 2. Update GitHub Secret immediately
# 3. Review FTP access logs for suspicious activity
# 4. Check deployed files for tampering
# 5. Deploy clean build from known-good commit
# 6. Document incident in SECURITY_LOG.md
```

### Scenario 3: Unauthorized GitHub Actions Run

**Immediate Actions**:
```bash
# 1. Cancel running workflow
gh run cancel <run-id>

# 2. Revoke GitHub personal access tokens
GitHub → Settings → Developer settings → Tokens

# 3. Enable branch protection
GitHub → Settings → Branches → Add rule
  - Require pull request reviews
  - Require status checks

# 4. Review Actions audit log
GitHub → Settings → Actions → Logs

# 5. Rotate all GitHub Secrets
```

### Emergency Contacts

```
GitHub Security: https://github.com/security
Hetzner Support: +49 (0)9831 505-0
Project Lead: [your email]
```

---

## Appendix: Template Files

### `.env.example`

```bash
# .env.example - Template for .env (safe to commit)
# Copy to .env and adjust values

PROJECT_SLUG=jozapf-de
PROJECT_ROOT_WINDOWS=E:/Projects/jozapf-de
SECRETS_DIR=E:/Secrets/jozapf-de
HTTP_PORT=8088
NEXT_PORT=3000
DOCKER_TARGET=dev
PHP_TAG=8.3-fpm-alpine
XDEBUG_MODE=off
```

### `app.env.example`

```bash
# app.env.example - Template for secrets (NEVER commit actual app.env!)
# Copy to ${SECRETS_DIR}/app.env and fill with real credentials

# SMTP Configuration
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=noreply@example.com
SMTP_PASSWORD=CHANGE_ME_generate_with_openssl_rand
SMTP_FROM=noreply@example.com

# Database Credentials
DB_HOST=db
DB_PORT=3306
DB_NAME=example_db
DB_USER=example_user
DB_PASSWORD=CHANGE_ME_generate_with_openssl_rand

# Dashboard Authentication
DASHBOARD_PASSWORD_HASH=CHANGE_ME_hash_with_php_password_hash
DASHBOARD_SECRET_KEY=CHANGE_ME_generate_with_openssl_rand_hex_32

# Application Keys
APP_SECRET=CHANGE_ME_generate_with_openssl_rand
API_KEY=CHANGE_ME_generate_with_openssl_rand

# Environment
APP_DEBUG=true
APP_ENV=development
```

### `.gitignore` (Secrets Section)

```gitignore
# Secrets and Sensitive Data
.env.local
.env.*.local
app.env
*.env.backup
secrets/
**/secrets/

# Hetzner FTP Credentials
.ftpconfig
.ftppass

# Password Files
.htpasswd
*.pem
*.key
*.crt

# Docker Secrets
docker-compose.override.yml
```

---

## Changelog

- **2024-11-09**: Initial security documentation for Next.js migration
- **2024-11-09**: Added three-layer secrets architecture
- **2024-11-09**: Documented GitHub Actions secrets setup
- **2024-11-09**: Added incident response procedures

---

## License

This security documentation is part of the jozapf.de project and is licensed under the MIT License.

---

## Contact

For security concerns or questions about this documentation:

**Jo Zapf**  
Email: [Contact via GitHub](https://github.com/JoZapf)  
Security Issues: [Report via GitHub Security](https://github.com/JoZapf/jozapf-de/security)

---

**🔒 Security is not a feature – it's a foundation.**

*Last Updated: 2024-11-09 | Version: 1.0.0*
