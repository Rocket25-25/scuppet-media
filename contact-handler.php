<?php
/**
 * contact-handler.php
 * -----------------------------------------------------------------
 * Backend endpoint for the Scuppet Media contact form
 * (name, email, phone, company, service, serviceOther, message).
 *
 * Sends mail via Gmail SMTP (PHPMailer) instead of PHP's built-in
 * mail(), which is unreliable/unconfigured on most local setups and
 * gets rejected by Gmail's spam filters when the "From" header
 * doesn't match an authenticated sender.
 * -----------------------------------------------------------------
 */

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/config.php';

// PHPMailer, installed via: composer require phpmailer/phpmailer
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function respond(bool $success, string $message, array $errors = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors'  => $errors,
    ]);
    exit;
}

/**
 * Sends the JSON reply to the browser RIGHT NOW and closes the connection,
 * while letting this script keep running in the background (to send the
 * emails). This is what removes the 15-20 second wait for the visitor.
 */
function respond_early(bool $success, string $message): void
{
    ignore_user_abort(true);   // keep running after the browser has its answer
    set_time_limit(60);        // hard cap for the background email work

    $body = json_encode([
        'success' => $success,
        'message' => $message,
        'errors'  => [],
    ]);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(200);
    header('Content-Encoding: none');            // stop gzip from delaying the flush
    header('Content-Length: ' . strlen((string) $body));
    header('Connection: close');

    echo $body;
    flush();

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();                // PHP-FPM / FastCGI
    }
}

// ---------------------------------------------------------------
// 1. Method check
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', [], 405);
}

// ---------------------------------------------------------------
// 2. Same-origin check (defence in depth, not a full CSRF fix on its own)
//
//    ALLOWED_ORIGIN in config.php can hold ONE OR MORE origins,
//    comma-separated. We compare HOSTNAMES only (ignoring http/https
//    and a leading "www.") instead of requiring an exact string-prefix
//    match, to avoid false-positive 403s from scheme/www/path
//    differences.
// ---------------------------------------------------------------
function extract_host(string $url): string
{
    $host = parse_url(trim($url), PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }
    return strtolower(preg_replace('/^www\./', '', $host) ?? $host);
}

function allowed_hosts(string $allowedOriginConfig): array
{
    $hosts = [];
    foreach (explode(',', $allowedOriginConfig) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $host = extract_host($part);
        if ($host !== '') {
            $hosts[] = $host;
        }
    }
    return array_values(array_unique($hosts));
}

$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

$allowedHostList = allowed_hosts(ALLOWED_ORIGIN);
$originHost      = $origin !== ''  ? extract_host($origin)  : '';
$refererHost     = $referer !== '' ? extract_host($referer) : '';

$sourceOk = false;

if (empty($allowedHostList)) {
    // Misconfigured ALLOWED_ORIGIN in config.php - fail safe by allowing
    // the request through rather than silently locking everyone out.
    // (Fix ALLOWED_ORIGIN in config.php so this branch is never hit.)
    $sourceOk = true;
} elseif ($originHost !== '' && in_array($originHost, $allowedHostList, true)) {
    $sourceOk = true;
} elseif ($refererHost !== '' && in_array($refererHost, $allowedHostList, true)) {
    $sourceOk = true;
} elseif ($origin === '' && $referer === '') {
    // Some browsers/extensions/privacy tools strip these headers even for
    // legitimate same-site requests; don't block those outright.
    $sourceOk = true;
}

if (!$sourceOk) {
    respond(false, 'Request rejected.', [], 403);
}

// ---------------------------------------------------------------
// 3. CSRF token check
// ---------------------------------------------------------------
$submittedToken = $_POST['csrf_token'] ?? '';
if (
    empty($_SESSION['csrf_token']) ||
    !is_string($submittedToken) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    respond(false, 'Your session expired. Please refresh the page and try again.', [], 419);
}

// ---------------------------------------------------------------
// 4. Honeypot check - bots fill every field, real users never see this one
// ---------------------------------------------------------------
if (!empty($_POST['website_url'])) {
    respond(true, 'Message received.');
}

