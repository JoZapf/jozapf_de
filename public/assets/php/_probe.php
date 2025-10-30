<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

function parse_dotenv_file(string $file): array {
    if (!is_readable($file)) return [];
    $out = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || strpos($line,'=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if ((strlen($v) >= 2) && (($v[0] === '"' && substr($v,-1)==='"') || ($v[0] === "'" && substr($v,-1)==="'"))) {
            $v = substr($v,1,-1);
        }
        $out[$k] = $v;
    }
    return $out;
}
function load_env(): void {
    $root = dirname(__DIR__, 2);
    $base = parse_dotenv_file($root.'/.env');
    $prod = parse_dotenv_file($root.'/.env.prod');
    $local= parse_dotenv_file($root.'/.env.local');
    $merged = $base;
    foreach ($prod as $k=>$v) { $merged[$k] = $v; }
    foreach ($local as $k=>$v) { $merged[$k] = $v; }
    foreach ($merged as $k=>$v) {
        if (getenv($k) === false || getenv($k) === '') $_ENV[$k] = $v;
    }
}
function envv(string $k, string $default = '<not set>'): string {
    $v = getenv($k);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$k]) && $_ENV[$k] !== '') return (string)$_ENV[$k];
    return $default;
}

echo 'PHP: ' . PHP_VERSION . "\n";
echo 'DIR: ' . __DIR__ . "\n";

$autoload = __DIR__ . '/../../vendor/autoload.php';
echo 'Autoload path: ' . $autoload . "\n";
echo 'Autoload exists: ' . (file_exists($autoload) ? 'YES' : 'NO') . "\n";
echo 'Autoload readable: ' . (is_readable($autoload) ? 'YES' : 'NO') . "\n";

$probe = sys_get_temp_dir() . '/contact-write-probe.txt';
$ok = @file_put_contents($probe, date('c')) !== false;
echo 'sys_get_temp_dir: ' . sys_get_temp_dir() . "\n";
echo 'tmp writable: ' . ($ok ? 'YES (' . $probe . ')' : 'NO') . "\n";

load_env();
$keys = ['APP_ENV','SMTP_HOST','SMTP_PORT','SMTP_SECURE','SMTP_USER','SMTP_PASS','NOREPLY_EMAIL','RECIPIENT_EMAIL'];
foreach ($keys as $k) {
    echo $k . '=' . envv($k) . "\n";
}
