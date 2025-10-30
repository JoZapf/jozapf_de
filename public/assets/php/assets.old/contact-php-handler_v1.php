<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Konfiguration
$recipient_email = "ihre-email@domain.com"; // Ihre E-Mail-Adresse
$subject_prefix = "Kontaktformular: ";

// Funktion für sichere Eingaben
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Antwort-Array
$response = array('success' => false, 'message' => '');

try {
    // Prüfen ob POST-Request
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Nur POST-Requests erlaubt.");
    }

    // Required Fields prüfen
    $required_fields = ['firstName', 'lastName', 'email', 'subject', 'message', 'captchaAnswer', 'captcha_answer'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Pflichtfeld fehlt: " . $field);
        }
    }

    // Eingaben sanitizen
    $firstName = sanitize_input($_POST['firstName']);
    $lastName = sanitize_input($_POST['lastName']);
    $email = sanitize_input($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    $user_answer = intval($_POST['captchaAnswer']);
    $correct_answer = intval($_POST['captcha_answer']);

    // E-Mail-Validierung
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Ungültige E-Mail-Adresse.");
    }

    // Captcha prüfen
    if ($user_answer !== $correct_answer) {
        throw new Exception("Captcha falsch gelöst.");
    }

    // Datenschutz-Checkbox prüfen
    if (!isset($_POST['privacy']) || $_POST['privacy'] !== 'on') {
        throw new Exception("Datenschutzerklärung muss akzeptiert werden.");
    }

    // Spam-Schutz: Rate Limiting (einfach)
    session_start();
    $now = time();
    if (isset($_SESSION['last_submit']) && ($now - $_SESSION['last_submit']) < 60) {
        throw new Exception("Bitte warten Sie mindestens 1 Minute zwischen Nachrichten.");
    }
    $_SESSION['last_submit'] = $now;

    // E-Mail zusammenstellen
    $full_subject = $subject_prefix . $subject;
    
    $email_body = "Neue Kontaktanfrage\n\n";
    $email_body .= "Name: " . $firstName . " " . $lastName . "\n";
    $email_body .= "E-Mail: " . $email . "\n";
    if (!empty($phone)) {
        $email_body .= "Telefon: " . $phone . "\n";
    }
    $email_body .= "Betreff: " . $subject . "\n\n";
    $email_body .= "Nachricht:\n" . $message . "\n\n";
    $email_body .= "---\n";
    $email_body .= "IP-Adresse: " . $_SERVER['REMOTE_ADDR'] . "\n";
    $email_body .= "Zeitpunkt: " . date('d.m.Y H:i:s') . "\n";

    // E-Mail-Header
    $headers = array(
        'From' => $email,
        'Reply-To' => $email,
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/plain; charset=UTF-8'
    );

    // Headers zu String
    $headers_string = '';
    foreach ($headers as $key => $value) {
        $headers_string .= $key . ': ' . $value . "\r\n";
    }

    // E-Mail senden
    if (mail($recipient_email, $full_subject, $email_body, $headers_string)) {
        
        // Bestätigungs-E-Mail an Absender (optional)
        $confirm_subject = "Bestätigung Ihrer Kontaktanfrage";
        $confirm_message = "Liebe/r " . $firstName . " " . $lastName . ",\n\n";
        $confirm_message .= "vielen Dank für Ihre Nachricht. Wir haben Ihre Anfrage erhalten und werden uns schnellstmöglich bei Ihnen melden.\n\n";
        $confirm_message .= "Ihre Nachricht:\n";
        $confirm_message .= "Betreff: " . $subject . "\n";
        $confirm_message .= "Nachricht: " . $message . "\n\n";
        $confirm_message .= "Mit freundlichen Grüßen\n";
        $confirm_message .= "Ihr Team";

        $confirm_headers = "From: " . $recipient_email . "\r\n";
        $confirm_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Bestätigungs-E-Mail senden (fehlerhafte Zustellung wird ignoriert)
        @mail($email, $confirm_subject, $confirm_message, $confirm_headers);
        
        $response['success'] = true;
        $response['message'] = 'Ihre Nachricht wurde erfolgreich gesendet.';
        
    } else {
        throw new Exception("Fehler beim E-Mail-Versand. Bitte versuchen Sie es später erneut.");
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Fehler loggen (optional)
    error_log("Contact Form Error: " . $e->getMessage());
}

// JSON-Antwort senden
echo json_encode($response);
exit;
?>