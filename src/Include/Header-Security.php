<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

global $nonce;

$nonce = 'Xiojd98a8jd3s9kFiDi29Uijwdu';

$csp = array(
    "default-src 'self'",
    "script-src 'self' 'nonce-".$nonce."' sidecar.gitter.im",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "font-src 'self' fonts.gstatic.com"
);

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy:".join(";",$csp));