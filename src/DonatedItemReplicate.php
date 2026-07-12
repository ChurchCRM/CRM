<?php
// Legacy redirect shim — DonatedItemReplicate mutation now handled by POST /fundraiser/{id}/donated-items/{itemId}/replicate.
// Redirect to the fundraiser editor; the replicate action requires using the new UI.
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

$iFundRaiserID = array_key_exists('iCurrentFundraiser', $_SESSION) ? (int) $_SESSION['iCurrentFundraiser'] : 0;

if ($iFundRaiserID > 0) {
    RedirectUtils::redirect('fundraiser/editor/' . $iFundRaiserID);
} else {
    RedirectUtils::redirect('fundraiser/');
}
