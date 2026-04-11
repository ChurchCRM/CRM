<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iDonatedItemID = InputUtils::legacyFilterInput($_GET['DonatedItemID'], 'int');
$linkBack = RedirectUtils::getLinkBackFromRequest('FindFundRaiser.php');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

DonatedItemQuery::create()
    ->filterById((int) $iDonatedItemID)
    ->filterByFrId((int) $iFundRaiserID)
    ->delete();
RedirectUtils::redirect($linkBack);
