<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use ChurchCRM\dto\SystemURLs;

$csp = array(
    "default-src 'self'",
    "script-src 'self' 'nonce-".SystemURLs::getCSPNonce()."' sidecar.gitter.im browser-update.org",
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self'",
    "media-src 'self'",
    "frame-src 'self'",
    "font-src 'self' fonts.gstatic.com",
    "connect-src 'self'"
);

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy-Report-Only:".join(";", $csp));
