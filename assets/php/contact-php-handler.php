<?php
/**
 * Contact Form Handler (PHPMailer)
 * --------------------------------
 * - Stabile JSON-Antworten (kein 500 für das Frontend)
 * - Eigener .env-Loader (ohne externe Abhängigkeiten)
 * - SMTP/Hetzner korrekt (TLS/SSL, Auth, Envelope-From)
 * - Versand an Admin **und optional Bestätigung an den Benutzer**
 * - Diagnosemodus via __diag=1
 * - Logging + EML-Mitschnitt in assets/php/logs/
 */

declare(strict_types=1);

// ---------------------------
// 0) Immer JSON ausliefern
// ---------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    echo '';
    exit;
}

// ---------------------------
// 1) Hilfsfunktionen
// ---------------------------
function json_ok(array $extra = []): void {
    http_response_code(200);
    echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_error(string $msg, int $status = 400, array $extra = []): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Mini-.env-Lader (KEY=VALUE, # Kommentare)
 * - Sucht .env.local, sonst .env.prod
 */
function load_env(string $rootDir): array {
    $paths = [
        $rootDir . '/.env.local',
        $rootDir . '/.env.prod',
    ];
    $env = [];
    foreach ($paths as $p) {
        if (!is_file($p)) { continue; }
        $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            $v = preg_replace('/^([\'"])(.*)\1$/', '$2', $v);
            $env[$k] = $v;
            $_ENV[$k] = $v;
            if (function_exists('putenv')) {
                @putenv("$k=$v");
            }
        }
        if (str_ends_with($p, '.env.local')) break;
    }
    return $env;
}
function envv(string $key, ?string $default = null): ?string {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') return $default;
    return (string)$val;
}
function sanitize_line(string $s): string {
    $s = trim($s);
    $s = str_replace(["\r", "\n"], ' ', $s);
    return filter_var($s, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW) ?? '';
}
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Strenge Code-Heuristik
 * Erkennt typische Code-/Markup-Fragmente und blockiert die Nachricht.
 * Achtung: Kann False Positives verursachen, bewusst restriktiv.
 */
function looks_like_code(string $t): bool {
    $t = trim($t);
    if ($t === '') return false;

    // 1) Offensichtliche Marker
    $patterns = [
        '/<\/?[a-z][\w:\.-]*[^>]*>/i', // HTML/XML Tags
        '/<\s*script\b/i',
        '/<\s*style\b/i',
        '/<\?php/i',
        '/`{3,}/',                        // Markdown-Fences
        '/\{\{|\}\}/',                // Templating
        '/\$\(.*\)/s',                 // jQuery
        '/\b(document|window|eval|Function)\b\s*\(/i',
        '/\bon\w+\s*=\s*["\']/i',   // onClick=...
        '/\bjavascript:\s*/i',
        '/\bdata:\s*text\/html/i',
        '/\b(function|class|=>)\b/i',
        '/;\s*$/m',                      // Zeilenenden mit ;
        '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\b\s/i'
    ];
    foreach ($patterns as $rx) {
        if (preg_match($rx, $t)) return true;
    }

    // 2) Heuristik: hohe Dichte an spitzen/geschweiften Klammern
    $angles = substr_count($t, '<') + substr_count($t, '>');
    $braces = substr_count($t, '{') + substr_count($t, '}');
    $backticks = substr_count($t, '`');
    $longLine = max(array_map('strlen', preg_split('/\r?\n/', $t)));
    if ($angles >= 2 || $braces >= 4 || $backticks >= 3 || $longLine > 300) return true;

    return false;
}
function safe_log(string $file, string $msg): void {
    @file_put_contents($file, '['.date('c').'] '.$msg.PHP_EOL, FILE_APPEND);
}

// ----------------------------------------
// 2) Pfade & Autoload
// ----------------------------------------
$handlerDir   = __DIR__;                  // assets/php
$projectRoot  = dirname($handlerDir, 2);  // Projekt-Root
$vendorAutoload = $projectRoot . '/vendor/autoload.php';
$logsDir      = $handlerDir . '/logs';
if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);
$debugLog     = $logsDir . '/debug.log';
$phpErrLog    = $logsDir . '/php-errors.log';

// PHP-Warnungen in eigenes Log
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($phpErrLog) {
    safe_log($phpErrLog, "PHP[$errno] $errstr in $errfile:$errline");
    return false;
});

if (!is_file($vendorAutoload)) {
    json_error("vendor/autoload.php fehlt. Bitte 'composer install' ausführen.", 500, [
        'autoload' => $vendorAutoload
    ]);
}
require_once $vendorAutoload;

