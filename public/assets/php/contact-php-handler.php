<?php
/**
 * Contact Form Handler - V4.0 WITH EXTENDED LOGGING
 * ===================================================
 * 
 * Features:
 * ✅ PHPMailer (SMTP-Auth via .env.prod)
 * ✅ ContactFormValidator-v2 with Extended Logging
 * ✅ Blocklist/Whitelist support (GDPR-compliant)
 * ✅ Auto-anonymization after 14 days
 * ✅ Server-side Captcha validation
 * ✅ Comprehensive sanitization
 * 
 * @author JoZapf
 * @version 4.3.0
 * @date 2026-03-25
 * 
 * Changelog v4.3.0 (2026-03-25):
 * - NF-02 FIX: Bestätigungsmail Rate-Limit (max 1 pro E-Mail pro 24h)
 *
 * Changelog v4.2.0 (2026-03-24):
 * - KF-03 FIX: CSRF-Token (Session-basiert, One-Time-Use)
 * - HF-01 FIX: Captcha-Lösung in Session statt Hidden-Field
 * - NF-01 FIX: Session-Härtung (httponly, secure, samesite)
 * - GET ?init=1 Endpoint für CSRF-Token + Captcha-Aufgabe
 * - Methoden-Guard: Nur POST + GET?init erlaubt
 * - Migrations-Modus für CSRF + Captcha (Frontend-Kompatibilität)
 *
 * Changelog v4.1.0 (2026-03-24):
 * - KF-02 FIX: CORS Wildcard * durch Domain-Lock ersetzt
 */

declare(strict_types=1);

// ============================================================================
// 0) SESSION START
// ============================================================================
// NF-01 FIX: Session-Härtung
// cookie_httponly: JavaScript kann Session-Cookie nicht lesen (XSS-Schutz)
// cookie_secure:   Cookie nur über HTTPS gesendet (kein Klartext-Leak)
// cookie_samesite: Cookie wird nicht bei Cross-Site-Requests gesendet (CSRF-Schutz)
// use_strict_mode: Lehnt uninitialisierte Session-IDs ab (Session-Fixation-Schutz)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// ============================================================================
// 1) HEADERS & ERROR HANDLING
// ============================================================================
header('Content-Type: application/json; charset=utf-8');
// KF-02 FIX: CORS auf eigene Domain einschränken (vorher: Wildcard *)
// Wildcard erlaubte Cross-Origin-Requests von jeder Website → Spam-Relay-Risiko.
// Hardcoded, da env() hier noch nicht geladen ist (load_env kommt in Abschnitt 3).
// Für Staging: ALLOWED_ORIGIN in .env.prod setzen → Override nach load_env() unten.
$allowedOrigin = 'https://jozapf.de';
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Error Handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP[$errno] $errstr in $errfile:$errline");
    return false;
});

// ============================================================================
// 2) HELPER FUNCTIONS
// ============================================================================

function json_success(string $message, array $data = []): void {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): void {
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => $message
    ] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * EINHEITLICHE SANITIZATION
 */
function sanitize_text(string $input, int $maxLength = 5000): string {
    $input = trim($input);
    $input = str_replace(["\r", "\n", "\0"], ' ', $input);
    $input = filter_var($input, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK) ?? '';
    
    if (strlen($input) > $maxLength) {
        $input = mb_substr($input, 0, $maxLength, 'UTF-8');
    }
    
    return $input;
}

/**
 * HTML-Escaping
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * ENV-Loader
 */
function load_env(string $envFile): array {
    $env = [];
    
    if (!file_exists($envFile)) {
        return $env;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        
        if (!str_contains($line, '=')) {
            continue;
        }
        
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = preg_replace('/^([\'"])(.*)\1$/', '$2', $value);
        
        $env[$key] = $value;
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
    
    return $env;
}

function env(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ============================================================================
// 2b) FORM INIT ENDPOINT — KF-03 + HF-01 (CSRF-Token + Captcha)
// ============================================================================
// GET ?init=1 liefert einen CSRF-Token und eine Captcha-Aufgabe.
// Beide werden in der PHP-Session gespeichert (nicht im HTML!).
// Das Frontend ruft diesen Endpoint beim Laden des Formulars auf.
//
// Warum hier (vor Config/PHPMailer/Validator)?
// → Ein leichtgewichtiger GET-Request braucht keine DB, kein SMTP, keinen
//   Validator. Session + CORS-Headers reichen. Spart Serverressourcen.

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['init'])) {
    // CSRF-Token generieren (KF-03)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Captcha generieren (HF-01: Lösung NUR in Session, nicht im HTML)
    $captchaA = random_int(1, 20);
    $captchaB = random_int(1, 20);
    $_SESSION['captcha_solution'] = $captchaA + $captchaB;
    
    json_success('Form initialized', [
        'csrf_token' => $_SESSION['csrf_token'],
        'captcha'    => [
            'question' => "{$captchaA} + {$captchaB} = ?",
            'a'        => $captchaA,
            'b'        => $captchaB
        ]
    ]);
    // json_success() ruft exit() auf — hier endet der GET-Request.
}

