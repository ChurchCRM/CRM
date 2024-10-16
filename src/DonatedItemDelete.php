<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iDonatedItemID = InputUtils::legacyFilterInput($_GET['DonatedItemID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack'], 'string');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

$sSQL = "DELETE FROM donateditem_di WHERE di_id=$iDonatedItemID AND di_fr_id=$iFundRaiserID";
RunQuery($sSQL);
RedirectUtils::redirect($linkBack);
