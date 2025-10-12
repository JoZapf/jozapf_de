<?php
header('Content-Type: text/plain; charset=utf-8');

/* --- Loader (robust, ohne parse_ini_file Arrays) --- */
function parse_dotenv_file(string $file): array {
    if (!is_readable($file)) return [];
    $out = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return $out;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (strpos($line, '#') !== false && !preg_match('/["\'].*#.*["\']/', $line)) {
            $line = trim(substr($line, 0, strpos($line, '#')));
        }
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (str_ends_with($key, '[]')) continue;
        if ((strlen($val) >= 2) && (
            ($val[0] === '"' && substr($val, -1) === '"') ||
            ($val[0] === "'" && substr($val, -1) === "'")
        )) { $val = substr($val, 1, -1); }
        $val = str_replace(["\r\n", "\r"], "\n", $val);
        $out[$key] = $val;
    }
    return $out;
}
function loadEnvFilesByEnv(): void {
    $baseDir = __DIR__; // assets/php (this file)

    // 1) If an explicit APP_ENV_FILE is provided (compose mounts secrets here), prefer it.
    $appEnvFile = getenv('APP_ENV_FILE') ?: ($_ENV['APP_ENV_FILE'] ?? null);
    if ($appEnvFile && is_readable($appEnvFile)) {
        $env = parse_dotenv_file($appEnvFile);
        foreach ($env as $k => $v) {
            if (getenv($k) !== false && getenv($k) !== '') continue;
            $_ENV[$k] = $v;
        }
        return;
    }

    // 2) Candidate locations (in-order): container webroot, repo project root, assets/php
    $candidates = [
        '/var/www/html',                // container webroot (production .app.env here)
        dirname(__DIR__, 2),           // repo/project root (works when running on host)
        __DIR__                        // assets/php (existing behavior)
    ];

    // Find base env in the first candidate that contains .app.env or .env
    $env = [];
    $foundBase = null;
    foreach ($candidates as $dir) {
        // Prefer .app.env in webroot/production
        $f1 = $dir . '/.app.env';
        $f2 = $dir . '/app.env';
        $f3 = $dir . '/.env';
        if (is_readable($f1)) { $foundBase = $f1; break; }
        if (is_readable($f2)) { $foundBase = $f2; break; }
        if (is_readable($f3)) { $foundBase = $f3; break; }
    }
    if ($foundBase) { $env = parse_dotenv_file($foundBase); }

    // Determine APP_ENV (process env wins, then discovered .env, then default)
    $appEnv = getenv('APP_ENV');
    if ($appEnv === false || $appEnv === '') { $appEnv = $env['APP_ENV'] ?? 'dev'; }

    // Find override file (.env.prod or .env.local) in the same candidate order
    $override = [];
    $overrideNames = ($appEnv === 'prod') ? ['.env.prod'] : ['.env.local', '.env.dev'];
    $foundOverride = null;
    foreach ($candidates as $dir) {
        foreach ($overrideNames as $name) {
            $path = $dir . '/' . $name;
            if (is_readable($path)) { $foundOverride = $path; break 2; }
        }
    }
    if ($foundOverride) { $override = parse_dotenv_file($foundOverride); }

    // Merge base + override (override wins)
    $merged = $env;
    foreach ($override as $k => $v) { $merged[$k] = $v; }

    // Populate into $_ENV only when not already present in process env
    foreach ($merged as $k => $v) {
        $proc = getenv($k);
        if ($proc !== false && $proc !== '') continue;
        $_ENV[$k] = $v;
    }
}
function envv(string $key, $default=null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    return $default;
}
loadEnvFilesByEnv();

/* --- Ausgabe --- */
echo "APP_ENV="    . envv('APP_ENV','-')    . PHP_EOL;
echo "SMTP_HOST="  . envv('SMTP_HOST','-')  . PHP_EOL;
echo "SMTP_PORT="  . envv('SMTP_PORT','-')  . PHP_EOL;
echo "SMTP_SECURE=". envv('SMTP_SECURE','<leer>') . PHP_EOL;
