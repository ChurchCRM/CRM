<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/reports/bid-sheets
namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iCurrentFundraiser = (int) InputUtils::legacyFilterInput($_GET['CurrentFundraiser'] ?? 0, 'int');
if ($iCurrentFundraiser <= 0 && array_key_exists('iCurrentFundraiser', $_SESSION)) {
    $iCurrentFundraiser = (int) $_SESSION['iCurrentFundraiser'];
}

if ($iCurrentFundraiser > 0) {
    RedirectUtils::absoluteRedirect(rtrim(\ChurchCRM\dto\SystemURLs::getRootPath(), '/') . '/fundraiser/' . $iCurrentFundraiser . '/reports/bid-sheets');
} else {
    RedirectUtils::redirect('fundraiser/');
}
