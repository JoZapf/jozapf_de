<?php
declare(strict_types=1);

// ---- Ausgabe als Text
header('Content-Type: text/plain; charset=utf-8');

// ---- Mini .env Loader (wie im Handler)
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
        if (!str_contains($line, '=')) continue;
        [$k,$v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (str_ends_with($k, '[]')) continue;
        if ((strlen($v)>=2) && (($v[0]==='"' && substr($v,-1)==='"') || ($v[0]==="'" && substr($v,-1)==="'"))) {
            $v = substr($v,1,-1);
        }
        $out[$k] = $v;
    }
    return $out;
}
function loadEnv(): void {
    $base = __DIR__;
    $env = parse_dotenv_file($base.'/.env');
    $appEnv = getenv('APP_ENV') ?: ($env['APP_ENV'] ?? 'prod');
    $override = parse_dotenv_file($base . ($appEnv==='prod' ? '/.env.prod' : '/.env.local'));
    $merged = $env + $override;
    foreach ($override as $k=>$v) { $merged[$k]=$v; }
    foreach ($merged as $k=>$v) {
        if (getenv($k)!==false && getenv($k)!=='') continue;
        $_ENV[$k]=$v;
    }
}
function envv(string $k, ?string $d=null): ?string {
    $v = getenv($k);
    if ($v!==false && $v!=='') return $v;
    return $_ENV[$k] ?? $d;
}
loadEnv();

// ---- Autoload
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_readable($autoload)) {
    echo "autoload MISSING at $autoload\n";
    exit(1);
}
require $autoload;

// ---- Ziel-Log
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0775, true);
$logFile = $logDir . '/smtp_debug.log';
@file_put_contents($logFile, "=== ".date('c')." ===\n", FILE_APPEND);

// ---- ENV anzeigen
$APP_ENV = envv('APP_ENV','<not set>');
$DEV_FAKE_SEND = strtolower((string)envv('DEV_FAKE_SEND','false'))==='true';
$SMTP_HOST = envv('SMTP_HOST','<not set>');
$SMTP_PORT = (int)(envv('SMTP_PORT','0') ?? 0);
$SMTP_SECURE = strtolower((string)envv('SMTP_SECURE',''));
$SMTP_USER = envv('SMTP_USER','');
$SMTP_PASS = envv('SMTP_PASS','');
$NOREPLY = envv('NOREPLY_EMAIL','');
$RCPT = envv('RECIPIENT_EMAIL','');

echo "APP_ENV=$APP_ENV\n";
echo "DEV_FAKE_SEND=".($DEV_FAKE_SEND?'true':'false')."\n";
echo "SMTP_HOST=$SMTP_HOST\n";
echo "SMTP_PORT=$SMTP_PORT\n";
echo "SMTP_SECURE=$SMTP_SECURE\n";
echo "NOREPLY_EMAIL=$NOREPLY\n";
echo "RECIPIENT_EMAIL=$RCPT\n\n";

if ($DEV_FAKE_SEND) {
    echo "FAKE-SEND aktiv → OK (kein Versand versucht)\n";
    exit(0);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

// Debug ins Datei-Log schreiben
$mail->SMTPDebug = 2; // nur hier im Probe-Skript
$mail->Debugoutput = static function ($str) use ($logFile) {
    @file_put_contents($logFile, "[SMTP] $str\n", FILE_APPEND);
};

try {
    if (!$SMTP_HOST || !$NOREPLY || !$RCPT) {
        throw new Exception("Missing required ENV (need SMTP_HOST, NOREPLY_EMAIL, RECIPIENT_EMAIL)");
    }

    $mail->isSMTP();
    $mail->Host = $SMTP_HOST;
    if ($SMTP_PORT > 0) $mail->Port = $SMTP_PORT;

    // Secure
    if ($SMTP_SECURE === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($SMTP_SECURE === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = false;
    }

    // Auth
    if ($SMTP_USER !== '') {
        $mail->SMTPAuth = true;
        $mail->Username = $SMTP_USER;
        $mail->Password = (string)$SMTP_PASS;
    }

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($NOREPLY, 'Website Probe');
    $mail->addAddress($RCPT);
    $mail->Subject = 'SMTP Probe (PHPMailer)';
    $mail->Body = "Hallo!\n\nDies ist eine Testmail vom _smtp_probe.php\nZeit: ".date('c')."\n";

    $ok = $mail->send();
    echo $ok ? "SEND OK\n" : "SEND FAIL (unknown)\n";
    echo "\nSiehe Debug-Log: assets/php/logs/smtp_debug.log\n";
} catch (\Throwable $e) {
    echo "EXCEPTION: ".$e->getMessage()."\n";
    echo "Siehe Debug-Log: assets/php/logs/smtp_debug.log\n";
    exit(1);
}