// Ab hier: Nur noch POST-Requests (Formular-Submit)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST to submit the form, GET ?init=1 to initialize.', 405);
}

// ============================================================================
// 3) CONFIGURATION
// ============================================================================

$handlerDir = __DIR__;
$projectRoot = dirname($handlerDir, 2);
$logsDir = $handlerDir . '/logs';
$dataDir = $handlerDir . '/data';

// Ensure directories exist
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Load .env.prod
$envFile = $handlerDir . '/.env.prod';
load_env($envFile);

// KF-02: CORS Override aus .env.prod (für Staging/andere Domains)
$envOrigin = env('ALLOWED_ORIGIN');
if ($envOrigin) {
    header('Access-Control-Allow-Origin: ' . $envOrigin);
}

// PHPMailer Autoload
$vendorAutoload = $projectRoot . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    json_error('System configuration error. Please contact administrator.', 500);
}
require_once $vendorAutoload;

// ============================================================================
// 4) VALIDATION & ABUSE PREVENTION (V2 - Extended Logging + Blocklist)
// ============================================================================

require_once __DIR__ . '/ContactFormValidator-v2.php';

$validator = new ContactFormValidator([
    'extendedLogDir' => $logsDir,
    'blocklistDir' => $dataDir,
    'honeypotField' => 'website',
    'timestampField' => 'form_timestamp',
    'minSubmitTime' => 3,
    'maxSubmitTime' => 3600,
    'rateLimitWindow' => 3600,
    'rateLimitMax' => 5,
    'requiredFields' => ['email', 'firstName', 'lastName', 'message'],
    'spamKeywords' => [
        'viagra', 'cialis', 'casino', 'lottery', 'prize',
        'click here', 'buy now', 'limited time', 'act now',
        'congratulations', 'winner', 'claim', 'free money'
    ],
    'maxLinks' => 3,
    'maxEmailLength' => 254,
    'maxMessageLength' => 5000,
    'blockThreshold' => 30
], true, true); // Extended Logging + Blocklist enabled

// Validate submission
$validation = $validator->validate($_POST);

// CRITICAL: Block if spam score too high or blocklisted
if ($validation['blocked']) {
    $errorMessage = 'Your submission was blocked.';
    
    // Specific message for blocklisted IPs
    if (in_array('ip_blocklisted', $validation['reasons'] ?? [])) {
        $errorMessage = 'Your IP address has been blocked due to previous abuse.';
    }
    
    json_error(
        $errorMessage,
        429,
        [
            'spamScore' => $validation['spamScore'],
            'reasons' => $validation['reasons'] ?? []
        ]
    );
}

// ============================================================================
// 4b) CSRF VALIDATION (KF-03)
// ============================================================================
// Prüft ob der CSRF-Token aus dem POST mit dem Session-Token übereinstimmt.
// hash_equals() verhindert Timing-Angriffe beim Vergleich.
// Nach erfolgreicher Prüfung wird der Token gelöscht (One-Time-Use).
//
// MIGRATIONS-MODUS: Während der Übergangsphase (Frontend noch ohne CSRF)
// wird der Check nur ausgeführt, wenn ein Token in der Session existiert.
// Nach Frontend-Deployment diese Bedingung entfernen und CSRF erzwingen!

$csrfToken = $_POST['csrf_token'] ?? '';
$sessionCsrf = $_SESSION['csrf_token'] ?? '';

if (!empty($sessionCsrf)) {
    // Session hat einen CSRF-Token → Validierung erzwingen
    if (empty($csrfToken) || !hash_equals($sessionCsrf, $csrfToken)) {
        json_error('Invalid security token. Please reload the page.', 403);
    }
    // One-Time-Use: Token nach erfolgreicher Validierung löschen
    unset($_SESSION['csrf_token']);
}
// TODO (nach Frontend-Deploy): Migrations-Modus entfernen, immer erzwingen:
// if (empty($csrfToken) || empty($sessionCsrf) || !hash_equals($sessionCsrf, $csrfToken)) {
//     json_error('Invalid security token. Please reload the page.', 403);
// }
// unset($_SESSION['csrf_token']);

