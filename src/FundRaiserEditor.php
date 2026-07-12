<?php
// Legacy redirect shim — migrated to /fundraiser/editor[/{fundraiserId}]
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iFundRaiserID = (int) InputUtils::legacyFilterInput($_GET['FundRaiserID'] ?? 0, 'int');

if ($iFundRaiserID > 0) {
    RedirectUtils::redirect('fundraiser/editor/' . $iFundRaiserID);
} else {
    RedirectUtils::redirect('fundraiser/editor');
}