// ---------------------------------------------------------------
// 5. Rate limiting (per session - no database needed)
// ---------------------------------------------------------------
$now = time();
if (!empty($_SESSION['last_submit_time']) && ($now - (int) $_SESSION['last_submit_time']) < RATE_LIMIT_SECONDS) {
    respond(false, 'You are submitting too quickly. Please wait a moment and try again.', [], 429);
}

// ---------------------------------------------------------------
// 6. Helpers
// ---------------------------------------------------------------
function clean_text(string $value, int $maxLen): string
{
    $value = trim($value);
    $value = strip_tags($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return mb_substr($value, 0, $maxLen);
}

function header_safe(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? $value);
}

function is_valid_name(string $value): bool
{
    return (bool) preg_match('/^[\p{L}\p{M} .\'-]{2,100}$/u', $value);
}

function is_valid_phone(string $value): bool
{
    // Generic international check: 7-15 digits, optional leading +.
    // Looser than a single-country format since Scuppet Media's form
    // marks phone as optional and doesn't restrict to one country.
    $digitsOnly = preg_replace('/[\s\-()]/', '', $value);
    return (bool) preg_match('/^\+?[0-9]{7,15}$/', (string) $digitsOnly);
}

const ALLOWED_SERVICES = [
    'LinkedIn Marketing',
    'Video Editing',
    'Website Development',
    'Social Media Marketing',
    'Other',
];

/**
 * Builds and connects a single PHPMailer instance whose SMTP connection
 * stays open (SMTPKeepAlive) so multiple emails can be sent through it
 * without reconnecting/re-authenticating each time. Returns null if the
 * connection itself couldn't be established.
 */
function open_smtp_connection(): ?PHPMailer
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host          = SMTP_HOST;
        $mail->SMTPAuth      = true;
        $mail->Username      = SMTP_USERNAME;
        $mail->Password      = SMTP_PASSWORD;
        $mail->SMTPSecure    = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port          = SMTP_PORT;
        $mail->SMTPKeepAlive = true; // reuse this connection across multiple send() calls
        $mail->Timeout       = 10;   // default is 300s; fail fast instead of hanging

        // On Windows/XAMPP, PHP often tries Gmail's IPv6 address first, which
        // times out before falling back to IPv4. Resolve the hostname to an
        // IPv4 address ourselves and connect to that directly. TLS still
        // verifies the certificate against the real hostname (peer_name).
        $smtpHost = SMTP_HOST;
        $ipv4     = gethostbyname(SMTP_HOST); // returns the hostname unchanged if lookup fails
        if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $smtpHost = $ipv4;
        }
        $mail->Host = $smtpHost;

        $mail->SMTPOptions = [
            'socket' => [
                'bindto' => '0:0',
            ],
            'ssl' => [
                'peer_name'         => SMTP_HOST,
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'SNI_enabled'       => true,
                'SNI_server_name'   => SMTP_HOST,
            ],
        ];

        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';

        // Establish the connection + auth now, once, up front.
        $connected = $mail->smtpConnect($mail->SMTPOptions);
        if (!$connected) {
            error_log('SMTP connect failed: ' . $mail->ErrorInfo);
            return null;
        }

        return $mail;
    } catch (PHPMailerException $e) {
        error_log('SMTP connect failed: ' . $mail->ErrorInfo);
        return null;
    }
}

/**
 * Sends one message over an already-open PHPMailer connection.
 * Returns true on success, false on failure (and logs the real reason).
 */
