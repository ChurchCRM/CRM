<?php
/*******************************************************************************
 *
 *  filename    : LettersAndMailingLabels.php
 *  last change : 2003-09-03
 *  description : form to invoke Sunday School reports
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Set the page title and include HTML header
$sPageTitle = gettext("Letters and Mailing Labels");
require "Include/Header.php";

// Is this the second pass?
if (isset($_POST["SubmitNewsLetter"]) || isset($_POST["SubmitConfirmReport"]) || isset($_POST["SubmitConfirmLabels"])) {
   $sLabelFormat = FilterInput($_POST['LabelFormat']);

   if (isset($_POST["SubmitNewsLetter"])) {
      Redirect ("Reports/NewsLetterLabels.php?LabelFormat=" . $sLabelFormat);
   } else if (isset($_POST["SubmitConfirmReport"])) {
      Redirect ("Reports/ConfirmReport.php");
   } else if (isset($_POST["SubmitConfirmLabels"])) {
      Redirect ("Reports/ConfirmLabels.php?LabelFormat=" . $sLabelFormat);
   }
} else {
   $sLabelFormat = 'Tractor';
}

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">

<table cellpadding="3" align="left">
	<tr>
	   <td class="LabelColumn"><?php echo gettext("Label Type:");?></td>
	   <td class="TextColumn">
		   <select name="LabelFormat">
			   <option value="Tractor">Tractor</option>
			   <option value="5160">5160</option>
			   <option value="5161">5161</option>
			   <option value="5162">5162</option>
			   <option value="5163">5163</option>
			   <option value="5164">5164</option>
			   <option value="8600">8600</option>
			   <option value="L7163">L7163</option>
		   </select>
	   </td>

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