// ----------------------------------------
// 3) ENV laden & Konfiguration
// ----------------------------------------
$env = load_env($projectRoot);
$appEnv = envv('APP_ENV', 'prod');

// Admin-Ziel & Absender
$recipientEmail = envv('RECIPIENT_EMAIL', 'mail@jozapf.de');
$noreplyEmail   = envv('NOREPLY_EMAIL',   'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$subjectPrefix  = envv('SUBJECT_PREFIX',  'Kontakt: ');

// SMTP
$smtpHost   = envv('SMTP_HOST', '');
$smtpPort   = (int) (envv('SMTP_PORT', '0') ?? 0);
$smtpSecure = strtolower(envv('SMTP_SECURE', '') ?? ''); // '', tls, ssl
$smtpUser   = envv('SMTP_USER', '');
$smtpPass   = envv('SMTP_PASS', '');

// Dev-Fake
$devFakeSend = filter_var(envv('DEV_FAKE_SEND', 'false'), FILTER_VALIDATE_BOOL);

// NEU: User-Bestätigung steuerbar
$userConfirmEnable   = filter_var(envv('USER_CONFIRM_ENABLE', 'true'), FILTER_VALIDATE_BOOL);
$userConfirmSubject  = envv('USER_CONFIRM_SUBJECT', 'Your message has been received.');
$userConfirmGreeting = envv('USER_CONFIRM_GREETING', 'Thank you for your message! I will get back to you shortly.');

// ----------------------------------------
// 4) Eingaben lesen & validieren
// ----------------------------------------
$firstName = sanitize_line($_POST['firstName'] ?? '');
$lastName  = sanitize_line($_POST['lastName'] ?? '');
$email     = sanitize_line($_POST['email'] ?? '');
$phone     = sanitize_line($_POST['phone'] ?? '');
$subjectIn = sanitize_line($_POST['subject'] ?? '');
$message   = trim((string)($_POST['message'] ?? ''));

$captchaAnswer = $_POST['captchaAnswer'] ?? $_POST['captcha_answer'] ?? null;
$captchaAnswer = is_string($captchaAnswer) ? trim($captchaAnswer) : $captchaAnswer;

$privacy = isset($_POST['privacy']) ? 'on' : 'off';

if ($firstName === '' && $lastName === '') {
    json_error('Name is a required field.', 422, ['fields' => ['name' => false]]);
}
if ($message === '') {
    json_error('Message is a required field.', 422, ['fields' => ['message' => false]]);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('The email address is invalid.', 422, ['fields' => ['email' => false]]);
}
if ($captchaAnswer !== null && $captchaAnswer !== '') {
    if (!preg_match('/^\d{1,3}$/', (string)$captchaAnswer)) {
        json_error('Security question invalid.', 422, ['fields' => ['captcha' => false]]);
    }
}

// ----------------------------------------
// Zusätzliche Regex-Validierungen (Whitelist je Feld)
$reLine   = '/^[\p{L}\p{N}\s\.,!?:;@()\+\-\'"&\/_]{1,120}$/u';
$rePhone  = '/^[\p{N}\s\+\-()\/]{0,40}$/u';

if ($firstName !== '' && !preg_match($reLine, $firstName)) {
    json_error('First name contains invalid characters.', 422, ['fields' => ['firstName' => false]]);
}
if ($lastName !== '' && !preg_match($reLine, $lastName)) {
    json_error('Last name contains invalid characters.', 422, ['fields' => ['lastName' => false]]);
}
if ($subjectIn !== '' && !preg_match($reLine, $subjectIn)) {
    json_error('Subject contains invalid characters.', 422, ['fields' => ['subject' => false]]);
}
if ($phone !== '' && !preg_match($rePhone, $phone)) {
    json_error('Phone number contains invalid characters.', 422, ['fields' => ['phone' => false]]);
}

// Strenge Code-Heuristik für Nachrichteninhalt
if (looks_like_code($message)) {
    json_error('The message contains code/markup fragments and has been rejected for security reasons.', 422, ['fields' => ['message' => false]]);
}

// 5) Betreff & Admin-Body
// ----------------------------------------
$subjectSafe = trim($subjectPrefix . ($subjectIn !== '' ? $subjectIn : 'Kontaktformular'));
$fullName    = trim($firstName . ' ' . $lastName);

$body  = '';
$body .= '<h3>Neue Kontaktanfrage</h3>';
$body .= '<table cellpadding="6" cellspacing="0" border="0">';
$body .= '<tr><td><strong>Name:</strong></td><td>' . e($fullName ?: '—') . '</td></tr>';
$body .= '<tr><td><strong>E-Mail:</strong></td><td>' . e($email ?: '—') . '</td></tr>';
$body .= '<tr><td><strong>Telefon:</strong></td><td>' . e($phone ?: '—') . '</td></tr>';
$body .= '<tr><td><strong>Betreff:</strong></td><td>' . e($subjectIn ?: '—') . '</td></tr>';
$body .= '<tr><td><strong>Datenschutz:</strong></td><td>' . e($privacy === 'on' ? 'akzeptiert' : 'nicht bestätigt') . '</td></tr>';
if ($captchaAnswer !== null && $captchaAnswer !== '') {
    $body .= '<tr><td><strong>Security:</strong></td><td>Antwort ' . e((string)$captchaAnswer) . '</td></tr>';
}
$body .= '</table><hr>';
$body .= '<div style="white-space:pre-wrap; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;">' . nl2br(e($message)) . '</div>';

// ----------------------------------------
// 6) Diagnosemodus (kein Versand)
// ----------------------------------------
if (($_POST['__diag'] ?? '') === '1') {
    $diag = [
        'php'           => PHP_VERSION,
        'sapi'          => PHP_SAPI,
        'app_env'       => $appEnv,
        'autoload'      => $vendorAutoload,
        '.env.local'    => is_file($projectRoot.'/.env.local') ? 'OK' : 'MISSING',
        '.env.prod'     => is_file($projectRoot.'/.env.prod')  ? 'OK' : 'MISSING',
        'logsWritable'  => is_writable($logsDir) ? 'YES' : 'NO',
        'smtp' => [
            'host'   => $smtpHost,
            'port'   => $smtpPort,
            'secure' => $smtpSecure,
            'auth'   => $smtpUser !== '' ? 'true' : 'false',
            'user'   => $smtpUser !== '' ? 'SET' : 'EMPTY',
        ],
        'from' => [
            'noreply'  => $noreplyEmail,
            'recipient'=> $recipientEmail,
        ],
        'userConfirm' => [
            'enabled'  => $userConfirmEnable ? 'true' : 'false',
        ],
    ];
    json_ok($diag);
}

// ----------------------------------------
// 7) PHPMailer konfigurieren
// ----------------------------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(true);

    // SMTP-Setup (wenn Host gesetzt)
    if ($smtpHost !== '' && $smtpPort > 0) {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;

        if ($smtpSecure === 'tls' || ($smtpSecure === '' && $smtpPort === 587)) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpSecure === 'ssl' || ($smtpSecure === '' && $smtpPort === 465)) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = false;
        }

        $mail->SMTPAuth = ($smtpUser !== '');
        if ($mail->SMTPAuth) {
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass ?? '';
        }

        if ($appEnv !== 'prod') {
            $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = static function($str) use ($debugLog) {
                safe_log($debugLog, "[SMTP] $str");
            };
        }
    }

    // ==============================================
    // 8) Versand an Admin (From/Reply-To/Return-Path + EML)
    // ==============================================
    $fromHeader = $noreplyEmail ?: $smtpUser ?: 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $replyName  = 'jozapf.de';

    // Header-From = authentifizierte Mailbox (oder Fallback) -> DMARC freundlich
    $headerFromEmail = $smtpUser ?: $fromHeader;
    $replyToEmail    = $noreplyEmail ?: $headerFromEmail;

    $mail->setFrom($headerFromEmail, $replyName, false);
    $mail->addAddress($recipientEmail);

    // Wenn der Absender seine E-Mail eingetragen hat, als Reply-To ergänzen
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($email, $fullName !== '' ? $fullName : 'Kontakt');
    } else {
        $mail->addReplyTo($replyToEmail, $replyName);
    }

    if (!empty($smtpUser)) {
        $mail->Sender = $smtpUser; // Envelope-From
    }

    $mail->Subject = $subjectSafe;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body));

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $mail->addCustomHeader('X-Originating-IP', $_SERVER['REMOTE_ADDR']);
    }
    $mail->addCustomHeader('X-App-Env', (string)$appEnv);
    $mail->addCustomHeader('X-App-Handler', 'contact-php-handler.php');

    // EML-Mitschnitt
    $emlDir = $logsDir . '/sent-eml';
    if (!is_dir($emlDir)) { @mkdir($emlDir, 0775, true); }
    $emlFile = $emlDir . '/mail-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';

    try {
        if ($mail->preSend()) {
            $raw = $mail->getSentMIMEMessage();
            @file_put_contents($emlFile, $raw);
        }
    } catch (\Throwable $emlEx) {
        safe_log($debugLog, '[MAIL] EML write failed: ' . $emlEx->getMessage());
    }

    // Admin-Versand (oder Fake im Dev)
    if ($devFakeSend) {
        safe_log($debugLog, '[FAKE_SEND] Admin-Mail NICHT gesendet (DEV_FAKE_SEND=true).');
    } else {
        $ok = $mail->send();
        if (!$ok) {
            safe_log($debugLog, 'Admin send() returned false; ErrorInfo=' . $mail->ErrorInfo);
            json_error('Versand fehlgeschlagen: ' . $mail->ErrorInfo, 502);
        }
    }

    // ==============================================
    // 9) (NEU) Bestätigungs-Mail an den Benutzer
    //    - nur wenn:
    //      • USER_CONFIRM_ENABLE=true
    //      • gültige Absender-E-Mail im Formular vorhanden
    //      • kein Fake-Send aktiv
    //    - Absender-Politik für DMARC:
    //      • From = SMTP_USER (authentifizierte Mailbox)
    //      • Reply-To = NOREPLY_EMAIL (oder leer)
    // ==============================================
    $userConfirmSent = false;
    if ($userConfirmEnable && !$devFakeSend && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $confirm = new PHPMailer(true);
        try {
            $confirm->CharSet  = 'UTF-8';
            $confirm->Encoding = 'base64';
            $confirm->isHTML(true);

            // SMTP identisch konfigurieren
            if ($smtpHost !== '' && $smtpPort > 0) {
                $confirm->isSMTP();
                $confirm->Host = $smtpHost;
                $confirm->Port = $smtpPort;

                if ($smtpSecure === 'tls' || ($smtpSecure === '' && $smtpPort === 587)) {
                    $confirm->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($smtpSecure === 'ssl' || ($smtpSecure === '' && $smtpPort === 465)) {
                    $confirm->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $confirm->SMTPSecure = false;
                }

                $confirm->SMTPAuth = ($smtpUser !== '');
                if ($confirm->SMTPAuth) {
                    $confirm->Username = $smtpUser;
                    $confirm->Password = $smtpPass ?? '';
                }
            }

            // From/Reply-To
            $confirmFrom = $smtpUser ?: $noreplyEmail ?: 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $confirm->setFrom($confirmFrom, 'jozapf.de', false);
            $confirm->addAddress($email, $fullName !== '' ? $fullName : '');

            if ($noreplyEmail !== '') {
                $confirm->addReplyTo($noreplyEmail, 'No-Reply');
            }

            $confirm->Subject = $userConfirmSubject;

            // Schlichte, saubere Bestätigungsnachricht
            $confirmBody  = '<p>' . e($userConfirmGreeting) . '</p>';
            $confirmBody .= '<hr><p><strong>Copy of your details:</strong></p>';
            $confirmBody .= '<table cellpadding="6" cellspacing="0" border="0">';
            $confirmBody .= '<tr><td><strong>Name:</strong></td><td>' . e($fullName ?: '—') . '</td></tr>';
            $confirmBody .= '<tr><td><strong>E-Mail:</strong></td><td>' . e($email ?: '—') . '</td></tr>';
            if ($subjectIn !== '') {
                $confirmBody .= '<tr><td><strong>Subject:</strong></td><td>' . e($subjectIn) . '</td></tr>';
            }
            if ($phone !== '') {
                $confirmBody .= '<tr><td><strong>Phone:</strong></td><td>' . e($phone) . '</td></tr>';
            }
            $confirmBody .= '</table><hr>';
            $confirmBody .= '<div style="white-space:pre-wrap; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;">' . nl2br(e($message)) . '</div>';

            $confirm->Body    = $confirmBody;
            $confirm->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $confirmBody));

            // Envelope-From (Bounces) -> SMTP_USER
            if (!empty($smtpUser)) {
                $confirm->Sender = $smtpUser;
            }

            $userConfirmSent = $confirm->send();
            if (!$userConfirmSent) {
                safe_log($debugLog, 'User confirm send() false; ErrorInfo=' . $confirm->ErrorInfo);
            }
        } catch (\Throwable $ex) {
            safe_log($debugLog, 'User confirm exception: ' . $ex->getMessage());
        }
    }

    // Erfolg fürs Frontend
    json_ok([
        'sent' => !$devFakeSend,
        'userConfirm' => $userConfirmEnable ? ($userConfirmSent ? 'sent' : 'not_sent_or_failed') : 'disabled'
    ]);

} catch (Exception $ex) {
    safe_log($debugLog, 'PHPMailer Exception: ' . $ex->getMessage());
    json_error('E-Mail Fehler: ' . $ex->getMessage(), 500);
} catch (\Throwable $ex) {
    safe_log($debugLog, 'Unhandled Throwable: ' . $ex->getMessage());
    json_error('Unerwarteter Fehler im Handler.', 500);
}
