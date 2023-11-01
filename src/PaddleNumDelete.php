<?php

/*******************************************************************************
*
*  filename    : Reports/PaddleNumDelete.php
*  last change : 2009-04-17
*  description : Deletes a specific paddle number holder
*  copyright   : Copyright 2009 Michael Wilt

******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iPaddleNumID = InputUtils::legacyFilterInput($_GET['PaddleNumID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack'], 'string');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

$sSQL = "DELETE FROM paddlenum_pn WHERE pn_id=$iPaddleNumID AND pn_fr_id=$iFundRaiserID";
RunQuery($sSQL);
RedirectUtils::redirect($linkBack);
