# Chart.js Self-Hosting Guide

## Why Self-Host?

### Advantages
- ✅ **CSP compliant:** No external scripts required
- ✅ **Performance:** No additional DNS resolution
- ✅ **Offline capable:** Dashboard works even without internet
- ✅ **Control:** No dependency on CDN availability
- ✅ **Privacy:** No data shared with third parties

### CSP issue
```
Content Security Policy: “script-src ‘self’ 'unsafe-inline'”
```

**Solution:** Host Chart.js locally instead of loading it from CDN

---

## Installation

### Option 1: Download from npm/CDN

```bash
cd /var/www/yourdomain.com/assets/js/

# Download latest version
wget https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.js -O chart.js

# Or minified version
wget https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js -O chart.min.js

# Set permissions
chmod 644 chart.js
```

### Option 2: Via npm

```bash
cd /var/www/yourdomain.com/

# Install Chart.js
npm install chart.js

# Copy to assets
cp node_modules/chart.js/dist/chart.umd.js assets/js/chart.js
```

---

## Dashboard Update

### Old version (CDN):
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
```

### New version (self-hosted):
```html
<script src="../js/chart.js"></script>
<!-- or -->
<script src="/assets/js/chart.js"></script>
```

### Complete dashboard.html change:

**Find this line:**
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
```

```

**Replace with:**
```html
<script src="../js/chart.min.js"></script>
```

---

## Content Security Policy Configuration

### Recommended CSP for jozapf.de

In `.htaccess` or Apache config:

```apache
<IfModule mod_headers.c>
    # Content Security Policy
    Header set Content-Security-Policy "\
        default-src ‘self’; \
        script-src ‘self’ 'unsafe-inline'; \
        style-src ‘self’ 'unsafe-inline'; \
        img-src ‘self’ data: https:; \
        font-src ‘self’ data:; \
        connect-src ‘self’; \
        frame-src ‘self’; \
    "
</IfModule>
```

**Explanation:**
- `script-src ‘self’` - Only own JavaScript files
- `‘unsafe-inline’` - Allows inline scripts (for small scripts)
- `connect-src ‘self’` - AJAX only to own domain

**For Dashboard:**
- Dashboard only fetches `dashboard.php` (same origin) ✅
- All scripts are local ✅
- No CDN required ✅

---

## File Structure

```
/var/www/jozapf.de/
├── assets/
│   ├── js/
│   │   ├── chart.js              ← Self-hosted Chart.js
│   │   ├── chart.min.js          ← Minified version (recommended)
│   │   └── contact-form-logic.js
│   └── php/
│       ├── dashboard.html         ← Update: ../js/chart.min.js
│       └── dashboard.php
```

---

## Verification

### 1. Test whether Chart.js is loaded

```bash
# Check if file exists
ls -lh /var/www/jozapf.de/assets/js/chart.js

# Check if accessible
curl -I https://jozapf.de/assets/js/chart.js
# Should return: HTTP/1.1 200 OK
```

### 2. Browser DevTools (F12)

**Network Tab:**
- ✅ `chart.js` should be loaded from `jozapf.de`
- ❌ No requests to `cdn.jsdelivr.net`

**Console Tab:**
- ✅ No CSP errors
- ✅ `Chart is defined` should be `true`

```javascript
// Test in browser console:
typeof Chart !== ‘undefined’
// Should return: true
```

### 3. Dashboard Test

1. Open: https://jozapf.de/assets/php/dashboard.html
2. Dashboard should load with charts ✅
3. No CSP errors in console ✅

---

## License Compliance

Chart.js is MIT-licensed. **Compliance checklist:**

- ✅ Keep copyright notice in file header:
```javascript
  /*!
   * Chart.js v4.5.0
   * https://www.chartjs.org
   * (c) 2025 Chart.js Contributors
   * Released under the MIT License
   */
  ```

- ✅ Optional: License file in the project:
```bash
  cd /var/www/jozapf.de/assets/js/
  wget https://raw.githubusercontent.com/chartjs/Chart.js/master/LICENSE.md
  ```

- ✅ Optional: Attribution in dashboard footer:
```html
  <footer>
    Dashboard powered by <a href="https://www.chartjs.org">Chart.js</a> (MIT License)
  </footer>
  ```

---

## Updates

### Update Chart.js:

```bash
# Check current version
grep “Chart.js v” /var/www/jozapf.de/assets/js/chart.js

