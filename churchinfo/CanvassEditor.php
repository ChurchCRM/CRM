<?php
/*******************************************************************************
 *
 *  filename    : CanvassEditor.php
 *  last change : 2005-02-23
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

require "Include/CanvassUtilities.php";

$iCanvassID = FilterInput($_GET["CanvassID"],'int');
$linkBack = FilterInput($_GET["linkBack"]);
$iFamily = FilterInput($_GET["FamilyID"]);
$iFYID = FilterInput($_GET["FYID"]);

//Get Family name
$sSQL = "SELECT fam_Name FROM family_fam where fam_ID = " . $iFamily;
$rsFamily = RunQuery($sSQL);
extract(mysql_fetch_array($rsFamily));

$fyStr = (1995 + $iFYID) . "/" . (1995 + $iFYID + 1);

$sPageTitle = gettext($fyStr . " Canvass Input for the " . $fam_Name . " family");

//Is this the second pass?
if (isset($_POST["Submit"]))
{
	$iCanvasser = FilterInput ($_POST["Canvasser"]);
	if (! $iCanvasser)
		$iCanvasser = 0;
	$dDate = FilterInput ($_POST["Date"]);
	$tPositive = FilterInput ($_POST["Positive"]);
	$tCritical = FilterInput ($_POST["Critical"]);
	$tInsightful = FilterInput ($_POST["Insightful"]);
	$tFinancial = FilterInput ($_POST["Financial"]);
	$tSuggestion = FilterInput ($_POST["Suggestion"]);
	$bNotInterested = isset($_POST["NotInterested"]);
	$tWhyNotInterested = FilterInput ($_POST["WhyNotInterested"]);

	// New canvas input (add)
	if (strlen($iCanvassID) < 1)
	{
		$sSQL = "INSERT INTO canvassdata_can (can_famID, can_Canvasser, can_FYID, can_date, can_Positive,
		                                      can_Critical, can_Insightful, can_Financial, can_Suggestion,
											  can_NotInterested, can_WhyNotInterested) 
					VALUES (" . $iFamily . "," .
							$iCanvasser . "," .
							$iFYID . "," .
							"\"" . $dDate . "\"," .
							"\"" . $tPositive . "\"," .
							"\"" . $tCritical . "\"," .
							"\"" . $tInsightful . "\"," .
							"\"" . $tFinancial . "\"," .
							"\"" . $tSuggestion . "\"," .
							"\"" . $bNotInterested . "\"," .
							"\"" . $tWhyNotInterested . "\")";
		//Execute the SQL
		RunQuery($sSQL);
		$sSQL = "SELECT MAX(can_ID) AS iCanvassID FROM canvassdata_can";
		$rsLastEntry = RunQuery($sSQL);
		$newRec = mysql_fetch_array($rsLastEntry);
		$iCanvassID = $newRec["iCanvassID"];
	} else {
		$sSQL = "UPDATE canvassdata_can SET can_famID=" . $iFamily . "," .
		                                  "can_Canvasser=" . $iCanvasser . "," .
		                                  "can_FYID=" . $iFYID . "," .
		                                  "can_date=\"" . $dDate . "\"," .
		                                  "can_Positive=\"" . $tPositive . "\"," .
		                                  "can_Critical=\"" . $tCritical . "\"," .
		                                  "can_Insightful=\"" . $tInsightful . "\"," .
		                                  "can_Financial=\"" . $tFinancial . "\"," .
		                                  "can_Suggestion=\"" . $tSuggestion . "\"," .
		                                  "can_NotInterested=\"" . $bNotInterested . "\"," .
		                                  "can_WhyNotInterested=\"" . $tWhyNotInterested . 
										  "\" WHERE can_FamID = " . $iFamily;
		//Execute the SQL
		RunQuery($sSQL);
	}

	if (isset($_POST["Submit"]))
	{
		// Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
		if ($linkBack != "") {
			Redirect($linkBack);
		} else {
			Redirect("CanvassEditor.php?FamilyID=" . $iFamily . "&FYID=".$iFYID . "&CanvassID=" . $iCanvassID . "&linkBack=", $linkBack);
		}
	}

} else {
	$sSQL = "SELECT * FROM canvassdata_can WHERE can_famID = " . $iFamily . " AND can_FYID=" . $iFYID;
	$rsCanvass = RunQuery($sSQL);
	if (mysql_num_rows ($rsCanvass) > 0) {
		extract(mysql_fetch_array($rsCanvass));

		$iCanvassID = $can_ID;
		$iCanvasser = $can_Canvasser;
		$iFYID = $can_FYID;
		$dDate = $can_date;
		$tPositive = $can_Positive;
		$tCritical = $can_Critical;
		$tInsightful = $can_Insightful;
		$tFinancial = $can_Financial;
		$tSuggestion = $can_Suggestion;
		$bNotInterested = $can_NotInterested;
		$tWhyNotInterested = $can_WhyNotInterested;
	} else {
		// Set some default values
		$iCanvasser = $_SESSION['iUserID'];
		$dDate = date("Y-m-d");
	}
}

// Get the lists of canvassers for the drop-down
$rsCanvassers = CanvassGetCanvassers (gettext ("Canvassers"));
$rsBraveCanvassers = CanvassGetCanvassers (gettext ("BraveCanvassers"));

require "Include/Header.php";

?>

<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . "?FamilyID=" . $iFamily . "&FYID=".$iFYID . "&CanvassID=" . $iCanvassID . "&linkBack=" . $linkBack; ?>" name="CanvassEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="Submit">
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
		</td>
	</tr>

	<tr>

		<td>
		<table cellpadding="3">

			<?php
			if (($rsBraveCanvassers <> 0 && mysql_num_rows($rsBraveCanvassers) > 0) || 
			    ($rsCanvasser <> 0 && mysql_num_rows($rsCanvassers) > 0))
			{
				echo "<tr><td class='LabelColumn'>" . gettext("Canvasser:") . "</td>\n";
				echo "<td class='TextColumnWithBottomBorder'>";
				// Display all canvassers
				echo "<select name='Canvasser'><option value=\"0\">None selected</option>";
				while ($aCanvasser = mysql_fetch_array($rsBraveCanvassers))
				{
					echo "<option value=\"" . $aCanvasser["per_ID"] . "\"";
					if ($aCanvasser["per_ID"]==$iCanvasser)
						echo " selected";
					echo ">";
					echo $aCanvasser["per_FirstName"] . " " . $aCanvasser["per_LastName"];
					echo "</option>";
				}
				while ($aCanvasser = mysql_fetch_array($rsCanvassers))
				{
					echo "<option value=\"" . $aCanvasser["per_ID"] . "\"";
					if ($aCanvasser["per_ID"]==$iCanvasser)
						echo " selected";
					echo ">";
					echo $aCanvasser["per_FirstName"] . " " . $aCanvasser["per_LastName"];
					echo "</option>";
				}
				echo "</select></td></tr>";
			}
			?>

			<tr>
				<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font></td>
			</tr>


			<tr>
				<td class="LabelColumn"><?php echo gettext("Positive");?></td>
				<td><textarea name="Positive" rows="3" cols="90"><?php echo $tPositive?></textarea></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Critical");?></td>
				<td><textarea name="Critical" rows="3" cols="90"><?php echo $tCritical?></textarea></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Insightful");?></td>
				<td><textarea name="Insightful" rows="3" cols="90"><?php echo $tInsightful?></textarea></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Financial");?></td>
				<td><textarea name="Financial" rows="3" cols="90"><?php echo $tFinancial?></textarea></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Suggestions");?></td>
				<td><textarea name="Suggestion" rows="3" cols="90"><?php echo $tSuggestion?></textarea></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Not Interested"); ?></td>
				<td class="TextColumn"><input type="checkbox" Name="NotInterested" value="1" <?php if ($bNotInterested) echo " checked"; ?>></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Why Not Interested?");?></td>
				<td><textarea name="WhyNotInterested" rows="1" cols="90"><?php echo $tWhyNotInterested?></textarea></td>
			</tr>

		</table>
		</td>
	</form>
</table>

<?php
require "Include/Footer.php";
?>
