<?php
/**
 * config.php
 * -----------------------------------------------------------------
 * Site-wide backend configuration for the Scuppet Media contact form.
 * No database is used - submissions are only emailed, never stored.
 *
 * SECURITY: keep this file OUT of version control (add it to
 * .gitignore) since it holds credentials. Consider moving the SMTP
 * values into real environment variables later instead of hardcoding
 * them here.
 * -----------------------------------------------------------------
 */

declare(strict_types=1);

// ==== Notification email settings ====
// NOTIFY_TO_EMAIL -> where enquiry notifications are sent (you)
define('NOTIFY_TO_EMAIL', 'shubhampro2002@gmail.com');
define('NOTIFY_FROM_NAME', 'Scuppet Media Website');

// The name used as the "From" on the auto-reply that goes back to the
// visitor. Keep it human and recognisable.
define('AUTOREPLY_FROM_NAME', 'Scuppet Media');

// ==== Rate limiting ====
// Minimum seconds a visitor must wait between two submissions
// (tracked per browser session - no database needed).
define('RATE_LIMIT_SECONDS', 30);

// ==== CORS / allowed origin ====
// Your own site's origin(s) (scheme + host, no trailing slash).
// Comma-separate multiple origins (local dev + live domain).
// Used to reject cross-site form submissions.
define('ALLOWED_ORIGIN', 'http://127.0.0.1:8000,http://localhost:8000,https://scuppetmedia.com');

// ==== SMTP settings (Gmail) ====
// PHP's mail() is unreliable on most local setups and Gmail will
// reject/spam anything claiming to be "From: you@gmail.com" that wasn't
// actually sent through Gmail's own servers. So we send THROUGH Gmail's
// SMTP server using an App Password (not your normal Gmail password).
//
// How to get an App Password:
//   1. Turn on 2-Step Verification: myaccount.google.com/security
//   2. Generate one at: myaccount.google.com/apppasswords
//   3. Paste the 16-character password below (spaces don't matter).
//
// REPLACE THESE PLACEHOLDERS before going live:
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);              // 587 = TLS (recommended), 465 = SSL
define('SMTP_SECURE', 'tls');          // 'tls' for port 587, 'ssl' for port 465
define('SMTP_USERNAME', 'digitaluniversalbank2@gmail.com'); // the Gmail address sending mail
define('SMTP_PASSWORD', 'uzjo cqod ddop tycb');

// This MUST match SMTP_USERNAME for Gmail - Gmail ignores/overrides any
// other "From" address and sends as the authenticated account anyway.
define('NOTIFY_FROM_EMAIL', SMTP_USERNAME);