# Composer Dependencies - Documentation

## 📦 Currently installed

### PHPMailer
- **Location:** `/vendor/phpmailer/phpmailer/`
- **Version:** Check mit `composer show phpmailer/phpmailer`
- **License:** LGPL-2.1
- **Used in:** `assets/php/contact-php-handler.php`

---

## ⚙️ Setup

## Create composer.json (recommended)

If not already present, create the following in the project root:

```json
{
    "name": "your-name/website",
    "description": "your-discription",
    "type": "project",
    "require": {
        "php": ">=7.4",
        "phpmailer/phpmailer": "^6.9"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

---

## 🔄 Updates durchführen

```bash
cd /var/www/yourdomain.com/

# updating dependencies
composer update

# Or only PHPMailer
composer update phpmailer/phpmailer

# Versions-Check
composer show phpmailer/phpmailer
```

---

## ✅ Recommendation

**Everything is currently working.** Only update if:
- Security update available
- New features required
- PHP version changes

**Before updating:** Backup vendor/
```bash
cp -r vendor vendor.backup
```

---

**Status:** ✅ Documented, no update necessary
