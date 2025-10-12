# Dashboard Login Security Fix (AP-03)

This repository implements a secure, stateless authentication system for the dashboard using HMAC-signed tokens and Argon2id password hashing. The solution addresses critical vulnerabilities identified in AP-03 of the security runbook.

---

## 🔒 What Was Fixed

- **Plaintext Password Comparison:**  
  Replaced with Argon2id password hashing and verification.
- **No Rate Limiting:**  
  Added IP-based rate limiting (max 5 failed logins per 15 minutes).
- **Session Vulnerabilities:**  
  Migrated to stateless HMAC token authentication (no PHP sessions required for access).
- **Sensitive Data Exposure:**  
  Password hash and secret key are stored securely in `.env.prod` (never in code or version control).

---

## 🚀 How It Works

1. **Password Hashing:**  
   - Passwords are hashed using Argon2id.
   - On login, `password_verify()` checks the submitted password against the hash from `.env.prod`.

2. **Rate Limiting:**  
   - Failed login attempts are logged per IP.
   - After 5 failed attempts in 15 minutes, further attempts are blocked (HTTP 429).

3. **HMAC Token Authentication:**  
   - Upon successful login, a signed token is generated:
     - Payload: `{ "exp": <expiry>, "csrf": <csrf_token> }`
     - Signature: HMAC-SHA256 using a secret from `.env.prod`
   - Token is stored in a secure, HttpOnly cookie.
   - Dashboard access is granted only if the token is valid and unexpired.

4. **CSRF Protection:**  
   - Double Submit Cookie pattern: CSRF token is stored in a cookie and verified on every POST.

---

## 🛡️ Security Features

- **Argon2id password hashing**
- **HMAC-SHA256 signed tokens**
- **Secure cookies (`HttpOnly`, `Secure`, `SameSite=Strict`)**
- **IP-based rate limiting**
- **CSRF protection**
- **No plaintext passwords or secrets in code**

---

## 📝 Usage

1. **Configure `.env.prod`:**
   ```
   DASHBOARD_PASSWORD_HASH=<argon2id hash>
   DASHBOARD_SECRET=<random 32+ byte secret>
   ```
2. **Login Flow:**
   - User submits password.
   - If valid, receives a signed token in a secure cookie.
   - Dashboard checks token validity on every request.

3. **Rate Limiting:**
   - After 5 failed logins per IP in 15 minutes, login is blocked for that IP.

---

## 📂 File Structure

- `assets/php/dashboard-login.php` — Login form, password verification, token generation
- `assets/php/dashboard.php` — Protected dashboard, token verification
- `assets/php/.env.prod` — Secrets and password hash (never commit to GitHub)
- `assets/php/logs/login_attempts.jsonl` — Failed login log (for rate limiting)

---

## ✅ Best Practices

- Use HTTPS for all dashboard access.
- Protect `.env.prod` with server config (e.g., `.htaccess`).
- Rotate secrets regularly.
- Monitor failed login logs for abuse.
- Never store plaintext passwords or secrets in code or version control.

---

## 📖 References

- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [NIST FIPS 198-1: HMAC](https://csrc.nist.gov/publications/detail/fips/198/1/final)
- [PHP: password_hash()](https://www.php.net/manual/en/function.password-hash.php)
- [PHP: hash_hmac()](https://www.php.net/manual/en/function.hash-hmac.php)

---

**Status:** Production Ready  
**License:** MIT  