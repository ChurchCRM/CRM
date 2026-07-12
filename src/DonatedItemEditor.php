<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/donated-items/editor[/{itemId}]
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iDonatedItemID = (int) InputUtils::filterInt(InputUtils::legacyFilterInputArr($_GET, 'DonatedItemID', 'int'));
$iFundRaiserID  = (int) InputUtils::filterInt(InputUtils::legacyFilterInputArr($_GET, 'CurrentFundraiser'));
if ($iFundRaiserID <= 0 && array_key_exists('iCurrentFundraiser', $_SESSION)) {
    $iFundRaiserID = (int) $_SESSION['iCurrentFundraiser'];
}

if ($iFundRaiserID <= 0) {
    RedirectUtils::redirect('fundraiser/');
} elseif ($iDonatedItemID > 0) {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/donated-items/editor/' . $iDonatedItemID);
} else {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/donated-items/editor');
}
