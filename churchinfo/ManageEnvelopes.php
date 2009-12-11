<?php
/*******************************************************************************
 *
 *  filename    : ManageEnvelopes.php
 *  last change : 2005-02-21
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2006 Michael Wilt
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

require "Include/EnvelopeFunctions.php";

//Set the page title
$sPageTitle = gettext("Envelope Manager");

// Security: User must have finance permission to use this form
if (!$_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}


$envelopesHash = getEnvelopes();
$familyArray = getFamilyList($sDirRoleHead, $sDirRoleSpouse);

require "Include/Header.php";

$duplicateEnvelopeHash = array();
// Service the action buttons
if (isset($_POST["AssignAllFamilies"])) {
	$newEnvNum = 0;
	$envelopesHash = array(); // zero it out
	foreach ($familyArray as $fam_ID => $fam_Data) {
		$envelopesHash[$fam_ID] = ++$newEnvNum;
	}
} elseif (isset($_POST["PrintReport"])) {
	redirect ("Reports/EnvelopeReport.php");
} elseif (isset($_POST["ZeroAll"])) {
	$envelopesHash = array(); // zero it out
	foreach ($familyArray as $fam_ID => $fam_Data) {
		$envelopesHash[$fam_ID] = 0;
	}
} elseif (isset($_POST["UpdateEnvelopes"])) {
	foreach ($envelopesHash as $fam_ID => $envelope) {
		$key = "EnvelopeID_" . $fam_ID;
		if (isset($_POST[$key])) {
			$newEnvelope = $_POST[$key];
			if ($envelope <> $newEnvelope) {
				$dSQL = "UPDATE family_fam SET fam_Envelope='" . $newEnvelope . "' WHERE fam_ID='" . $fam_ID . "'";
				RunQuery($dSQL);
			}
		}
	}
	$envelopesHash = getEnvelopes();
}?>

<form method="post" action="ManageEnvelopes.php" name="ManageEnvelopes">

<input type="submit" class="icButton" value="<?php echo gettext("Sort by last Name"); ?>" name="SortByName">
<input type="submit" class="icButton" value="<?php echo gettext("Sort by envelope #"); ?>" name="SortByEnvelope">
<input type="submit" class="icButton" value="<?php echo gettext("Print Report"); ?>" 
			 name="PrintReport">

<br><br>
<input type="submit" class="icButton" value="<?php echo gettext("Update Family Records"); ?>" 
			 name="UpdateEnvelopes"> <-- Envelope #'s are not written to DB until this button is pressed

<br><br>

<table border=1>
<tr><td><b>Family Select</b></td><td><b>Envelope</b>
<input type="submit" class="icButton" value="<?php echo gettext("Zero"); ?>" 
			 name="ZeroAll">
<input type="submit" class="icButton" value="<?php echo gettext("Assign"); ?>" 
			 name="AssignAllFamilies">

</td></tr>

<?php
if ($_POST["SortByEnvelope"]) {
	foreach ($envelopesHash as $fam_ID => $envelope) {
		$fam_Data = $familyArray[$fam_ID];
		echo "<tr>";
		echo "<td>" . $fam_Data . "&nbsp;</td>";
		if ($envelope and $duplicateEnvelopeHash and array_key_exists($envelope, $duplicateEnvelopeHash)) {
			$tdTag = "<td bgcolor='red'>";
		} else {
			$duplicateEnvelopeHash[$envelope] = $fam_ID;
			$tdTag = "<td>";
		}
		echo $tdTag;?><class="TextColumn">
		<input type="text" name="EnvelopeID_<?php echo $fam_ID; ?>" value="<?php echo $envelope; ?>" maxlength="10">
		</td></tr>
		<?php
	}
} else {
	foreach ($familyArray as $fam_ID => $fam_Data) {
		$envelope = $envelopesHash[$fam_ID];
		echo "<tr>";
		echo "<td>" . $fam_Data . "&nbsp;</td>";
		if ($envelope and $duplicateEnvelopeHash and array_key_exists($envelope, $duplicateEnvelopeHash)) {
			$tdTag = "<td bgcolor='red'>";
		} else {
			$duplicateEnvelopeHash[$envelope] = $fam_ID;
			$tdTag = "<td>";
		}
		echo $tdTag;?><class="TextColumn">
		<input type="text"  name="EnvelopeID_<?php echo $fam_ID; ?>" value="<?php echo $envelope; ?>"  maxlength="10">
		</td></tr>
		<?php
	}
}

?>	
</table><br>
</form>

<?php

function getEnvelopes() {
	$dSQL = "SELECT fam_ID, fam_Envelope FROM family_fam ORDER by fam_Envelope";
	$dEnvelopes = RunQuery($dSQL);
	while ($aRow = mysql_fetch_array($dEnvelopes)) {
		extract($aRow);
		$envelopes[$fam_ID] = $fam_Envelope;
	}
	return $envelopes;
}


require "Include/Footer.php";
?>
