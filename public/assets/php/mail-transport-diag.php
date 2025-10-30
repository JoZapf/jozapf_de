<?php
declare(strict_types=1);

/*
 * assets/php/mail-transport-diag.php
 * Temporäre SMTP-Diagnose für Hetzner (per Browser).
 * Ausgabe: text/plain. Keine Passwörter im Klartext.
 */

header('Content-Type: text/plain; charset=utf-8');

/* ---------- Mini-.env Loader (wie im Handler, eigenständig) ---------- */
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
function loadEnvFilesByEnv(string $baseDir): void {
    $env = parse_dotenv_file($baseDir . '/.env');
    $appEnv = getenv('APP_ENV');
    if ($appEnv === false || $appEnv === '') { $appEnv = $env['APP_ENV'] ?? 'dev'; }
    $overrideFile = ($appEnv === 'prod') ? $baseDir.'/.env.prod' : $baseDir.'/.env.local';
    $override = parse_dotenv_file($overrideFile);
    $merged = $env;
    foreach ($override as $k => $v) { $merged[$k] = $v; }
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

/* ---------- Start ---------- */
$baseDir = __DIR__; // assets/php
loadEnvFilesByEnv($baseDir);

$APP_ENV        = envv('APP_ENV', 'prod');
$SMTP_HOST      = envv('SMTP_HOST', '');
$SMTP_PORT      = (int) envv('SMTP_PORT', '0');
$SMTP_SECURE    = strtolower((string) envv('SMTP_SECURE', ''));
$SMTP_USER      = envv('SMTP_USER', '');
$SMTP_PASS      = envv('SMTP_PASS', '');
$NOREPLY_EMAIL  = envv('NOREPLY_EMAIL', '');
$RECIPIENT      = envv('RECIPIENT_EMAIL', '');
$SUBJECT_PREFIX = envv('SUBJECT_PREFIX', 'Kontakt: ');
$DEV_FAKE_SEND  = envv('DEV_FAKE_SEND', 'false');

echo "=== SMTP Diagnose ===\n";
echo "APP_ENV........: {$APP_ENV}\n";
echo "HOST...........: {$SMTP_HOST}\n";
echo "PORT...........: " . ($SMTP_PORT ?: '(leer)') . "\n";
echo "SECURE.........: " . ($SMTP_SECURE ?: '(leer)') . "  (tls/ssl/leer)\n";
echo "SMTP_AUTH......: " . (($SMTP_USER !== '' || $SMTP_PASS !== '') ? 'true' : 'false') . "\n";
echo "SMTP_USER......: " . ($SMTP_USER !== '' ? $SMTP_USER : '(leer)') . "\n";
echo "SMTP_PASS......: " . ($SMTP_PASS !== '' ? str_repeat('•', 8) : '(leer)') . "\n";
echo "FROM (NOREPLY).: " . ($NOREPLY_EMAIL ?: '(leer)') . "\n";
echo "TO (RECIPIENT).: " . ($RECIPIENT ?: '(leer)') . "\n";
echo "DEV_FAKE_SEND..: {$DEV_FAKE_SEND}\n\n";

$issues = [];

/* Basiskontrollen */
if ($SMTP_HOST === '') $issues[] = 'SMTP_HOST ist leer.';
if ($SMTP_PORT === 0)  $issues[] = 'SMTP_PORT ist leer/0 (üblich: 587 für TLS, 465 für SSL).';
if ($NOREPLY_EMAIL === '') $issues[] = 'NOREPLY_EMAIL ist leer.';
if ($RECIPIENT === '')    $issues[] = 'RECIPIENT_EMAIL ist leer.';
if (!filter_var($NOREPLY_EMAIL, FILTER_VALIDATE_EMAIL)) $issues[] = 'NOREPLY_EMAIL ist keine gültige E-Mail.';
if (!filter_var($RECIPIENT, FILTER_VALIDATE_EMAIL))     $issues[] = 'RECIPIENT_EMAIL ist keine gültige E-Mail.';
if ($DEV_FAKE_SEND === 'true') $issues[] = 'DEV_FAKE_SEND=true (würde Versand unterbinden).';

/* DNS/Socket */
echo "— Netzwerk —\n";
$resolved = @gethostbyname($SMTP_HOST);
echo "DNS-Auflösung: {$SMTP_HOST} -> {$resolved}\n";
$fp = @fsockopen($SMTP_HOST, $SMTP_PORT, $errno, $errstr, 5.0);
if ($fp) {
    echo "TCP Connect...: OK (Port {$SMTP_PORT})\n";
    fclose($fp);
} else {
    echo "TCP Connect...: FAIL ({$errno}) {$errstr}\n";
    $issues[] = "TCP-Verbindung zu {$SMTP_HOST}:{$SMTP_PORT} fehlgeschlagen.";
}

/* PHPMailer */
require __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
$debugFile = $logDir . '/mail-debug.log';

// Debug in Datei sammeln
$debugLines = [];
$debugOutput = static function ($str) use (&$debugLines) { $debugLines[] = '[SMTP] ' . $str; };

$mail = new PHPMailer(true);
$mail->CharSet = 'UTF-8';
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

if ($SMTP_USER !== '' || $SMTP_PASS !== '') {
    $mail->SMTPAuth = true;
    $mail->Username = $SMTP_USER;
    $mail->Password = $SMTP_PASS;
}

// Debug-Level hoch für Diagnose
$mail->SMTPDebug = SMTP::DEBUG_CONNECTION; // moderat ausführlich
$mail->Debugoutput = $debugOutput;

// Absender/Empfänger (strict)
$mail->setFrom($NOREPLY_EMAIL, 'Website');
$mail->addAddress($RECIPIENT);
$mail->Subject = $SUBJECT_PREFIX . 'SMTP-Diagnose';
$mail->Body = "Dies ist eine SMTP-Diagnose-Mail von {$NOREPLY_EMAIL} an {$RECIPIENT}.\nZeit: " . date('c');

/* Erst Verbindungsaufbau testen */
echo "\n— PHPMailer —\n";
try {
    if (!$mail->smtpConnect()) {
        echo "smtpConnect(): FAIL\n";
        $issues[] = 'PHPMailer smtpConnect() fehlgeschlagen.';
    } else {
        echo "smtpConnect(): OK\n";
        $mail->smtpClose();
    }
} catch (Throwable $e) {
    echo "smtpConnect(): EXCEPTION: " . $e->getMessage() . "\n";
    $issues[] = 'Exception beim smtpConnect(): ' . $e->getMessage();
}

/* Test-Send (optional, nur wenn keine Issues bis hier) */
if (empty($issues)) {
    try {
        if ($mail->send()) {
            echo "send(): OK (Testmail gesendet)\n";
        } else {
            echo "send(): FAIL (unbekannt)\n";
            $issues[] = 'PHPMailer send() meldet Fehler.';
        }
    } catch (Throwable $e) {
        echo "send(): EXCEPTION: " . $e->getMessage() . "\n";
        $issues[] = 'Exception beim send(): ' . $e->getMessage();
    }
} else {
    echo "send(): übersprungen, da vorherige Fehler.\n";
}

/* Debug schreiben */
if (!empty($debugLines)) {
    @file_put_contents($debugFile, implode("\n", $debugLines) . "\n", FILE_APPEND);
    echo "\nDebug-Log geschrieben: assets/php/logs/mail-debug.log (auf Server prüfen)\n";
}

/* Ergebnis */
echo "\n=== Ergebnis ===\n";
if (empty($issues)) {
    echo "OK: Verbindung/Versand sieht gut aus.\n";
} else {
    echo "FEHLER:\n - " . implode("\n - ", $issues) . "\n";
    echo "\nTipps:\n";
    echo " • Stelle sicher, dass SMTP_HOST/PORT/SECURE/USER/PASS korrekt sind (Hetzner Mailserver-Daten!).\n";
    echo " • FROM (NOREPLY_EMAIL) sollte zur authentifizierten Mailbox/Domain passen (gleiche Domain).\n";
    echo " • SPF/DMARC/DKIM der Domain prüfen, falls Mails zugestellt aber abgewiesen werden.\n";
    echo " • Bei Port 587 -> SECURE=tls, bei 465 -> SECURE=ssl.\n";
}
