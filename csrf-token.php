<?php
/**
 * csrf-token.php
 * -----------------------------------------------------------------
 * Issues a per-session CSRF token that the front-end JS fetches once
 * per page load and embeds into the contact form before it can be
 * submitted. contact-handler.php refuses any request whose token
 * doesn't match this session's token.
 * -----------------------------------------------------------------
 */

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);