<?php
/*******************************************************************************
*
*  filename    : Reports/PaddleNumDelete.php
*  last change : 2009-04-17
*  description : Deletes a specific paddle number holder
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

$iPaddleNumID = FilterInput($_GET["PaddleNumID"],'int');
$linkBack = FilterInput($_GET["linkBack"],'string');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

$sSQL = "DELETE FROM paddlenum_pn WHERE pn_id=$iPaddleNumID AND pn_fr_id=$iFundRaiserID";
RunQuery($sSQL);
redirect ($linkBack);

?>