function send_over_connection(PHPMailer $mail, string $toEmail, string $toName, string $subject, string $body, string $replyToEmail, string $replyToName): bool
{
    try {
        $mail->clearAddresses();
        $mail->clearReplyTos();
        $mail->clearAttachments();

        $mail->setFrom(NOTIFY_FROM_EMAIL, NOTIFY_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        if ($replyToEmail !== '') {
            $mail->addReplyTo($replyToEmail, $replyToName);
        }

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('SMTP mail failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// ---------------------------------------------------------------
// 7. Validate the Scuppet Media contact form fields
// ---------------------------------------------------------------
$name         = clean_text((string) ($_POST['name'] ?? ''), 100);
$email        = trim((string) ($_POST['email'] ?? ''));
$phone        = clean_text((string) ($_POST['phone'] ?? ''), 30);
$company      = clean_text((string) ($_POST['company'] ?? ''), 150);
$service      = clean_text((string) ($_POST['service'] ?? ''), 100);
$serviceOther = clean_text((string) ($_POST['serviceOther'] ?? ''), 150);
$message      = clean_text((string) ($_POST['message'] ?? ''), 5000);

$errors = [];

if ($name === '' || !is_valid_name($name)) {
    $errors['name'] = 'Please enter your name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($phone !== '' && !is_valid_phone($phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}
if ($service === '' || !in_array($service, ALLOWED_SERVICES, true)) {
    $errors['service'] = 'Please select a service.';
}
if ($service === 'Other' && $serviceOther === '') {
    $errors['serviceOther'] = 'Please specify the service you need.';
}
if ($message === '' || mb_strlen($message) < 10) {
    $errors['message'] = 'Please tell us a little about your project (at least 10 characters).';
} elseif (mb_strlen($message) > 5000) {
    $errors['message'] = 'Message is too long.';
}

if (!empty($errors)) {
    respond(false, 'Please correct the highlighted fields.', $errors, 422);
}

// ---------------------------------------------------------------
// 8. Open ONE SMTP connection and send both emails through it
// ---------------------------------------------------------------
$safeName  = header_safe($name);
$safeEmail = header_safe($email);

$adminSubject = 'New contact form submission - Scuppet Media';

$adminLines = [
    'NEW CONTACT FORM SUBMISSION',
    '====================================================================',
    '',
    'Name: ' . $name,
    'Email: ' . $email,
    'Phone: ' . ($phone !== '' ? $phone : 'Not provided'),
    'Company: ' . ($company !== '' ? $company : 'Not provided'),
    'Service: ' . $service . ($service === 'Other' && $serviceOther !== '' ? ' - ' . $serviceOther : ''),
    '',
    'Message:',
    $message,
    '',
    '--------------------------------------------------------------------',
    'Submitted: ' . date('l, d M Y - h:i A'),
];

$adminBody = implode("\r\n", $adminLines);

$firstName = explode(' ', trim($name))[0];
$firstName = $firstName !== '' ? $firstName : 'there';

$userSubject = 'We received your message - Scuppet Media';

$userLines = [
    'Hi ' . $firstName . ',',
    '',
    'Thanks for reaching out to Scuppet Media! We\'ve received your message and',
    'will get back to you shortly to start the conversation.',
    '',
    'Here is a copy of what you sent us:',
    '- Service: ' . $service . ($service === 'Other' && $serviceOther !== '' ? ' - ' . $serviceOther : ''),
    '- Message: ' . $message,
    '',
    'If anything above needs correcting, just reply to this email.',
    '',
    'Best,',
    'Scuppet Media',
];

$userBody = implode("\r\n", $userLines);

// ---------------------------------------------------------------
// Reply to the visitor immediately. The emails are sent AFTER this
// point, so the visitor never waits for Gmail's SMTP server.
// (Rate-limit timestamp is saved and the session lock released first.)
// ---------------------------------------------------------------
$_SESSION['last_submit_time'] = $now;
session_write_close();

respond_early(true, 'Message received.');

$smtp = open_smtp_connection();

if ($smtp === null) {
    error_log('Contact form: could not open SMTP connection. Enquiry from ' . $safeEmail . ' was NOT emailed.');
    exit;
}

$adminMailSent = send_over_connection(
    $smtp,
    NOTIFY_TO_EMAIL,
    NOTIFY_FROM_NAME,
    $adminSubject,
    $adminBody,
    $safeEmail,
    $safeName
);

// The visitor has already been answered, so a failure here can only be logged.
if (!$adminMailSent) {
    error_log('Contact form: admin notification failed. Enquiry from ' . $safeEmail . ' (' . $safeName . ') was NOT delivered.');
    $smtp->smtpClose();
    exit;
}

$userMailSent = send_over_connection(
    $smtp,
    $safeEmail,
    $safeName,
    $userSubject,
    $userBody,
    NOTIFY_TO_EMAIL,
    AUTOREPLY_FROM_NAME
);

if (!$userMailSent) {
    // Non-fatal: the admin already got the enquiry, so still report
    // success to the visitor, just without a confirmation email.
    error_log('Auto-reply email to visitor (' . $safeEmail . ') failed to send.');
}

$smtp->smtpClose();
exit;