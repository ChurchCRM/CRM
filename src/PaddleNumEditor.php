<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/paddle-numbers/editor[/{paddleId}]
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iPaddleNumID  = (int) InputUtils::legacyFilterInputArr($_GET, 'PaddleNumID', 'int');
$iFundRaiserID = array_key_exists('iCurrentFundraiser', $_SESSION) ? (int) $_SESSION['iCurrentFundraiser'] : 0;

if ($iFundRaiserID <= 0) {
    RedirectUtils::redirect('fundraiser/');
} elseif ($iPaddleNumID > 0) {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/paddle-numbers/editor/' . $iPaddleNumID);
} else {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/paddle-numbers/editor');
}
