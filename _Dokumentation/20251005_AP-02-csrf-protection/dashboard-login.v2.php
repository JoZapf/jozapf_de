<?php
/**
 * Dashboard Login - HMAC Token Authentication with CSRF Protection
 * 
 * @version     2.0.0
 * @date        2025-10-05 20:00:00 UTC
 * @repository  https://github.com/JoZapf/contact-form-abuse-prevention
 * @package     ContactFormAbusePrevention
 * @author      Jo Zapf
 * 
 * CHANGELOG v2.0.0 (2025-10-05):
 * - [SECURITY] Added CSRF token generation (AP-02)
 * - [SECURITY] CSRF token embedded in JWT payload (AP-02)
 * - [SECURITY] Separate csrf_token cookie (non-HttpOnly for form access) (AP-02)
 * - [BREAKING] generateToken() now returns array: [jwt_token, csrf_token]
 * - [SECURITY] Double Submit Cookie pattern implementation
 * 
 * Previous version: v1.0.0 (HMAC authentication only)
 */

// ============================================================================
// CONFIGURATION: Environment Variables
// ============================================================================

/**
 * Load environment variable from .env.prod
 * 
 * @param string $key Variable name
 * @param mixed $default Default value if not found
 * @return mixed Variable value or default
 */
function env($key, $default = null) {
    $envFile = __DIR__ . '/.env.prod';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                [$k, $v] = explode('=', trim($line), 2);
                if (trim($k) === $key) return trim($v, '"\'');
            }
        }
    }
    return $default;
}

// ============================================================================
// SECURITY: Token Generation with CSRF Protection (AP-02)
// ============================================================================

/**
 * Generate HMAC token with embedded CSRF token
 * 
 * Creates a JWT-style token with:
 * - Standard claims (exp, iat, user)
 * - Embedded CSRF token for double-submit cookie pattern
 * - HMAC-SHA256 signature
 * 
 * @param string $secret DASHBOARD_SECRET from .env.prod
 * @return array [jwt_token, csrf_token]
 * 
 * @since v2.0.0 Returns array (breaking change from v1.0.0)
 */
function generateToken($secret) {
    // CSRF-Token generieren (32 Bytes = 64 Hex-Zeichen)
    $csrfToken = bin2hex(random_bytes(32));
    
    // JWT-Payload mit CSRF-Token
    $data = [
        'user' => 'dashboard_admin',
        'exp' => time() + (24 * 3600),  // 24 Stunden
        'iat' => time(),
        'csrf' => $csrfToken  // ← NEU in v2.0.0 (AP-02)
    ];
    
    // JWT erstellen
    $payload = base64_encode(json_encode($data));
    $signature = hash_hmac('sha256', $payload, $secret);
    $jwtToken = $payload . '.' . $signature;
    
    return [$jwtToken, $csrfToken];
}

/**
 * Verify HMAC token
 * 
 * @param string $token JWT token from cookie
 * @param string $secret DASHBOARD_SECRET from .env.prod
 * @return bool True if valid and not expired
 */
function verifyToken($token, $secret) {
    if (empty($token) || strpos($token, '.') === false) return false;
    [$payload, $signature] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) return false;
    $data = json_decode(base64_decode($payload), true);
    return $data && isset($data['exp']) && $data['exp'] >= time();
}

// ============================================================================
// CONFIGURATION: Load Credentials
// ============================================================================

$DASHBOARD_PASSWORD = env('DASHBOARD_PASSWORD', 'admin123');
$DASHBOARD_SECRET = env('DASHBOARD_SECRET');

if (!$DASHBOARD_SECRET) {
    die('ERROR: DASHBOARD_SECRET not set in .env.prod');
}

// ============================================================================
// AUTHENTICATION: Check Existing Session
// ============================================================================

$token = $_COOKIE['dashboard_token'] ?? '';
if (verifyToken($token, $DASHBOARD_SECRET)) {
    header('Location: dashboard.php');
    exit;
}

// ============================================================================
// AUTHENTICATION: Handle Login Request
// ============================================================================

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $DASHBOARD_PASSWORD) {
        // Token-Generierung mit CSRF-Token (v2.0.0)
        [$token, $csrfToken] = generateToken($DASHBOARD_SECRET);
        
        // Dashboard-Token (HttpOnly, da JWT-Payload sensibel)
        setcookie('dashboard_token', $token, [
            'expires' => time() + (24 * 3600),
            'path' => '/assets/php/',
            'secure' => true,
            'httponly' => true,   // ← Nicht JavaScript-lesbar
            'samesite' => 'Strict'
        ]);
        
        // CSRF-Token (NICHT HttpOnly, für Formular-Zugriff)
        setcookie('csrf_token', $csrfToken, [
            'expires' => time() + (24 * 3600),
            'path' => '/assets/php/',
            'secure' => true,
            'httponly' => false,  // ← WICHTIG: Muss für Formulare lesbar sein!
            'samesite' => 'Strict'
        ]);
        
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid password';
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
        .version-badge {
            display: inline-block;
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
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
            <div style="text-align: center;">
                <span class="version-badge">v2.0.0 - CSRF Protected</span>
            </div>
            
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
                    >
                </div>
                <button type="submit" class="btn-login">Access Dashboard</button>
            </form>
        </div>
    </div>
</body>
</html>
