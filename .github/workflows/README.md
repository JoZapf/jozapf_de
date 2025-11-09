# GitHub Workflows & Security Automation

This directory contains automated workflows for security scanning, dependency management, and code quality checks.

## 📋 Overview

| Workflow | Purpose | Trigger | Badge |
|----------|---------|---------|-------|
| **CodeQL** | Code security analysis | Push, PR, Weekly | [![CodeQL](https://github.com/JoZapf/jozapf-de/workflows/CodeQL%20Analysis/badge.svg)](https://github.com/JoZapf/jozapf-de/security/code-scanning) |
| **Security Audit** | NPM vulnerability scanning | Push, PR, Weekly | [![Security Audit](https://github.com/JoZapf/jozapf-de/workflows/Security%20Audit/badge.svg)](https://github.com/JoZapf/jozapf-de/actions/workflows/security-audit.yml) |
| **Dependabot** | Automated dependency updates | Daily | [![Dependabot](https://img.shields.io/badge/Dependabot-enabled-success?logo=dependabot)](https://github.com/dependabot) |

---

## 🛡️ Security Workflows

### CodeQL Analysis (`codeql.yml`)

**What it does:**
- Scans JavaScript/TypeScript code for security vulnerabilities
- Detects common coding errors and security issues
- Runs on every push to main/develop, PRs, and weekly

**First-time setup:**
1. Enable Code Scanning in repository settings:
   ```
   Repository → Settings → Code security and analysis
   → Code scanning → Set up → Configure CodeQL alerts
   ```

2. The workflow will run automatically on the next push

**View results:**
```
Repository → Security → Code scanning alerts
```

**Manual trigger:**
```bash
gh workflow run codeql.yml
```

---

### Security Audit (`security-audit.yml`)

**What it does:**
- Runs `npm audit` to check for known vulnerabilities
- Scans both production and development dependencies
- Fails if critical or high severity vulnerabilities found
- Posts results as PR comments

**Features:**
- ✅ Separate production/dev dependency scanning
- ✅ Summary in GitHub Actions UI
- ✅ Automatic PR comments with results
- ✅ JSON artifacts for further analysis
- ✅ Optional Snyk integration

**First-time setup:**
No setup required! Works out of the box.

**Optional: Enable Snyk scanning**
1. Sign up at [snyk.io](https://snyk.io/)
2. Get your Snyk token
3. Add as GitHub Secret:
   ```
   Repository → Settings → Secrets → Actions
   → New repository secret
   Name: SNYK_TOKEN
   Value: [your token]
   ```

**Manual trigger:**
```bash
gh workflow run security-audit.yml
```

**View results:**
```bash
# Latest run
gh run list --workflow=security-audit.yml --limit 1

# Download audit artifacts
gh run download [run-id]
```

---

## 🤖 Dependabot (`dependabot.yml`)

**What it does:**
- Automatically checks for outdated dependencies
- Creates pull requests for updates
- Groups minor/patch updates together
- Monitors NPM, Docker, and GitHub Actions

**Configured for:**
- **NPM packages**: Weekly updates, Monday 9:00 AM CET
- **Docker images**: Weekly updates, Monday 9:00 AM CET
- **GitHub Actions**: Weekly updates, Monday 9:00 AM CET

**Features:**
- ✅ Security updates are always created immediately
- ✅ Groups non-security updates to reduce PR noise
- ✅ Auto-assigns reviewers
- ✅ Conventional commit messages

**First-time setup:**
1. Enable Dependabot in repository settings:
   ```
   Repository → Settings → Code security and analysis
   → Dependabot alerts → Enable
   → Dependabot security updates → Enable
   ```

2. Dependabot will start creating PRs based on `dependabot.yml`

**View Dependabot PRs:**
```bash
gh pr list --label dependencies
```

**Approve and merge Dependabot PR:**
```bash
# Review the changes
gh pr view [PR-number]

# If tests pass, approve and merge
gh pr review [PR-number] --approve
gh pr merge [PR-number] --squash
```

---

## 🚀 Quick Start

### Enable all security features

```bash
# 1. Commit the workflows (already done if you're reading this!)
git add .github/
git commit -m "ci: add security workflows and dependabot"
git push

# 2. Enable GitHub security features
# Go to: Repository → Settings → Code security and analysis
# Enable:
# - Dependabot alerts
# - Dependabot security updates
# - Code scanning (CodeQL)

# 3. Trigger workflows manually for first run
gh workflow run codeql.yml
gh workflow run security-audit.yml

# 4. Check results
gh run list
```

### Monitor security status

```bash
# Check all workflow runs
gh run list --limit 10

# Check for vulnerabilities
gh api repos/:owner/:repo/code-scanning/alerts

# Check Dependabot alerts
gh api repos/:owner/:repo/dependabot/alerts
```

---

## 📊 Understanding the Results

### CodeQL Results

**Severity levels:**
- 🔴 **Critical**: Immediate action required
- 🟠 **High**: Should be fixed soon
- 🟡 **Medium**: Fix when possible
- 🔵 **Low**: Nice to fix

**Common findings:**
- SQL injection vulnerabilities
- Cross-site scripting (XSS)
- Hardcoded credentials
- Insecure random number generation
- Path traversal

**Actions:**
```
Security → Code scanning → View alert → Show paths → Fix code
```

### NPM Audit Results

**Vulnerability types:**
- **Critical**: Remote code execution, data exposure
- **High**: Denial of service, authentication bypass
- **Moderate**: Information disclosure
- **Low**: Minor issues

**Actions:**
```bash
# View details
npm audit

# Fix automatically (if possible)
npm audit fix

# Force update (breaking changes possible)
npm audit fix --force

# Manually update specific package
npm update [package-name]
```

### Dependabot PRs

**PR types:**
- 🔒 **Security update**: Fixes known vulnerability
- ⬆️ **Version update**: New feature or bug fix
- 📦 **Grouped update**: Multiple minor/patch updates

**Review checklist:**
- [ ] Check CI/CD status (all tests pass)
- [ ] Review changelog for breaking changes
- [ ] Test locally if major version update
- [ ] Approve and merge

---

## 🔧 Customization

### Adjust scan frequency

**Edit schedule in workflow file:**
```yaml
# Weekly (default)
schedule:
  - cron: '0 9 * * 1'  # Monday 9:00 AM

# Daily
schedule:
  - cron: '0 9 * * *'  # Every day 9:00 AM

# Monthly
schedule:
  - cron: '0 9 1 * *'  # First day of month 9:00 AM
```

### Change Dependabot schedule

**Edit `.github/dependabot.yml`:**
```yaml
schedule:
  interval: "daily"   # daily, weekly, monthly
  day: "monday"       # For weekly
  time: "09:00"
  timezone: "Europe/Berlin"
```

### Ignore specific vulnerabilities

**Create `.npmrc` in project root:**
```
audit-level=moderate
```

**Or use npm audit signatures:**
```bash
# Ignore specific advisory
npm audit --audit-level=moderate --exclude=1234567
```

---

## 📚 Additional Resources

- [GitHub Code Scanning](https://docs.github.com/en/code-security/code-scanning)
- [Dependabot Configuration](https://docs.github.com/en/code-security/dependabot)
- [npm audit Documentation](https://docs.npmjs.com/cli/v10/commands/npm-audit)
- [CodeQL Query Language](https://codeql.github.com/docs/)
- [Security Best Practices](../MIGRATION_SECURITY.md)

---

## 🆘 Troubleshooting

### CodeQL fails with "No code to analyze"

**Solution:**
```yaml
# Add autobuild step
- name: Autobuild
  uses: github/codeql-action/autobuild@v3
```

### npm audit fails with ENOTFOUND

**Solution:**
```bash
# Check network connectivity in Actions
- run: npm config set registry https://registry.npmjs.org/
```

### Dependabot PRs not created

**Check:**
1. Dependabot is enabled in settings
2. `dependabot.yml` syntax is correct
3. No conflicts with existing PRs
4. Check Dependabot logs: `Settings → Code security → Dependabot → View logs`

### Too many Dependabot PRs

**Solution:**
```yaml
# Group updates
groups:
  all-dependencies:
    patterns:
      - "*"
    update-types:
      - "minor"
      - "patch"
```

---

**Last Updated:** 2024-11-09  
**Maintainer:** Jo Zapf

For security concerns, see [MIGRATION_SECURITY.md](../MIGRATION_SECURITY.md)
