<?php
/*******************************************************************************
*
*  filename    : Reports/PaddleNumDelete.php
*  last change : 2011-04-03
*  description : Deletes a specific donated item
*  copyright   : Copyright 2009 Michael Wilt
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

$iDonatedItemID = FilterInput($_GET["DonatedItemID"],'int');
$linkBack = FilterInput($_GET["linkBack"],'string');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

$sSQL = "DELETE FROM donateditem_di WHERE di_id=$iDonatedItemID AND di_fr_id=$iFundRaiserID";
RunQuery($sSQL);
redirect ($linkBack);

?>
