<?php

/*******************************************************************************
*
*  filename    : Reports/PaddleNumDelete.php
*  last change : 2011-04-03
*  description : Deletes a specific donated item
*  copyright   : Copyright 2009 Michael Wilt

******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iDonatedItemID = InputUtils::legacyFilterInput($_GET['DonatedItemID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack'], 'string');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

$sSQL = "DELETE FROM donateditem_di WHERE di_id=$iDonatedItemID AND di_fr_id=$iFundRaiserID";
RunQuery($sSQL);
RedirectUtils::redirect($linkBack);