// ============================================================================
// 5) INPUT SANITIZATION & FIELD VALIDATION
// ============================================================================

// Sanitize ALL fields
$firstName = sanitize_text($_POST['firstName'] ?? '', 50);
$lastName  = sanitize_text($_POST['lastName'] ?? '', 50);
$email     = sanitize_text($_POST['email'] ?? '', 100);
$phone     = sanitize_text($_POST['phone'] ?? '', 40);
$subject   = sanitize_text($_POST['subject'] ?? '', 120);
$message   = sanitize_text($_POST['message'] ?? '', 5000);

// Captcha
$captchaAnswer = trim($_POST['captchaAnswer'] ?? '');
// HF-01 FIX: Lösung aus Session lesen, NICHT aus POST-Hidden-Field.
// Vorher: $captchaSolution = trim($_POST['captcha_answer'] ?? '');
// Das Hidden-Field war im HTML sichtbar → Bot konnte Lösung auslesen.
$captchaSolution = $_SESSION['captcha_solution'] ?? null;
// MIGRATIONS-FALLBACK: Altes Frontend sendet Lösung noch als Hidden-Field.
// TODO (nach Frontend-Deploy): Diese 3 Zeilen entfernen!
if ($captchaSolution === null && !empty($_POST['captcha_answer'])) {
    $captchaSolution = (int) trim($_POST['captcha_answer']);
}

// Privacy checkbox
$privacyAccepted = isset($_POST['privacy']) && $_POST['privacy'] === 'on';

// --- REQUIRED FIELDS ---

if (empty($firstName)) {
    json_error('First name is required.', 422, ['fields' => ['firstName' => false]]);
}

if (empty($lastName)) {
    json_error('Last name is required.', 422, ['fields' => ['lastName' => false]]);
}

if (empty($email)) {
    json_error('Email is required.', 422, ['fields' => ['email' => false]]);
}

if (empty($message)) {
    json_error('Message is required.', 422, ['fields' => ['message' => false]]);
}

// --- PRIVACY CHECKBOX (DSGVO) ---

if (!$privacyAccepted) {
    json_error('You must accept the privacy policy.', 422, ['fields' => ['privacy' => false]]);
}

// --- CAPTCHA SERVER-SIDE VALIDATION (HF-01: Session-basiert) ---

if (empty($captchaAnswer)) {
    json_error('Please solve the security question.', 422, ['fields' => ['captchaAnswer' => false]]);
}

if (!is_numeric($captchaAnswer)) {
    json_error('Security answer must be a number.', 422, ['fields' => ['captchaAnswer' => false]]);
}

if ($captchaSolution === null) {
    json_error('Security question expired. Please reload the page.', 422, ['fields' => ['captchaAnswer' => false]]);
}

if ((int)$captchaAnswer !== (int)$captchaSolution) {
    json_error('Security answer is incorrect.', 422, ['fields' => ['captchaAnswer' => false]]);
}

// HF-01: One-Time-Use — Captcha-Lösung nach Prüfung löschen
unset($_SESSION['captcha_solution']);

// --- FORMAT VALIDATION ---

// Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email address.', 422, ['fields' => ['email' => false]]);
}

// First/Last Name - Whitelist
$namePattern = '/^[\p{L}\p{N}\s\.,\-\']{2,50}$/u';
if (!preg_match($namePattern, $firstName)) {
    json_error('First name contains invalid characters.', 422, ['fields' => ['firstName' => false]]);
}
if (!preg_match($namePattern, $lastName)) {
    json_error('Last name contains invalid characters.', 422, ['fields' => ['lastName' => false]]);
}

// Phone (optional)
if (!empty($phone)) {
    $phonePattern = '/^[\d\s\+\-()\/]{0,40}$/';
    if (!preg_match($phonePattern, $phone)) {
        json_error('Phone number contains invalid characters.', 422, ['fields' => ['phone' => false]]);
    }
}

// Subject (optional)
if (!empty($subject)) {
    $subjectPattern = '/^[\p{L}\p{N}\s\.,!?:;@()\+\-\'"&\/_]{1,120}$/u';
    if (!preg_match($subjectPattern, $subject)) {
        json_error('Subject contains invalid characters.', 422, ['fields' => ['subject' => false]]);
    }
}

