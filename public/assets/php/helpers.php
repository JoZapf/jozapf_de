<?php
/**
 * Zentrale Hilfsfunktionen für Dashboard + API
 * 
 * Ersetzt die duplizierten env() und verifyToken() Funktionen
 * in dashboard-login.php, dashboard.php, dashboard-api.php,
 * dashboard-blocklist.php (MF-03 FIX).
 * 
 * @version 1.0.0
 * @date 2026-03-24
 * 
 * Changelog v1.0.0 (2026-03-24):
 * - MF-03 FIX: env() und verifyToken() zentralisiert
 * - MF-04 FIX: verifyToken() prüft IP-Bindung
 */

/**
 * Robust env loader — liest aus Process-Env, $_ENV und .env-Dateien.
 * Suchpfade: .env.prod, .app.env, app.env (in assets/php und Webroot)
 */
function env(string $key, ?string $default = null): ?string {
    // 1) Process environment (Docker, systemd etc.)
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];

    // 2) Datei-Fallback
    $candidates = [
        __DIR__ . '/.env.prod',
        __DIR__ . '/.app.env',
        __DIR__ . '/app.env',
        dirname(__DIR__) . '/.app.env',
        dirname(__DIR__) . '/app.env',
        dirname(__DIR__) . '/.env',
    ];

    foreach ($candidates as $envFile) {
        if (!file_exists($envFile)) continue;
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $val] = explode('=', $line, 2);
            if (trim($k) === $key) return trim($val, " \t\n\r\0\x0B\"'");
        }
    }
    return $default;
}

/**
 * HMAC-Token verifizieren (Dashboard-Auth).
 * 
 * MF-04 FIX: Prüft optional die IP-Bindung im Token-Payload.
 * Tokens ohne 'ip'-Feld (alte Tokens vor v2.0.0) werden weiterhin akzeptiert,
 * aber Tokens MIT 'ip'-Feld müssen zur aktuellen IP passen.
 */
function verifyToken(string $token, string $secret): bool {
    if (empty($token) || strpos($token, '.') === false) return false;
    
    [$payload, $signature] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payload, $secret);
    
    // Timing-sicherer Vergleich
    if (!hash_equals($expected, $signature)) return false;
    
    $data = json_decode(base64_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return false;
    
    // MF-04: IP-Bindung prüfen (wenn im Token vorhanden)
    if (isset($data['ip']) && $data['ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        return false;
    }
    
    return true;
}
