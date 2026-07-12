<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/paddle-numbers
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iFundRaiserID = (int) InputUtils::filterInt($_GET['FundRaiserID'] ?? 0);
if ($iFundRaiserID <= 0 && array_key_exists('iCurrentFundraiser', $_SESSION)) {
    $iFundRaiserID = (int) $_SESSION['iCurrentFundraiser'];
}

if ($iFundRaiserID > 0) {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/paddle-numbers');
} else {
    RedirectUtils::redirect('fundraiser/');
}
