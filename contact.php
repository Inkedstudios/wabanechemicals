<?php
/**
 * Wabane Chemicals Chemicals - contact form handler
 *
 * Receives the POST from contact.html, validates it, and emails it to your team.
 * Works with or without JavaScript:
 *   - With JavaScript the page calls this file with fetch() and receives JSON.
 *   - Without JavaScript the visitor is redirected back to contact.html with a status.
 *
 * SETUP: edit the CONFIG block below, then upload to any PHP 7.4+ host.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

session_start();

// Load PHPMailer from your website folder.
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/* ------------------------------ CONFIG ------------------------------ */

const TO_EMAIL       = 'sales@wabanechemicals.co.za';
const FROM_EMAIL     = 'sales@wabanechemicals.co.za';
const SITE_NAME      = 'Wabane Chemicals';
const SEND_AUTOREPLY = true;
const REDIRECT_OK    = 'contact.html?status=sent#form';
const REDIRECT_ERR   = 'contact.html?status=error#form';
const MIN_SECONDS    = 3;
const MAX_PER_HOUR   = 5;

/* -------------------------------------------------------------------- */

$wantsJson = isset($_SERVER['HTTP_ACCEPT'])
    && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond(bool $ok, string $message, bool $wantsJson): void
{
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 422);
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } else {
        header('Location: ' . ($ok ? REDIRECT_OK : REDIRECT_ERR));
    }

    exit;
}

/* Remove control characters and email header line breaks. */
function clean_line(?string $v, int $max = 200): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';

    return mb_substr($v, 0, $max);
}

/* Clean message text while preserving normal line breaks. */
function clean_text(?string $v, int $max = 5000): string
{
    $v = trim((string) $v);
    $v = preg_replace('/[^\P{C}\n\r\t]+/u', '', $v) ?? '';

    return mb_substr($v, 0, $max);
}

/* Only accept POST requests. */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

/* Honeypot spam check. */
if (!empty($_POST['website'])) {
    respond(true, 'Thank you. Your message has been received.', $wantsJson);
}

/* Minimum completion time. */
if (!empty($_POST['ts']) && ctype_digit((string) $_POST['ts'])) {
    if (time() - (int) $_POST['ts'] < MIN_SECONDS) {
        respond(
            false,
            'Please take a moment to complete the form, then send it again.',
            $wantsJson
        );
    }
}

/* Rate limit submissions per session. */
$now = time();

$_SESSION['sent'] = array_values(array_filter(
    $_SESSION['sent'] ?? [],
    fn($t) => $now - $t < 3600
));

if (count($_SESSION['sent']) >= MAX_PER_HOUR) {
    respond(
        false,
        'You have sent several messages recently. Please try again later or call us.',
        $wantsJson
    );
}

/* Collect and validate fields. */
$name    = clean_line($_POST['name'] ?? '', 100);
$company = clean_line($_POST['company'] ?? '', 120);
$email   = clean_line($_POST['email'] ?? '', 150);
$phone   = clean_line($_POST['phone'] ?? '', 40);
$type    = clean_line($_POST['enquiry_type'] ?? 'General enquiry', 60);
$message = clean_text($_POST['message'] ?? '');
$consent = !empty($_POST['consent']);

$errors = [];

if ($name === '' || mb_strlen($name) < 2) {
    $errors[] = 'Please enter your name.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (
    $phone !== ''
    && !preg_match('/^[0-9+()\\-\s]{7,40}$/', $phone)
) {
    $errors[] = 'Please enter a valid phone number.';
}

if (mb_strlen($message) < 10) {
    $errors[] = 'Please tell us a little more in your message.';
}

if (!$consent) {
    $errors[] = 'Please accept the privacy consent so we can reply to you.';
}

if ($errors) {
    respond(false, implode(' ', $errors), $wantsJson);
}

/* Build the enquiry email. */
$subject = '[' . SITE_NAME . '] ' . $type . ' from ' . $name;

$body  = "New website enquiry\n";
$body .= "===================\n\n";
$body .= "Name:      $name\n";
$body .= "Company:   " . ($company ?: '-') . "\n";
$body .= "Email:     $email\n";
$body .= "Phone:     " . ($phone ?: '-') . "\n";
$body .= "Enquiry:   $type\n\n";
$body .= "Message:\n$message\n\n";
$body .= "-------------------------------------------\n";
$body .= 'Sent: ' . date('Y-m-d H:i:s');
$body .= ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
$body .= "Consent to be contacted: given\n";

/* Get the SMTP password from the local environment. */
$smtpPassword = getenv('CHEMICALS_SMTP_PASSWORD');

if ($smtpPassword === false || $smtpPassword === '') {
    error_log('Contact form: SMTP password environment variable is missing.');

    respond(
        false,
        'Email service is temporarily unavailable. Please call us.',
        $wantsJson
    );
}

/*
 * SMTP SETTINGS
 * These settings assume GoDaddy Professional Email powered by Titan.
 * Confirm your email product before using them.
 */
 function createMailer(string $smtpPassword): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtpout.secureserver.net';
    $mail->SMTPAuth   = true;
    $mail->Username   = FROM_EMAIL;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(false);

    return $mail;
}

$smtpPassword = getenv('CHEMICALS_SMTP_PASSWORD');

if (!$smtpPassword) {
    error_log('Contact form: SMTP password is not configured.');

    respond(
        false,
        'Email service is temporarily unavailable. Please contact us directly.',
        $wantsJson
    );
}

/* Send the enquiry to your business inbox. */
try {
    $mail = createMailer($smtpPassword);

    $mail->setFrom(FROM_EMAIL, SITE_NAME . ' Website');
    $mail->addAddress(TO_EMAIL);
    $mail->addReplyTo($email, $name);

    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();

} catch (\Throwable $e) {
    error_log(
        'Contact form: enquiry email failed. '
        . (isset($mail) ? $mail->ErrorInfo : $e->getMessage())
    );

    respond(
        false,
        'Your message could not be sent right now. Please call us or try again shortly.',
        $wantsJson
    );
}

/* Send the visitor a confirmation copy, if enabled. */
if (SEND_AUTOREPLY) {
    try {
        $reply  = "Hi $name,\n\n";
        $reply .= 'Thank you for contacting ' . SITE_NAME . ". ";
        $reply .= "We have received your enquiry and a member of our team ";
        $reply .= "will reply within one working day.\n\n";
        $reply .= "A copy of your message:\n$message\n\n";
        $reply .= "Kind regards,\n" . SITE_NAME . "\n";

        $confirmation = createMailer($smtpPassword);

        $confirmation->setFrom(FROM_EMAIL, SITE_NAME);
        $confirmation->addAddress($email, $name);
        $confirmation->Subject =
            'We received your enquiry - ' . SITE_NAME;
        $confirmation->Body = $reply;

        $confirmation->send();

    } catch (\Throwable $e) {
        // The original enquiry was delivered; log any auto-reply failure.
        error_log(
            'Contact form: auto-reply failed. '
            . (isset($confirmation)
                ? $confirmation->ErrorInfo
                : $e->getMessage())
        );
    }
}

/* Record successful enquiry delivery. */
$_SESSION['sent'][] = $now;

respond(
    true,
    'Thank you. Your message has been sent and we will reply within one working day.',
    $wantsJson
);