# Download new version
cd /var/www/jozapf.de/assets/js/
mv chart.js chart.js.old
wget https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js -O chart.js

# Test dashboard
# If OK, remove old
rm chart.js.old
```

### Check for Updates:

https://github.com/chartjs/Chart.js/releases

**Current:** v4.5.0 (January 2025)

---

## Performance Optimization

### 1. Use the minified version

```bash
# Minified is ~40% smaller
# chart.js: ~300 KB
# chart.min.js: ~180 KB

wget https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js -O chart.min.js
```

Update in `dashboard.html`:
```html
<script src="../js/chart.min.js"></script>
```

### 2. Gzip compression (Apache)

In `.htaccess`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/javascript application/javascript
</IfModule>
```

### 3. Browser caching

In `.htaccess`:
```apache
<IfModule mod_expires.c>
    <FilesMatch “\.(js)$”>
        ExpiresActive On
        ExpiresDefault “access plus 1 year”
    </FilesMatch>
</IfModule>
```

---

## Troubleshooting

### Problem: “Chart is not defined”

**Check 1: Script loaded?**
```javascript
// In browser console
console.log(typeof Chart);
// Should be: “function”
```

**Check 2: Path correct?**
```html
<!-- dashboard.html is in /assets/php/ -->
<!-- chart.js is in /assets/js/ -->
<!-- Relative path: -->
<script src="../js/chart.js"></script>
<!-- Absolute path: -->
<script src="/assets/js/chart.js"></script>
```

**Check 3: File permissions**
```bash
ls -l /var/www/jozapf.de/assets/js/chart.js
# Should be readable (644)
```

```

### Problem: CSP error despite self-hosting

**Check CSP header:**
```bash
curl -I https://jozapf.de/assets/php/dashboard.html | grep -i “content-security”
```

**If too strict:**
```apache
# Temporarily relax for testing
Header set Content-Security-Policy “default-src ‘self’ 'unsafe-inline' ‘unsafe-eval’;”
```

---

## Alternative: Chart.js Bundle

For minimum size, only necessary components:

```javascript
// Create custom-chart.js
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale } from ‘chart.js’;

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale);

export default Chart;
```

Then bundle with Webpack/Rollup → Only ~60 KB instead of 180 KB!

---

## Best Practices

1. ✅ **Minified version** for production
2. ✅ **Versioning** in the file name: `chart.4.5.0.min.js`
3. ✅ Enable **Gzip compression**
4. ✅ Configure **browser caching**
5. ✅ Remain **CSP compliant**
6. ✅ Check **updates** regularly

---

## Summary

| Method | Size | CSP | Performance | Recommendation |
|---------|-------|-----|-------------|------------|
| **CDN** | ~180 KB | ❌ Requires external | ⚡ Fast (cached) | Not for CSP sites |
| **Self-Hosted (full)** | ~300 KB | ✅ OK | 🐢 Larger | For development |
| **Self-Hosted (min)** | ~180 KB | ✅ OK | ⚡⚡ Fast | **✅ Recommended** |
| **Custom Bundle** | ~60 KB | ✅ OK | ⚡⚡⚡ Very fast | For advanced users |

**Your choice (Self-Hosted min):** ✅ Perfect for production!

---

**Status:** ✅ Self-hosting active & working  
**Version:** Chart.js 4.5.0 (MIT License)  
**CSP:** ✅ Compliant