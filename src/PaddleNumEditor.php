<?php
// Legacy redirect shim — migrated to /fundraiser/{fundraiserId}/paddle-numbers/editor[/{paddleId}]
require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iPaddleNumID = (int) InputUtils::legacyFilterInputArr($_GET, 'PaddleNumID', 'int');

// Derive the fundraiser ID: from paddle record (most accurate), then legacy query param, then session
$iFundRaiserID = 0;
if ($iPaddleNumID > 0) {
    $rsPN = RunQuery("SELECT pn_fr_ID FROM paddlenum_pn WHERE pn_ID = '$iPaddleNumID'");
    if ($rsPN && mysqli_num_rows($rsPN) > 0) {
        $pnRow = mysqli_fetch_array($rsPN);
        $iFundRaiserID = (int) $pnRow['pn_fr_ID'];
    }
}
if ($iFundRaiserID <= 0) {
    // Also honour the legacy CurrentFundraiser GET param (e.g. PaddleNumEditor.php?CurrentFundraiser=123)
    $iFundRaiserID = (int) ($_GET['CurrentFundraiser'] ?? $_SESSION['iCurrentFundraiser'] ?? 0);
}

if ($iFundRaiserID <= 0) {
    RedirectUtils::redirect('fundraiser/');
} elseif ($iPaddleNumID > 0) {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/paddle-numbers/editor/' . $iPaddleNumID);
} else {
    RedirectUtils::redirect('fundraiser/' . $iFundRaiserID . '/paddle-numbers/editor');
}