// Message length
if (strlen($message) < 10) {
    json_error('Message must be at least 10 characters.', 422, ['fields' => ['message' => false]]);
}

// ============================================================================
// 6) PHPMAILER CONFIGURATION
// ============================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load SMTP config from .env.prod
$recipientEmail = env('RECIPIENT_EMAIL', 'mail@jozapf.de');
$smtpHost       = env('SMTP_HOST', '');
$smtpPort       = (int) env('SMTP_PORT', '587');
$smtpUser       = env('SMTP_USER', '');
$smtpPass       = env('SMTP_PASS', '');
$smtpSecure     = strtolower(env('SMTP_SECURE', 'tls'));

// Validation
if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
    error_log('[CONTACT FORM] SMTP configuration incomplete in .env.prod');
    json_error('Email system not configured. Please contact administrator.', 500);
}

// ============================================================================
// 7) SEND EMAIL TO ADMIN
// ============================================================================

$mail = new PHPMailer(true);

try {
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->Port       = $smtpPort;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    
    // Encryption
    if ($smtpSecure === 'tls' || $smtpPort === 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($smtpSecure === 'ssl' || $smtpPort === 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }
    
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    
    // From/To
    $mail->setFrom($smtpUser, 'www.jozapf.de Contact Form');
    $mail->addAddress($recipientEmail);
    
    // Reply-To = User's email
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fullName = trim($firstName . ' ' . $lastName);
        $mail->addReplyTo($email, $fullName);
    }
    
    // Envelope From
    $mail->Sender = $smtpUser;
    
    // Subject
    $emailSubject = !empty($subject) ? $subject : 'Contact Form Submission';
    $mail->Subject = 'Contact: ' . $emailSubject;
    
    // Body
    $fullName = trim($firstName . ' ' . $lastName);
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 8px; border-bottom: 1px solid #eee; }
            td:first-child { font-weight: bold; width: 120px; color: #555; }
            .message { margin-top: 15px; padding: 15px; background: white; border-left: 3px solid #3498db; white-space: pre-wrap; }
            .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 0.85em; color: #777; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Neue Kontaktanfrage</h2>
            </div>
            <div class="content">
                <table>
                    <tr><td>Name:</td><td>' . e($fullName) . '</td></tr>
                    <tr><td>E-Mail:</td><td><a href="mailto:' . e($email) . '">' . e($email) . '</a></td></tr>
                    ' . (!empty($phone) ? '<tr><td>Telefon:</td><td>' . e($phone) . '</td></tr>' : '') . '
                    ' . (!empty($subject) ? '<tr><td>Betreff:</td><td>' . e($subject) . '</td></tr>' : '') . '
                </table>
                
                <div class="message">
                    <strong>Nachricht:</strong><br><br>
                    ' . nl2br(e($message)) . '
                </div>
                
                <div class="footer">
                    <strong>Metadata:</strong><br>
                    Spam-Score: ' . $validation['spamScore'] . '/100<br>
                    IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . '<br>
                    Zeitstempel: ' . date('d.m.Y H:i:s') . '
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $mail->isHTML(true);
    $mail->Body = $htmlBody;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $htmlBody));
    
    // Send
    if (!$mail->send()) {
        error_log('[CONTACT FORM] Failed to send admin email: ' . $mail->ErrorInfo);
        json_error('Failed to send message. Please try again.', 500);
    }
    
    // Log sent email (.eml)
    $emlDir = $logsDir . '/sent-eml';
    if (!is_dir($emlDir)) {
        @mkdir($emlDir, 0755, true);
    }
    
    $emlFile = $emlDir . '/mail-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
    if ($mail->preSend()) {
        @file_put_contents($emlFile, $mail->getSentMIMEMessage());
    }
    
} catch (Exception $e) {
    error_log('[CONTACT FORM] PHPMailer Exception: ' . $e->getMessage());
    json_error('Email system error. Please contact administrator.', 500);
}

// ============================================================================
// 7b) NF-02 FIX: CONFIRMATION MAIL RATE-LIMIT (max 1 per email per 24h)
// Verhindert Missbrauch als Spam-Relay über die Bestätigungsmail.
// E-Mail-Adresse wird als SHA-256-Hash gespeichert (DSGVO-konform).
// ============================================================================

