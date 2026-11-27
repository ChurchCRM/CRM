<?php

/*
 * Security Headers Configuration
 *
 * This file configures security-related HTTP headers for ChurchCRM:
 *
 * - Content-Security-Policy (CSP): Helps protect against XSS attacks
 *   CSP is enforced by default. Switch to report-only via
 *   Admin > Feature Toggles (bEnforceCSP).
 *   CSP violations are reported to /api/public/csp-report (public endpoint).
 *
 * - X-Frame-Options: Prevents clickjacking attacks
 * - X-Content-Type-Options: Prevents MIME-sniffing attacks
 * - Referrer-Policy: Controls how much referrer information is sent
 *
 * Opt-in for embeddable public pages (e.g. the external calendar route):
 * Set  $allowFraming = true;  in the template BEFORE requiring
 * HeaderNotLoggedIn.php (which in turn requires this file). When set,
 * X-Frame-Options is omitted and frame-ancestors is set to the value of
 * the sCalendarEmbedOrigins system setting (default "*", any origin) so
 * the page can be embedded in a third-party <iframe>. All other
 * clickjacking protections remain in force for every other route.
 */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Utils\RequestUtils;

$telemetryEnabled = TelemetryService::isEnabled();

// PostHog serves assets from the -assets subdomain: https://us-assets.i.posthog.com
$posthogAssetsDomain = $telemetryEnabled
    ? preg_replace('/(.i\.posthog\.com)/', '-assets$1', TelemetryService::POSTHOG_ENDPOINT)
    : '';

$csp = [
    "default-src 'self'",
    "script-src 'self' 'nonce-" . SystemURLs::getCSPNonce() . "' browser-update.org https://www.googletagmanager.com"
        . ($telemetryEnabled ? ' ' . $posthogAssetsDomain : ''),
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self' data: https://secure.gravatar.com https://tile.openstreetmap.org https://*.tile.openstreetmap.org",
    "media-src 'self'",
    "frame-src 'self'",
    "font-src 'self' data: fonts.gstatic.com",
    "connect-src 'self' https://www.google-analytics.com"
        . ($telemetryEnabled ? ' ' . TelemetryService::POSTHOG_ENDPOINT . ' ' . $posthogAssetsDomain : ''),
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
    // Embeddable route: allow framing from the configured origins.
    // sCalendarEmbedOrigins defaults to "*" (any origin); admins can restrict
    // it to a space-separated list of specific origins (e.g. "https://mysite.org").
    // Strip CSP structural characters (;) and HTTP header newlines (\r\n) before
    // injecting into the header — defense-in-depth against directive injection
    // and response splitting even when input comes from an authenticated admin.
    $origins = preg_replace('/[;\r\n]/', '', SystemConfig::getValue('sCalendarEmbedOrigins') ?: '*');
    $csp[] = 'frame-ancestors ' . $origins;
    // An enforcing frame-ancestors directive controls access for embeddable pages.
}

$csp[] = 'report-uri ' . SystemURLs::getRootPath() . '/api/public/csp-report';

header('X-Content-Type-Options: nosniff');
if (RequestUtils::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP can be in report-only mode (violations logged but not blocked) or enforcing mode (violations blocked)
// The mode is controlled by the bEnforceCSP system configuration option
if (SystemConfig::getBooleanValue('bEnforceCSP')) {
    header('Content-Security-Policy: ' . join('; ', $csp));
} else {
    header('Content-Security-Policy-Report-Only: ' . join('; ', $csp));
}
