<?php
/*******************************************************************************
 *
 *  filename    : ReminderReport.php
 *  last change : 2003-09-03
 *  description : form to invoke user access report
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// If CSVAdminOnly option is enabled and user is not admin, redirect to the menu.
if (!$_SESSION['bAdmin'] && $bCSVAdminOnly) {
	Redirect("Menu.php");
	exit;
}


// Set the page title and include HTML header
$sPageTitle = gettext("Pledge Reminder Report");
require "Include/Header.php";

// Is this the second pass?
if (isset($_POST["Submit"])) {
	$iFYID = FilterInput($_POST["FYID"], 'int');
   $_SESSION['idefaultFY'] = $iFYID;
   Redirect ("Reports/ReminderReport.php?FYID=" . $_SESSION['idefaultFY']);
} else {
   $iFYID = $_SESSION['idefaultFY'];
}

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">

<table cellpadding="3" align="left">

   <tr>
      <td class="LabelColumn"><?php echo gettext("Fiscal Year:"); ?></td>
      <td class="TextColumnWithBottomBorder">
		<?php PrintFYIDSelect ($iFYID, "FYID") ?>
      </td>
   </tr>

</table>

<table cellpadding="3" align="left">
   <tr>
      <input type="submit" class="icButton" name="Submit" <?php echo 'value="' . gettext("Create Report") . '"'; ?>>
      <input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';">
   </tr>
</table>


</p>
</form>

<?php
require "Include/Footer.php";
?>