$confirmRateLimitFile = $dataDir . '/confirm_ratelimit.json';
$confirmLimits = json_decode(@file_get_contents($confirmRateLimitFile) ?: '{}', true) ?: [];
$emailHash = hash('sha256', strtolower(trim($email)));
$cutoff24h = time() - 86400;

// Alte Einträge bereinigen (älter als 24h)
$confirmLimits = array_filter($confirmLimits, fn($t) => $t > $cutoff24h);

$skipConfirmation = false;
if (isset($confirmLimits[$emailHash]) && $confirmLimits[$emailHash] > $cutoff24h) {
    $skipConfirmation = true;
    error_log('[CONTACT FORM] Confirmation mail rate-limited for ' . $email . ' (already sent within 24h)');
} else {
    $confirmLimits[$emailHash] = time();
    file_put_contents($confirmRateLimitFile, json_encode($confirmLimits), LOCK_EX);
}

// ============================================================================
// 8) SEND CONFIRMATION TO USER
// ============================================================================

if (!$skipConfirmation) {

$confirmMail = new PHPMailer(true);

try {
    // Same SMTP config
    $confirmMail->isSMTP();
    $confirmMail->Host       = $smtpHost;
    $confirmMail->Port       = $smtpPort;
    $confirmMail->SMTPAuth   = true;
    $confirmMail->Username   = $smtpUser;
    $confirmMail->Password   = $smtpPass;
    
    if ($smtpSecure === 'tls' || $smtpPort === 587) {
        $confirmMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($smtpSecure === 'ssl' || $smtpPort === 465) {
        $confirmMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }
    
    $confirmMail->CharSet = 'UTF-8';
    $confirmMail->Encoding = 'base64';
    
    // From/To
    $confirmMail->setFrom($smtpUser, 'jozapf.de');
    $confirmMail->addAddress($email, $fullName);
    $confirmMail->Sender = $smtpUser;
    
    // Subject
    $confirmMail->Subject = 'Thank you for your message - www.jozapf.de';
    
    // Body
    $confirmHtml = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #27ae60; color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
            .checkmark { font-size: 48px; margin-bottom: 10px; }
            .content { background: #f9f9f9; padding: 30px 20px; border: 1px solid #ddd; border-top: none; }
            .message-copy { margin: 20px 0; padding: 20px; background: white; border-left: 4px solid #27ae60; }
            .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 0.85em; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="checkmark">✓</div>
                <h2>Message Received</h2>
            </div>
            <div class="content">
                <p>Dear ' . e($firstName) . ',</p>
                
                <p>Thank you for contacting me! I have received your message and will get back to you as soon as possible.</p>
                
                <div class="message-copy">
                    <strong>Your message:</strong><br><br>
                    ' . (!empty($subject) ? '<strong>Subject:</strong> ' . e($subject) . '<br><br>' : '') . '
                    ' . nl2br(e($message)) . '
                </div>
                
                <p>Best regards,<br>
                <strong>Jo Zapf</strong><br>
                <a href="https://jozapf.de">jozapf.de</a></p>
                
                <div class="footer">
                    This is an automated confirmation email. Please do not reply!<br>
                    © ' . date('Y') . ' jozapf.de
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $confirmMail->isHTML(true);
    $confirmMail->Body = $confirmHtml;
    $confirmMail->AltBody = strip_tags(str_replace('<br>', "\n", $confirmHtml));
    
    // Send (don't fail if confirmation fails)
    $confirmSent = @$confirmMail->send();
    
    // Log confirmation
    if ($confirmSent) {
        $confirmEmlFile = $emlDir . '/confirmation-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        if ($confirmMail->preSend()) {
            @file_put_contents($confirmEmlFile, $confirmMail->getSentMIMEMessage());
        }
    }
    
} catch (Exception $e) {
    error_log('[CONTACT FORM] Confirmation email failed: ' . $e->getMessage());
    // Don't fail the whole request if confirmation fails
}

} // Ende NF-02: if (!$skipConfirmation)

// ============================================================================
// 9) SUCCESS RESPONSE
// ============================================================================

$confirmMessage = $skipConfirmation
    ? 'Thank you for your message! We will get back to you shortly.'
    : 'Thank you for your message! We will get back to you shortly. A confirmation email has been sent to ' . $email;

json_success(
    $confirmMessage,
    [
        'timestamp' => date('c'),
        'spamScore' => $validation['spamScore'],
        'confirmationSent' => ($confirmSent ?? false) && !$skipConfirmation
    ]
);
