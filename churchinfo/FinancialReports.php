<?php
/*******************************************************************************
 *
 *  filename    : FinancialReports.php
 *  last change : 2003-09-03
 *  description : form to invoke financial reports
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
$sPageTitle = gettext("Financial Reports");
require "Include/Header.php";

// Is this the second pass?
if (isset($_POST["SubmitReminder"]) || isset($_POST["SubmitTax"]) || isset($_POST["SubmitPledgeSummary"])) {
	$iFYID = FilterInput($_POST["FYID"], 'int');
	$iCalYear = FilterInput($_POST["CalYear"], 'int');

   $_SESSION['idefaultFY'] = $iFYID;

   if (isset($_POST["SubmitReminder"])) {
      Redirect ("Reports/ReminderReport.php?FYID=" . $iFYID);
   } else if (isset($_POST["SubmitTax"])) {
      Redirect ("Reports/TaxReport.php?Year=" . $iCalYear);
   } else if (isset($_POST["SubmitPledgeSummary"])) {
      Redirect ("Reports/PledgeSummary.php?FYID=" . $iFYID);
   }
} else {
   $iFYID = $_SESSION['idefaultFY'];
	$iCalYear = date ("Y");
}

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">

<table cellpadding="3" align="left">
   <tr>
      <td class="LabelColumn"><?php echo gettext("Fiscal Year:"); ?></td>
      <td class="TextColumnWithBottomBorder">
	      <select name="FYID">
		      <option value="0"><?php echo gettext("Select Fiscal Year"); ?></option>
		      <option value="1" <?php if ($iFYID == 1) { echo "selected"; } ?>><?php echo gettext("1996/97"); ?></option>
		      <option value="2" <?php if ($iFYID == 2) { echo "selected"; } ?>><?php echo gettext("1997/98"); ?></option>
		      <option value="3" <?php if ($iFYID == 3) { echo "selected"; } ?>><?php echo gettext("1998/99"); ?></option>
		      <option value="4" <?php if ($iFYID == 4) { echo "selected"; } ?>><?php echo gettext("1999/00"); ?></option>
		      <option value="5" <?php if ($iFYID == 5) { echo "selected"; } ?>><?php echo gettext("2000/01"); ?></option>
		      <option value="6" <?php if ($iFYID == 6) { echo "selected"; } ?>><?php echo gettext("2001/02"); ?></option>
		      <option value="7" <?php if ($iFYID == 7) { echo "selected"; } ?>><?php echo gettext("2002/03"); ?></option>
		      <option value="8" <?php if ($iFYID == 8) { echo "selected"; } ?>><?php echo gettext("2003/04"); ?></option>
		      <option value="9" <?php if ($iFYID == 9) { echo "selected"; } ?>><?php echo gettext("2004/05"); ?></option>
		      <option value="10" <?php if ($iFYID == 10) { echo "selected"; } ?>><?php echo gettext("2005/06"); ?></option>
		      <option value="11" <?php if ($iFYID == 11) { echo "selected"; } ?>><?php echo gettext("2006/07"); ?></option>
		      <option value="12" <?php if ($iFYID == 12) { echo "selected"; } ?>><?php echo gettext("2007/08"); ?></option>
		      <option value="13" <?php if ($iFYID == 13) { echo "selected"; } ?>><?php echo gettext("2008/09"); ?></option>
		      <option value="14" <?php if ($iFYID == 14) { echo "selected"; } ?>><?php echo gettext("2009/10"); ?></option>
		      <option value="15" <?php if ($iFYID == 15) { echo "selected"; } ?>><?php echo gettext("2010/11"); ?></option>
		      <option value="16" <?php if ($iFYID == 16) { echo "selected"; } ?>><?php echo gettext("2011/12"); ?></option>
		      <option value="17" <?php if ($iFYID == 17) { echo "selected"; } ?>><?php echo gettext("2012/13"); ?></option>
		      <option value="18" <?php if ($iFYID == 18) { echo "selected"; } ?>><?php echo gettext("2013/14"); ?></option>
	      </select>
      </td>
   </tr>

   <tr>
      <td class="LabelColumn"><?php echo gettext("Calendar Year:"); ?></td>
		<td class="TextColumn"><input type="text" name="CalYear" id="CalYear" value="<?php echo $iCalYear; ?>"></td>
   </tr>

   <tr>
   	<?php if ($_SESSION['bFinance']) { ?>
      <td><input type="submit" class="icButton" name="SubmitReminder" <?php echo 'value="' . gettext("Pledge Reminders") . '"'; ?>></td>
      <td><input type="submit" class="icButton" name="SubmitTax" <?php echo 'value="' . gettext("Tax Statements") . '"'; ?>></td>
   	<?php } ?>
      <td><input type="submit" class="icButton" name="SubmitPledgeSummary" <?php echo 'value="' . gettext("Pledge Summary") . '"'; ?>></td>
      <td><input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';"></td>
   </tr>

</table>

</form>

<?php
require "Include/Footer.php";
?>
