<?php

/*
 * Security Headers Configuration
 *
 * This file configures security-related HTTP headers for ChurchCRM:
 *
 * - Content-Security-Policy (CSP): enforced to block XSS. The bEnforceCSP
 *   feature toggle (default on) switches it to report-only.
 *   Violations are reported to /api/public/csp-report (public endpoint).
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
    // unsafe-eval: heic2any's libheif worker builds functions at run time to convert iPhone photos.
    "script-src 'self' 'nonce-" . SystemURLs::getCSPNonce() . "' 'unsafe-eval' browser-update.org https://www.googletagmanager.com"
        . ($telemetryEnabled ? ' ' . $posthogAssetsDomain : ''),
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self' data: blob: https://secure.gravatar.com https://tile.openstreetmap.org https://*.tile.openstreetmap.org",
    "media-src 'self'",
    "frame-src 'self'",
    // heic2any converts iPhone photos in a worker it starts from a blob: URL.
    "worker-src 'self' blob:",
    "font-src 'self' data: fonts.gstatic.com",
    "connect-src 'self' blob: https://www.google-analytics.com"
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
}

$csp[] = 'report-uri ' . SystemURLs::getRootPath() . '/api/public/csp-report';

header('X-Content-Type-Options: nosniff');
if (RequestUtils::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}
header('Referrer-Policy: strict-origin-when-cross-origin');
$cspHeader = SystemConfig::getBooleanValue('bEnforceCSP') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
header($cspHeader . ': ' . join('; ', $csp));
