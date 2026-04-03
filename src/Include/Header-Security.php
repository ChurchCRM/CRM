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
    "frame-ancestors 'self'",
    'report-uri ' . SystemURLs::getRootPath() . '/api/public/csp-report',
];

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP can be in report-only mode (violations logged but not blocked) or enforcing mode (violations blocked)
// The mode is controlled by the bEnforceCSP system configuration option
if (SystemConfig::getBooleanValue('bEnforceCSP')) {
    header('Content-Security-Policy: ' . join('; ', $csp));
} else {
    header('Content-Security-Policy-Report-Only: ' . join('; ', $csp));
}
