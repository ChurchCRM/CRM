<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/reports/statement
namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

use ChurchCRM\Utils\RedirectUtils;

// Legacy params: CurrentFundraiser (query string) takes priority, then session
$iFundRaiserID = (int) ($_GET['CurrentFundraiser'] ?? $_SESSION['iCurrentFundraiser'] ?? 0);
// Forward single-paddle parameter if present
$iPaddleNumID  = (int) ($_GET['PaddleNumID'] ?? 0);

if ($iFundRaiserID > 0) {
    $suffix = $iPaddleNumID > 0 ? '?paddleId=' . $iPaddleNumID : '';
    RedirectUtils::absoluteRedirect(rtrim(\ChurchCRM\dto\SystemURLs::getRootPath(), '/') . '/fundraiser/' . $iFundRaiserID . '/reports/statement' . $suffix);
} else {
    RedirectUtils::redirect('fundraiser/');
}
