<?php
/*******************************************************************************
*
*  filename    : GroupList.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright 2001, 2002 Deane Barker
*
*
*  Additional Contributors:
*  2006 Ed Davis
*  2016 Charles Crossan
*
*
*  Copyright Contributors
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/
//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';


//Set the page title
$sPageTitle = gettext('Integrity Check Results');
if (!$_SESSION['bFinance'])
{
  Redirect("index.php");
  exit;
}
require 'Include/Header.php'; 
$CRMInstallRoot = __DIR__;
$integrityCheckFile = $CRMInstallRoot."/integrityCheck.json";
$IntegrityCheckDetails = json_decode(file_get_contents($integrityCheckFile));

?>

<div class="box box-body">
  <p><?= gettext("Previous Integrity Check Result:") ?>
<?php 
  if ($IntegrityCheckDetails->status == "failure")
  {
    ?>
    <span style="color:red"><?=  gettext("Failure") ?></span>
  <?php  
  }
  else
  {
    ?>
    <span style="color:green"><?=  gettext("Success") ?></span>
  <?php  
  }
  ?>
  </p>
  <?php  
  if (isset($IntegrityCheckDetails->message))
  {
    ?>
  <p><?= gettext("Details:")?> <?=  $IntegrityCheckDetails->message ?></p>
    
  <?php
  }
  if(count($IntegrityCheckDetails->files) > 0 )
  {
    ?>
  <p><?= gettext("Files failing integrity check:") ?>
  <ul>
    <?php
    foreach ($IntegrityCheckDetails->files as $file)
    {
      ?>
    <li><?= $file ?></li>
      <?php
    }
    ?>
  </ul>
  <?php
  }
?>
</div>

<?php
require 'Include/Footer.php';
?>
