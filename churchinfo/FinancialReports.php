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
if (isset($_POST["SubmitReminder"]) || 
    isset($_POST["SubmitTax"]) || 
	isset($_POST["SubmitPledgeSummary"]) ||
	isset($_POST["VotingMembers"])) {

	$iFYID = FilterInput($_POST["FYID"], 'int');
	$iCalYear = FilterInput($_POST["CalYear"], 'int');
	$iRequireDonationYears = FilterInput($_POST["RequireDonationYears"], 'int');

   $_SESSION['idefaultFY'] = $iFYID;

   if (isset($_POST["SubmitReminder"])) {
      Redirect ("Reports/ReminderReport.php?FYID=" . $iFYID);
   } else if (isset($_POST["SubmitTax"])) {
      Redirect ("Reports/TaxReport.php?Year=" . $iCalYear);
   } else if (isset($_POST["SubmitPledgeSummary"])) {
      Redirect ("Reports/PledgeSummary.php?FYID=" . $iFYID);
   } else if (isset($_POST["VotingMembers"])) {
      Redirect ("Reports/VotingMembers.php?FYID=" . $iFYID . "&RequireDonationYears=" . $iRequireDonationYears);
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
		<?php PrintFYIDSelect ($iFYID, "FYID") ?>
      </td>
   </tr>

   <tr>
      <td class="LabelColumn"><?php echo gettext("Calendar Year:"); ?></td>
		<td class="TextColumn"><input type="text" name="CalYear" id="CalYear" value="<?php echo $iCalYear; ?>"></td>
   </tr>

   <tr>
      <td class="LabelColumn"><?php echo gettext("Voting members must have made a donation within this many years (0 to not require a donation):"); ?></td>
		<td class="TextColumn"><input type="text" name="RequireDonationYears" id="RequireDonationYears" value="<?php echo $iRequireDonationYears; ?>"></td>
   </tr>

   <tr>
   	<?php if ($_SESSION['bFinance']) { ?>
      <td><input type="submit" class="icButton" name="SubmitReminder" <?php echo 'value="' . gettext("Pledge Reminders") . '"'; ?>></td>
      <td><input type="submit" class="icButton" name="SubmitTax" <?php echo 'value="' . gettext("Tax Statements") . '"'; ?>></td>
   	<?php } ?>
      <td><input type="submit" class="icButton" name="SubmitPledgeSummary" <?php echo 'value="' . gettext("Pledge Summary") . '"'; ?>></td>
      <td><input type="submit" class="icButton" name="VotingMembers" <?php echo 'value="' . gettext("Voting Members") . '"'; ?>></td>
      <td><input type="button" class="icButton" name="Cancel" <?php echo 'value="' . gettext("Cancel") . '"'; ?> onclick="javascript:document.location='Menu.php';"></td>
   </tr>

</table>

</form>

<?php
require "Include/Footer.php";
?>
