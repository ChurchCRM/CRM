<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iPaddleNumID = InputUtils::legacyFilterInput($_GET['PaddleNumID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack'], 'string');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

$sSQL = "DELETE FROM paddlenum_pn WHERE pn_id=$iPaddleNumID AND pn_fr_id=$iFundRaiserID";
RunQuery($sSQL);
RedirectUtils::redirect($linkBack);
