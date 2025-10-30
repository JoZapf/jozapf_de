<?php
/**
 * Contact Form PHP Handler - Production Version
 * 
 * @author JoZapf
 * @version 2.2.0
 * @date 2025-10-02
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function json_success($message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function json_error($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Only POST requests are allowed', 405);
}

// ============================================================================
// FIELD MAPPING: firstName + lastName → name
// ============================================================================

if (isset($_POST['firstName']) || isset($_POST['lastName'])) {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $_POST['name'] = trim($firstName . ' ' . $lastName);
}

// ============================================================================
// ABUSE LOGGER INTEGRATION
// ============================================================================

require_once __DIR__ . '/AbuseLogger.php';

$abuseLogger = new AbuseLogger(
    __DIR__ . '/logs',
    false  // Set to true for GDPR IP anonymization
);

$abuseCheck = $abuseLogger->checkAndLog($_POST);

if (!$abuseCheck['allowed']) {
    json_error($abuseCheck['reason'], 429);
}

// ============================================================================
// FORM VALIDATION
// ============================================================================

// Honeypot
if (!empty($_POST['website'])) {
    json_error('Invalid submission', 422);
}

// Bot-Timing
if (isset($_POST['formStart'])) {
    $formStart = (int)$_POST['formStart'];
    $timeTaken = (time() * 1000) - $formStart;
    
    if ($timeTaken < 3000) {
        json_error('Please take your time filling the form', 422);
    }
}

// Required fields with firstName/lastName support
$firstName = isset($_POST['firstName']) ? filter_var(trim($_POST['firstName']), FILTER_SANITIZE_STRING) : '';
$lastName = isset($_POST['lastName']) ? filter_var(trim($_POST['lastName']), FILTER_SANITIZE_STRING) : '';
$name = trim($firstName . ' ' . $lastName);

// Validation: Both fields required
if (empty($firstName)) {
    json_error("First name is required", 400);
}

if (empty($lastName)) {
    json_error("Last name is required", 400);
}

// Validation: Minimum 2 characters per field
if (strlen($firstName) < 2) {
    json_error("First name must be at least 2 characters", 400);
}

if (strlen($lastName) < 2) {
    json_error("Last name must be at least 2 characters", 400);
}

// Validation: Maximum 50 characters per field
if (strlen($firstName) > 50) {
    json_error("First name cannot exceed 50 characters", 400);
}

if (strlen($lastName) > 50) {
    json_error("Last name cannot exceed 50 characters", 400);
}

// Other required fields
if (empty($_POST['email'])) {
    json_error("Email is required", 400);
}

if (empty($_POST['message'])) {
    json_error("Message is required", 400);
}

// Sanitize inputs
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$phone = isset($_POST['phone']) ? filter_var(trim($_POST['phone']), FILTER_SANITIZE_STRING) : '';
$subject = isset($_POST['subject']) ? filter_var(trim($_POST['subject']), FILTER_SANITIZE_STRING) : 'Contact Form Submission';
$message = filter_var(trim($_POST['message']), FILTER_SANITIZE_STRING);

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email address', 400);
}

// Message validation
if (strlen($message) < 10) {
    json_error('Message must be at least 10 characters', 400);
}

if (strlen($message) > 5000) {
    json_error('Message cannot exceed 5000 characters', 400);
}

// ============================================================================
// SEND EMAIL
// ============================================================================

$to = 'mail@jozapf.de';  // ✅ CORRECTED EMAIL
$from = 'no-reply@jozapf.de';

$headers = [
    'From: ' . $from,
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion(),
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8'
];

$emailBody = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3498db; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #555; }
        .value { margin-top: 5px; padding: 10px; background: white; border-left: 3px solid #3498db; }
        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 0.9em; color: #777; }
        .spam-score { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .spam-low { background: #d4edda; color: #155724; }
        .spam-medium { background: #fff3cd; color: #856404; }
        .spam-high { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>📧 New Contact Request</h2>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='label'>👤 Name:</div>
                <div class='value'>" . htmlspecialchars($name) . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>📧 Email:</div>
                <div class='value'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></div>
            </div>
            
            " . (!empty($phone) ? "
            <div class='field'>
                <div class='label'>📞 Phone:</div>
                <div class='value'>" . htmlspecialchars($phone) . "</div>
            </div>
            " : "") . "
            
            <div class='field'>
                <div class='label'>📋 Subject:</div>
                <div class='value'>" . htmlspecialchars($subject) . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>💬 Message:</div>
                <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
            </div>
            
            <div class='footer'>
                <strong>Metadata:</strong><br>
                Spam Score: <span class='spam-score " . 
                    ($abuseCheck['spamScore'] < 40 ? 'spam-low' : 
                    ($abuseCheck['spamScore'] < 70 ? 'spam-medium' : 'spam-high')) . "'>" . 
                    $abuseCheck['spamScore'] . "/100</span><br>
                IP: " . $_SERVER['REMOTE_ADDR'] . "<br>
                User Agent: " . htmlspecialchars($_SERVER['HTTP_USER_AGENT']) . "<br>
                Timestamp: " . date('Y-m-d H:i:s') . "
            </div>
        </div>
    </div>
</body>
</html>
";

$emailSent = mail($to, $subject, $emailBody, implode("\r\n", $headers));

if (!$emailSent) {
    json_error('Failed to send email. Please try again later.', 500);
}

// ============================================================================
// EMAIL LOGGING
// ============================================================================

$logDir = __DIR__ . '/logs/sent-eml';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Ymd-His');
$uniqueId = substr(md5(uniqid()), 0, 8);
$emlFilename = "mail-{$timestamp}-{$uniqueId}.eml";

$emlContent = "From: {$from}\r\n";
$emlContent .= "To: {$to}\r\n";
$emlContent .= "Reply-To: {$email}\r\n";
$emlContent .= "Subject: {$subject}\r\n";
$emlContent .= "Date: " . date('r') . "\r\n";
$emlContent .= "MIME-Version: 1.0\r\n";
$emlContent .= "Content-Type: text/html; charset=UTF-8\r\n";
$emlContent .= "\r\n";
$emlContent .= $emailBody;

file_put_contents($logDir . '/' . $emlFilename, $emlContent);

// ============================================================================
// SUCCESS RESPONSE
// ============================================================================

json_success(
    'Thank you for your message! We will get back to you shortly.',
    [
        'messageId' => $uniqueId,
        'timestamp' => date('c'),
        'spamScore' => $abuseCheck['spamScore'],
        'name' => $name
    ]
);
