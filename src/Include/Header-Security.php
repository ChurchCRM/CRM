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
 * - Strict-Transport-Security (HSTS): Enforces HTTPS connections
 *   Enable via System Settings > bHSTSEnable configuration option.
 *
 * - X-Frame-Options: Prevents clickjacking attacks
 */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$csp = [
    "default-src 'self'",
    "script-src 'self' 'nonce-" . SystemURLs::getCSPNonce() . "' 'unsafe-eval' browser-update.org",
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self' data:",
    "media-src 'self'",
    "frame-src 'self'",
    "font-src 'self' data: fonts.gstatic.com",
    "connect-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'self'",
    'report-uri ' . SystemURLs::getRootPath() . '/api/public/csp-report',
];

if (SystemConfig::getBooleanValue('bHSTSEnable')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('X-Frame-Options: SAMEORIGIN');
// CSP can be in report-only mode (violations logged but not blocked) or enforcing mode (violations blocked)
// The mode is controlled by the bEnforceCSP system configuration option
if (SystemConfig::getBooleanValue('bEnforceCSP')) {
    header('Content-Security-Policy: ' . join('; ', $csp));
} else {
    header('Content-Security-Policy-Report-Only: ' . join('; ', $csp));
}
