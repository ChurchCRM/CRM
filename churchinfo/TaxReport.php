<?php
/*******************************************************************************
 *
 *  filename    : TaxReport.php
 *  last change : 2003-09-03
 *  description : form to invoke tax letter generation
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
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
$sPageTitle = gettext("Tax Report");
require "Include/Header.php";

// Is this the second pass?
if (isset($_POST["Submit"])) {
	$iYear = FilterInput($_POST["Year"], 'int');
   Redirect ("Reports/TaxReport.php?Year=" . $iYear);
} else {
   $iYear = date ("Y") - 1;
}

?>

<form method="post" action="TaxReport.php">

<table cellpadding="3" align="left">

   <tr>
      <td class="LabelColumn"><?= gettext("Calendar Year:") ?></td>
		<td class="TextColumn"><input type="text" name="Year" id="Year" value="<?= $iYear ?>"></td>
   </tr>

</table>

<table cellpadding="3" align="left">
   <tr>
      <input type="submit" class="btn" name="Submit" value="<?= gettext("Create Report") ?>">
      <input type="button" class="btn" name="Cancel" value="<?= gettext("Cancel") ?>" onclick="javascript:document.location='Menu.php';">
   </tr>
</table>


</p>
</form>

<?php require "Include/Footer.php" ?>
