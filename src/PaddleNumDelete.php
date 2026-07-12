<?php
// Legacy redirect shim — PaddleNumDelete mutation now handled by POST /fundraiser/{id}/paddle-numbers/{paddleId}/delete.
// Redirect to the paddle number list; the delete action requires using the new UI.
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

$iFundRaiserID = array_key_exists('iCurrentFundraiser', $_SESSION) ? (int) $_SESSION['iCurrentFundraiser'] : 0;

if ($iFundRaiserID > 0) {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/paddle-numbers');
} else {
    RedirectUtils::redirect('fundraiser/');
}
