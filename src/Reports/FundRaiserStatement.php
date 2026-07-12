<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/reports/statement
namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

$iFundRaiserID = array_key_exists('iCurrentFundraiser', $_SESSION) ? (int) $_SESSION['iCurrentFundraiser'] : 0;

if ($iFundRaiserID > 0) {
    RedirectUtils::absoluteRedirect(rtrim(\ChurchCRM\dto\SystemURLs::getRootPath(), '/') . '/fundraiser/' . $iFundRaiserID . '/reports/statement');
} else {
    RedirectUtils::redirect('fundraiser/');
}
