<?php
/*******************************************************************************
*
*  filename    : LettersAndLabels.php
*  website     : http://www.churchdb.org
*
*  Contributors:
*  2006 Ed Davis
*
*
*  Copyright 2006 Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/


// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/LabelFunctions.php";

// Set the page title and include HTML header
$sPageTitle = gettext("Letters and Mailing Labels");
require "Include/Header.php";

// Is this the second pass?
if (isset($_POST["SubmitNewsLetter"]) || isset($_POST["SubmitConfirmReport"]) || isset($_POST["SubmitConfirmLabels"])) {
   $sLabelFormat = FilterInput($_POST['labeltype']);
   $sFontInfo = $_POST["labelfont"];
   $sFontSize = $_POST["labelfontsize"];
   $sLabelInfo = "&labelfont=".urlencode($sFontInfo)."&labelfontsize=".$sFontSize;

   if (isset($_POST["SubmitNewsLetter"])) {
      Redirect ("Reports/NewsLetterLabels.php?labeltype=" . $sLabelFormat.$sLabelInfo);
   } else if (isset($_POST["SubmitConfirmReport"])) {
      Redirect ("Reports/ConfirmReport.php");
   } else if (isset($_POST["SubmitConfirmLabels"])) {
      Redirect ("Reports/ConfirmLabels.php?labeltype=" . $sLabelFormat.$sLabelInfo);
   }
} else {
   $sLabelFormat = 'Tractor';
}

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">

<table cellpadding="3" align="left">
    <?php 
    LabelSelect("labeltype");
    FontSelect("labelfont"); 
    FontSizeSelect("labelfontsize"); 
    ?>

   <tr>
      <td><input type="submit" class="icButton" name="SubmitNewsLetter" <?php echo 'value="' . gettext("Newsletter labels") . '"'; ?>></td>
      <td><input type="submit" class="icButton" name="SubmitConfirmReport" <?php echo 'value="' . gettext("Confirm data letter") . '"'; ?>></td>
      <td><input type="submit" class="icButton" name="SubmitConfirmLabels" <?php echo 'value="' . gettext("Confirm data labels") . '"'; ?>></td>
      <td><input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';"></td>
   </tr>

</table>

</form>

<?php
require "Include/Footer.php";
?>
