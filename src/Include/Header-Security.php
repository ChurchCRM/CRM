<?php

/*
 * Security Headers Configuration
 *
 * This file configures security-related HTTP headers for ChurchCRM:
 *
 * - Content-Security-Policy (CSP): Helps protect against XSS attacks
 *   By default, CSP is in report-only mode. Enable enforcement via
 *   System Settings > bEnforceCSP configuration option.
 *   CSP violations are reported to /api/public/csp-report (public endpoint).
 *
 * - X-Frame-Options: Prevents clickjacking attacks
 * - X-Content-Type-Options: Prevents MIME-sniffing attacks
 * - Referrer-Policy: Controls how much referrer information is sent
 *
 * Opt-in for embeddable public pages (e.g. the external calendar route):
 * Set  $allowFraming = true;  in the template BEFORE requiring
 * HeaderNotLoggedIn.php (which in turn requires this file). When set,
 * X-Frame-Options is omitted and frame-ancestors is widened to "*" so
 * the page can be embedded in a third-party <iframe>. All other
 * clickjacking protections remain in force for every other route.
 */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$csp = [
    "default-src 'self'",
    "script-src 'self' 'nonce-" . SystemURLs::getCSPNonce() . "' 'unsafe-eval' browser-update.org https://www.googletagmanager.com",
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self' data: https://secure.gravatar.com https://tile.openstreetmap.org https://*.tile.openstreetmap.org",
    "media-src 'self'",
    "frame-src 'self'",
    "font-src 'self' data: fonts.gstatic.com",
    "connect-src 'self' https://www.google-analytics.com",
    "base-uri 'self'",
    "form-action 'self'",
];

// Framing / clickjacking protection.
// Public embeddable routes (e.g. /external/calendars/{token}) opt in to
// cross-origin embedding by setting $allowFraming = true in the template
// before requiring this file. All other pages keep the default clickjacking
// protection.
if (empty($allowFraming)) {
    // Default: prevent framing from other origins.
    $csp[] = "frame-ancestors 'self'";
    header('X-Frame-Options: SAMEORIGIN');
} else {
    // Embeddable route: allow framing from any origin.
    $csp[] = 'frame-ancestors *';
    // X-Frame-Options is intentionally omitted; browsers honour
    // CSP frame-ancestors when present, and absence of the legacy
    // header imposes no framing restriction.
}

$csp[] = 'report-uri ' . SystemURLs::getRootPath() . '/api/public/csp-report';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP can be in report-only mode (violations logged but not blocked) or enforcing mode (violations blocked)
// The mode is controlled by the bEnforceCSP system configuration option
if (SystemConfig::getBooleanValue('bEnforceCSP')) {
    header('Content-Security-Policy: ' . join('; ', $csp));
} else {
    header('Content-Security-Policy-Report-Only: ' . join('; ', $csp));
}
