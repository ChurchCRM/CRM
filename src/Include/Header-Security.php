<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$csp = [
    "default-src 'self'",
    "script-src 'unsafe-eval' 'self' 'nonce-" . SystemURLs::getCSPNonce() . "' browser-update.org",
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self' data:",
    "media-src 'self'",
    "frame-src 'self'",
    "font-src 'self' fonts.gstatic.com",
    "connect-src 'self'",
    'report-uri ' . SystemURLs::getRootPath() . '/api/system/background/csp-report',
];
if (SystemConfig::getBooleanValue('bHSTSEnable')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy-Report-Only:' . join(';', $csp));
