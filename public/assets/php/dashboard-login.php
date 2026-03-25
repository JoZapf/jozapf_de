<?php
/**
 * Dashboard Login - HMAC Token Authentication
 * 
 * @version 2.0.0
 * @date 2026-03-24
 * 
 * Changelog v2.0.0 (2026-03-24):
 * - HF-02 FIX: Brute-Force-Schutz via LoginRateLimiter (5 Versuche / 15 min)
 * - HF-05 FIX: Default-Passwort 'admin123' entfernt, hash_equals statt ===
 * - MF-03 FIX: Lokale env()/verifyToken() durch helpers.php ersetzt
 * - MF-04 FIX: Token enthält IP-Bindung, Gültigkeit 4h statt 24h
 * 
 * Changelog v1.0.0 (2025-10-12):
 * - Initial: HMAC Token Authentication mit Cookie
 */

// MF-03 FIX: Zentrale Hilfsfunktionen statt lokaler Duplikate
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/LoginRateLimiter.php';

/**
 * MF-04 FIX: Token mit IP-Bindung und verkürzter Laufzeit.
 * Vorher: 24h, keine IP → gestohlener Cookie von überall nutzbar.
 * Nachher: 4h, IP gebunden → Cookie nur von der Login-IP gültig.
 */
function generateToken(string $secret): string {
    $data = [
        'user' => 'dashboard_admin',
        'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
        'exp'  => time() + (4 * 3600),   // 4h statt 24h
        'iat'  => time()
    ];
    $payload = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $payload, $secret);
    return $payload . '.' . $signature;
}

// Compatibility wrapper for setting cookies with SameSite across PHP versions
function set_cookie_compat(string $name, string $value, int $expires, string $path = '/', bool $secure = true, bool $httponly = true, string $samesite = 'Strict') {
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
        return;
    }
    $cookie = rawurlencode($name) . '=' . rawurlencode($value);
    $cookie .= '; Expires=' . gmdate('D, d-M-Y H:i:s T', $expires);
    $cookie .= '; Path=' . $path;
    if ($secure) $cookie .= '; Secure';
    if ($httponly) $cookie .= '; HttpOnly';
    if ($samesite) $cookie .= '; SameSite=' . $samesite;
    header('Set-Cookie: ' . $cookie, false);
}

// --- Konfiguration ---

// HF-05 FIX: Kein Default-Passwort mehr!
// Vorher: env('DASHBOARD_PASSWORD', 'admin123') ← unsicherer Default
// Nachher: Kein Default. Fehlt beides → Login verweigert.
$DASHBOARD_PASSWORD_HASH = env('DASHBOARD_PASSWORD_HASH');
$DASHBOARD_PASSWORD = env('DASHBOARD_PASSWORD');  // Kein Default!
$DASHBOARD_SECRET = env('DASHBOARD_SECRET');

if (!$DASHBOARD_SECRET) {
    die('ERROR: DASHBOARD_SECRET not set in .env.prod');
}

if (!$DASHBOARD_PASSWORD && !$DASHBOARD_PASSWORD_HASH) {
    die('ERROR: No dashboard password configured. Set DASHBOARD_PASSWORD or DASHBOARD_PASSWORD_HASH in .env.prod');
}

// --- Token-Check: Bereits eingeloggt? ---

$token = $_COOKIE['dashboard_token'] ?? '';
if (verifyToken($token, $DASHBOARD_SECRET)) {
    header('Location: dashboard.php');
    exit;
}

// --- HF-02 FIX: Brute-Force-Schutz ---

$limiter = new LoginRateLimiter(__DIR__ . '/data');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$error = '';
$isLocked = $limiter->isLocked($ip);

if ($isLocked) {
    $remaining = $limiter->getRemainingLockTime($ip);
    $minutes = (int) ceil($remaining / 60);
    $error = "Too many login attempts. Try again in {$minutes} minute(s).";
}

// --- Login-Verarbeitung ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    if (isset($_POST['password'])) {
        $pw = $_POST['password'];
        $ok = false;
        
        // HF-05: Bevorzugt Argon2-Hash prüfen
        if ($DASHBOARD_PASSWORD_HASH && function_exists('password_verify')) {
            if (password_verify($pw, $DASHBOARD_PASSWORD_HASH)) {
                $ok = true;
            }
        }
        
        // HF-05 FIX: Fallback mit hash_equals() statt === (Timing-sicher)
        // Vorher: if ($pw === $DASHBOARD_PASSWORD) ← Timing-Angriff möglich
        // Nachher: hash_equals() hat konstante Laufzeit
        if (!$ok && $DASHBOARD_PASSWORD && hash_equals($DASHBOARD_PASSWORD, $pw)) {
            $ok = true;
        }
        
        if ($ok) {
            // Login erfolgreich → Fehlversuche zurücksetzen
            $limiter->resetAttempts($ip);
            
            $token = generateToken($DASHBOARD_SECRET);
            // MF-04: Cookie-Ablauf angepasst an Token-Laufzeit (4h)
            set_cookie_compat('dashboard_token', $token, time() + (4 * 3600), '/assets/php/', true, true, 'Strict');
            header('Location: dashboard.php');
            exit;
        }
        
        // HF-02: Fehlversuch aufzeichnen
        $limiter->recordFailedAttempt($ip);
        $attemptCount = $limiter->getAttemptCount($ip);
        $remaining = 5 - $attemptCount;
        
        if ($remaining > 0) {
            $error = "Invalid password. {$remaining} attempt(s) remaining.";
        } else {
            $lockMinutes = (int) ceil($limiter->getRemainingLockTime($ip) / 60);
            $error = "Too many login attempts. Try again in {$lockMinutes} minute(s).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Login</title>
    <link rel="stylesheet" href="../css/contact-form.css">
    <style>
        body {
            background: #0d1117;
            color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .login-container { max-width: 400px; width: 100%; padding: 20px; }
        .login-card {
            background: var(--cf-card-bg);
            border: 1px solid var(--cf-card-border);
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: var(--cf-card-shadow);
        }
        h1 { font-size: 24px; margin: 0 0 10px; text-align: center; color: #fff; }
        p { margin: 0 0 30px; text-align: center; color: var(--cf-input-text); font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--cf-input-text); font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: var(--cf-input-bg);
            border: 1px solid var(--cf-input-border);
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover { background: #2980b9; }
        .btn-login:disabled { background: #555; cursor: not-allowed; }
        .error-message {
            background: var(--cf-error-bg-dark);
            border: 1px solid var(--cf-error-border-dark);
            color: var(--cf-error-text-dark);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1>Dashboard Login</h1>
            <p>Contact Form Analytics</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="on">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        autocomplete="current-password"
                        autofocus 
                        required
                        <?= $isLocked ? 'disabled' : '' ?>
                    >
                </div>
                <button type="submit" class="btn-login" <?= $isLocked ? 'disabled' : '' ?>>
                    Access Dashboard
                </button>
            </form>
        </div>
    </div>
</body>
</html>
