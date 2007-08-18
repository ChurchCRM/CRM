<?php
/*******************************************************************************
 *
 *  filename    : FamilyEditor.php
 *  last change : 2003-01-04
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
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
//require "Include/GeoCoder.php";

//Set the page title
$sPageTitle = gettext("Family Editor");

//Get the FamilyID from the querystring
$iFamilyID = FilterInput($_GET["FamilyID"],'int');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (strlen($iFamilyID) > 0)
{
	if (!($_SESSION['bEditRecords'] || ($_SESSION['bEditSelf'] && ($iFamilyID == $_SESSION['iFamID']))))
	{
		Redirect("Menu.php");
		exit;
	}

	$sSQL = "SELECT fam_ID FROM family_fam WHERE fam_ID = " . $iFamilyID;
	if (mysql_num_rows(RunQuery($sSQL)) == 0)
	{
		Redirect("Menu.php");
		exit;
	}
}
elseif (!$_SESSION['bAddRecords'])
{
		Redirect("Menu.php");
		exit;
}

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
if ($editorMode == 0) $sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.
$rsFunds = RunQuery($sSQL);

// Get the lists of canvassers
$rsCanvassers = CanvassGetCanvassers (gettext ("Canvassers"));
$rsBraveCanvassers = CanvassGetCanvassers (gettext ("BraveCanvassers"));

// Get the list of custom person fields
$sSQL = "SELECT family_custom_master.* FROM family_custom_master ORDER BY fam_custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

// Get Field Security List Matrix
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence";
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsSecurityGrp))
{
	extract ($aRow);
	$aSecurityType[$lst_OptionID] = $lst_OptionName;
}


//Is this the second pass?
if (isset($_POST["FamilySubmit"]) || isset($_POST["FamilySubmitAndAdd"]))
{
	//Assign everything locally
	$sName = FilterInput($_POST["Name"]);
	// Strip commas out of address fields because they are problematic when
	// exporting addresses to CSV file
	$sAddress1 = str_replace(',','',FilterInput($_POST["Address1"]));
	$sAddress2 = str_replace(',','',FilterInput($_POST["Address2"]));
	$sCity = FilterInput($_POST["City"]);
	$sZip = FilterInput($_POST["Zip"]);
	$sCountry = FilterInput($_POST["Country"]);
	$iFamilyMemberRows = FilterInput($_POST["FamCount"]);

	if ($sCountry == "United States" || $sCountry == "Canada")
		$sState = FilterInput($_POST["State"]);
	else
		$sState = FilterInput($_POST["StateTextbox"]);

	$sHomePhone = FilterInput($_POST["HomePhone"]);
	$sWorkPhone = FilterInput($_POST["WorkPhone"]);
	$sCellPhone = FilterInput($_POST["CellPhone"]);
	$sEmail = FilterInput($_POST["Email"]);
	$bSendNewsLetter = isset($_POST["SendNewsLetter"]);

	$nLatitude = FilterInput($_POST["Latitude"]);
	$nLongitude = FilterInput($_POST["Longitude"]);

	if ($bHaveXML && ($sCountry == "United States" || $sCountry == "Canada")) {
	// Try to get Lat/Lon based on the address
		$myAddressLatLon = new AddressLatLon;
		$myAddressLatLon->SetAddress ($sAddress1, $sCity, $sState, $sZip);
		$ret = $myAddressLatLon->Lookup ();
		if ($ret == 0) {
			$nLatitude = $myAddressLatLon->GetLat ();
			$nLongitude = $myAddressLatLon->GetLon ();
		} else {
			$nLatitude="NULL";
			$nLongitude="NULL";
		}
	}

	if(is_numeric($nLatitude))
		$nLatitude = "'" . $nLatitude . "'";
	else
		$nLatitude = "NULL";

	if(is_numeric($nLongitude))
		$nLongitude= "'" . $nLongitude . "'";
	else
		$nLongitude="NULL";


	$nEnvelope = FilterInput($_POST["Envelope"]);
	
	if(is_numeric($nEnvelope)){ // Only integers are allowed as Envelope Numbers
		if(intval($nEnvelope)==floatval($nEnvelope))
			$nEnvelope= "'" . intval($nEnvelope) . "'";
		else
			$nEnvelope= "'0'";
	} else
		$nEnvelope= "'0'";


	if ($_SESSION['bCanvasser']) { // Only take modifications to this field if the current user is a canvasser
		$bOkToCanvass = isset($_POST["OkToCanvass"]);
		$iCanvasser = FilterInput($_POST["Canvasser"]);
		if (! $iCanvasser)
			$iCanvasser = FilterInput($_POST["BraveCanvasser"]);
		if (! $iCanvasser)
			$iCanvasser = 0;
	}

	$iPropertyID = FilterInput($_POST["PropertyID"],'int');
	$dWeddingDate = FilterInput($_POST["WeddingDate"]);

	$bNoFormat_HomePhone = isset($_POST["NoFormat_HomePhone"]);
	$bNoFormat_WorkPhone = isset($_POST["NoFormat_WorkPhone"]);
	$bNoFormat_CellPhone = isset($_POST["NoFormat_CellPhone"]);

	//Loop through the Family Member 'quick entry' form fields
	for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++)
	{
		// Assign everything to arrays
		$aFirstNames[$iCount] = FilterInput($_POST["FirstName" . $iCount]);
		$aMiddleNames[$iCount] = FilterInput($_POST["MiddleName" . $iCount]);
		$aLastNames[$iCount] = FilterInput($_POST["LastName" . $iCount]);
		$aRoles[$iCount] = FilterInput($_POST["Role" . $iCount],'int');
		$aGenders[$iCount] = FilterInput($_POST["Gender" . $iCount],'int');
		$aBirthDays[$iCount] = FilterInput($_POST["BirthDay" . $iCount],'int');
		$aBirthMonths[$iCount] = FilterInput($_POST["BirthMonth" . $iCount],'int');
		$aBirthYears[$iCount] = FilterInput($_POST["BirthYear" . $iCount],'int');
		$aClassification[$iCount] = FilterInput($_POST["Classification" . $iCount],'int');
		$aPersonIDs[$iCount] = FilterInput($_POST["PersonID" . $iCount],'int');
		$aUpdateBirthYear[$iCount] = FilterInput($_POST["UpdateBirthYear"], 'int');

		// Make sure first names were entered if editing existing family
		if (strlen($iFamilyID) > 0)
		{
			if (strlen($aFirstNames[$iCount]) == 0)
			{
				$aFirstNameError[$iCount] = gettext("First name must be entered");
				$bErrorFlag = True;
			}
		}

		// Validate any family member birthdays
		if ((strlen($aFirstNames[$iCount]) > 0) && (strlen($aBirthYears[$iCount]) > 0)) 
		{
			if (($aBirthYears[$iCount] > 2155) || ($aBirthYears[$iCount] < 1901))
			{
				$aBirthDateError[$iCount] = gettext("Invalid Year: allowable values are 1901 to 2155");
				$bErrorFlag = True;
			}
			elseif ($aBirthMonths[$iCount] > 0 && $aBirthDays[$iCount] > 0)
			{
				if (!checkdate($aBirthMonths[$iCount],$aBirthDays[$iCount],$aBirthYears[$iCount]))
				{
					$aBirthDateError[$iCount] = gettext("Invalid Birth Date.");
					$bErrorFlag = True;
				}
			}
		}
	}

	//Did they enter a name?
	if (strlen($sName) < 1)
	{
		$sNameError = gettext("You must enter a Name.");
		$bErrorFlag = True;

	}

	// Validate Wedding Date if one was entered
	if ((strlen($dWeddingDate) > 0) && ($dWeddingDate != "0000-00-00")) {
		$dateString = parseAndValidateDate($dWeddingDate, $locale = "US", $pasfut = "past");
		if ( $dateString === FALSE ) {
			$sWeddingDateError = "<span style=\"color: red; \">" 
								. gettext("Not a valid Wedding Date") . "</span>";
			$bErrorFlag = true;
		} else {
			$dWeddingDate = "'" . $dateString . "'";
		}
	} else {
		$dWeddingDate = "NULL";
	}

	// Validate all the custom fields
	while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH) )
	{
		extract($rowCustomField);

		$currentFieldData = FilterInput($_POST[$fam_custom_Field]);

		$bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $fam_custom_Field, $aCustomErrors);

		// assign processed value locally to $aPersonProps so we can use it to generate the form later
		$aCustomData[$fam_custom_Field] = $currentFieldData;
	}


	//If no errors, then let's update...
	if (!$bErrorFlag)
	{
		// Format the phone numbers before we store them
		if (!$bNoFormat_HomePhone) $sHomePhone = CollapsePhoneNumber($sHomePhone,$sCountry);
		if (!$bNoFormat_WorkPhone) $sWorkPhone = CollapsePhoneNumber($sWorkPhone,$sCountry);
		if (!$bNoFormat_CellPhone) $sCellPhone = CollapsePhoneNumber($sCellPhone,$sCountry);

		//Write the base SQL depending on the Action
		if ($bSendNewsLetter)
			$bSendNewsLetterString = "'TRUE'";
		else
			$bSendNewsLetterString = "'FALSE'";
		if ($bOkToCanvass)
			$bOkToCanvassString = "'TRUE'";
		else
			$bOkToCanvassString = "'FALSE'";
		if (strlen($iFamilyID) < 1)
		{
			$sSQL = "INSERT INTO family_fam (
						fam_Name, 
						fam_Address1, 
						fam_Address2, 
						fam_City, 
						fam_State, 
						fam_Zip, 
						fam_Country, 
						fam_HomePhone, 
						fam_WorkPhone, 
						fam_CellPhone, 
						fam_Email, 
						fam_WeddingDate, 
						fam_DateEntered, 
						fam_EnteredBy, 
						fam_SendNewsLetter,
						fam_OkToCanvass,
						fam_Canvasser,
						fam_Latitude,
						fam_Longitude,
						fam_Envelope)
					VALUES ('"							. 
						$sName					. "','" . 
						$sAddress1				. "','" . 
						$sAddress2				. "','" . 
						$sCity					. "','" . 
						$sState					. "','" . 
						$sZip					. "','" . 
						$sCountry				. "','" . 
						$sHomePhone				. "','" . 
						$sWorkPhone				. "','" . 
						$sCellPhone				. "','" . 
						$sEmail					. "'," . 
						$dWeddingDate			. ",'" . 
						date("YmdHis")			. "'," . 
						$_SESSION['iUserID']	. "," . 
						$bSendNewsLetterString	. "," . 
						$bOkToCanvassString		. ",'" .
						$iCanvasser				. "'," .
						$nLatitude				. "," .
						$nLongitude				. "," .
						$nEnvelope              . ")";
			$bGetKeyBack = true;
		}
		else
		{
			$sSQL = "UPDATE family_fam SET fam_Name='" . $sName . "'," .
						"fam_Address1='" . $sAddress1 . "'," .
						"fam_Address2='" . $sAddress2 . "'," .
						"fam_City='" . $sCity . "'," .
						"fam_State='" . $sState . "'," .
						"fam_Zip='" . $sZip . "'," .
						"fam_Latitude=" . $nLatitude . "," .
						"fam_Longitude=" . $nLongitude . "," .
						"fam_Country='" . $sCountry . "'," .
						"fam_HomePhone='" . $sHomePhone . "'," .
						"fam_WorkPhone='" . $sWorkPhone . "'," .
						"fam_CellPhone='" . $sCellPhone . "'," .
						"fam_Email='" . $sEmail . "'," .
						"fam_WeddingDate=" . $dWeddingDate . "," .
						"fam_Envelope=" . $nEnvelope . "," .
						"fam_DateLastEdited='" . date("YmdHis") . "'," .
						"fam_EditedBy = " . $_SESSION['iUserID'] . "," .
						"fam_SendNewsLetter = " . $bSendNewsLetterString;
			if ($_SESSION['bCanvasser'])
				$sSQL .= ", fam_OkToCanvass = " . $bOkToCanvassString . 
									", fam_Canvasser = '" . $iCanvasser . "'";
				$sSQL .= " WHERE fam_ID = " . $iFamilyID;
			$bGetKeyBack = false;
		}

		//Execute the SQL
		RunQuery($sSQL);

		//If the user added a new record, we need to key back to the route to the FamilyView page
		if ($bGetKeyBack)
		{
			//Get the key back
			$sSQL = "SELECT MAX(fam_ID) AS iFamilyID FROM family_fam";
			$rsLastEntry = RunQuery($sSQL);
			extract(mysql_fetch_array($rsLastEntry));

			$sSQL = "INSERT INTO `family_custom` (`fam_ID`) VALUES ('" . $iFamilyID . "')";
			RunQuery($sSQL);
			
			// Add property if assigned
			if ($iPropertyID)
			{
				$sSQL = "INSERT INTO record2property_r2p (r2p_pro_ID, r2p_record_ID) VALUES ($iPropertyID, $iFamilyID)";
				RunQuery($sSQL);
			}

			//Run through the family member arrays...
			for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++)
			{
				if (strlen($aFirstNames[$iCount]) > 0)
				{
					if (strlen($aBirthYears[$iCount]) < 4)
					{
						$aBirthYears[$iCount] = "NULL";
					}

					//If no last name is entered for a member, use the family name.
					if(strlen($aLastNames[$iCount]) && $aLastNames[$iCount] != $sName)
					{
						$sLastNameToEnter = $aLastNames[$iCount];
					}
					else
					{
						$sLastNameToEnter = $sName;
					}

					RunQuery("LOCK TABLES person_per WRITE, person_custom WRITE");
					$sSQL = "INSERT INTO person_per (
								per_FirstName,
								per_MiddleName,
								per_LastName,
								per_fam_ID,
								per_fmr_ID,
								per_DateEntered,
								per_EnteredBy,
								per_Gender,
								per_BirthDay,
								per_BirthMonth,
								per_BirthYear,
								per_cls_ID)
							VALUES (
								'$aFirstNames[$iCount]',
								'$aMiddleNames[$iCount]',
								'$sLastNameToEnter',
								$iFamilyID,
								$aRoles[$iCount],
								'" . date("YmdHis") . "',
								" . $_SESSION['iUserID'] . ",
								$aGenders[$iCount],
								$aBirthDays[$iCount],
								$aBirthMonths[$iCount],
								$aBirthYears[$iCount],
								$aClassification[$iCount])";
					RunQuery($sSQL);
					$sSQL = "INSERT INTO person_custom (per_ID) VALUES (" 
								. mysql_insert_id() . ")";
					RunQuery($sSQL);
					RunQuery("UNLOCK TABLES");
				}
			}
		} else {
			for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++)
			{
				if (strlen($aFirstNames[$iCount]) > 0)
				{
					if (strlen($aBirthYears[$iCount]) < 4)
					{
						$aBirthYears[$iCount] = "NULL";
					}

					//If no last name is entered for a member, use the family name.
					if(strlen($aLastNames[$iCount]) && $aLastNames[$iCount] != $sName)
					{
						$sLastNameToEnter = $aLastNames[$iCount];
					}
					else
					{
						$sLastNameToEnter = $sName;
					}
					$sUpdateBirthYear = ($aUpdateBirthYear[$iCount] & 1) ? "per_BirthYear=" . $aBirthYears[$iCount]. ", " : "";
					//RunQuery("LOCK TABLES person_per WRITE, person_custom WRITE");
					$sSQL = "UPDATE person_per SET per_FirstName='" . $aFirstNames[$iCount] . "', per_MiddleName='" . $aMiddleNames[$iCount] . "',per_LastName='" . $aLastNames[$iCount] . "',per_Gender='" . $aGenders[$iCount] . "',per_fmr_ID='" . $aRoles[$iCount] . "',per_BirthMonth='" . $aBirthMonths[$iCount] . "',per_BirthDay='" . $aBirthDays[$iCount] . "', " . $sBirthYearScript . "per_cls_ID='" . $aClassification[$iCount] . "' WHERE per_ID=" . $aPersonIDs[$iCount];
					RunQuery($sSQL);
					//RunQuery("UNLOCK TABLES");
				}
			}
		}
		
		// Update the custom person fields.
		if ($numCustomFields > 0)
		{
			$sSQL = "REPLACE INTO family_custom SET ";
			mysql_data_seek($rsCustomFields,0);

			while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH) )
			{
				extract($rowCustomField);
				if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$fam_custom_FieldSec]]))
				{
					$currentFieldData = trim($aCustomData[$fam_custom_Field]);

					sqlCustomField($sSQL, $type_ID, $currentFieldData, $fam_custom_Field, $sPhoneCountry);
				}
			}

			// chop off the last 2 characters (comma and space) added in the last while loop iteration.
			$sSQL = substr($sSQL,0,-2);

			$sSQL .= ", fam_ID = " . $iFamilyID;

			//Execute the SQL
			RunQuery($sSQL);
		}


		//Which submit button did they press?
		if (isset($_POST["FamilySubmit"]))
		{
			//Send to the view of this person
			Redirect("FamilyView.php?FamilyID=" . $iFamilyID);
		} else {
			//Reload to editor to add another record
			Redirect("FamilyEditor.php");
		}
	}
}
else
{
	//FirstPass
	//Are we editing or adding?
	if (strlen($iFamilyID) > 0)
	{
		//Editing....
		//Get the information on this family
		$sSQL = "SELECT * FROM family_fam WHERE fam_ID = " . $iFamilyID;
		$rsFamily = RunQuery($sSQL);
		extract(mysql_fetch_array($rsFamily));
		
		$iFamilyID = $fam_ID;
		$sName = $fam_Name;
		$sAddress1 = $fam_Address1;
		$sAddress2 = $fam_Address2;
		$sCity = $fam_City;
		$sState = $fam_State;
		$sZip	= $fam_Zip;
		$sCountry = $fam_Country;
		$sHomePhone = $fam_HomePhone;
		$sWorkPhone = $fam_WorkPhone;
		$sCellPhone = $fam_CellPhone;
		$sEmail = $fam_Email;
		$bSendNewsLetter = ($fam_SendNewsLetter == 'TRUE');
		$bOkToCanvass = ($fam_OkToCanvass == 'TRUE');
		$iCanvasser = $fam_Canvasser;
		$dWeddingDate = $fam_WeddingDate;
		$nLatitude = $fam_Latitude;
		$nLongitude = $fam_Longitude;

		// Expand the phone number
		$sHomePhone = ExpandPhoneNumber($sHomePhone,$sCountry,$bNoFormat_HomePhone);
		$sWorkPhone = ExpandPhoneNumber($sWorkPhone,$sCountry,$bNoFormat_WorkPhone);
		$sCellPhone = ExpandPhoneNumber($sCellPhone,$sCountry,$bNoFormat_CellPhone);

		$sSQL = "SELECT * FROM family_custom WHERE fam_ID = " . $iFamilyID;
		$rsCustomData = RunQuery($sSQL);
		$aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);
 		
		$sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID =" . $iFamilyID . " ORDER BY per_fmr_ID";
		$rsMembers = RunQuery($sSQL);
		$iCount = 0;
		$iFamilyMemberRows = 0;
		while ($aRow = mysql_fetch_array($rsMembers))
		{
			extract($aRow);
			$iCount++;
			$iFamilyMemberRows++;
			$aFirstNames[$iCount] = $per_FirstName;
			$aMiddleNames[$iCount] = $per_MiddleName;
			$aLastNames[$iCount] = $per_LastName;		
			$aGenders[$iCount] = $per_Gender;
			$aRoles[$iCount] = $per_fmr_ID;
			$aBirthMonths[$iCount] = $per_BirthMonth;
			$aBirthDays[$iCount] = $per_BirthDay;
			if ($per_BirthYear > 0)
				$aBirthYears[$iCount] = $per_BirthYear;
			else
				$aBirthYears[$iCount] = "";
			$aClassification[$iCount] = $per_cls_ID;
			$aPersonIDs[$iCount] = $per_ID;
			$aPerFlag[$iCount] = $per_Flags;
		}
	}
	else
	{
		//Adding....
		//Set defaults
		$sCity = $sDefaultCity;
		$sCountry = $sDefaultCountry;
		$sState = $sDefaultState;
		$iClassification = "0";
		$iFamilyMemberRows = 6;
		$bOkToCanvass = 1;
	}
}

require "Include/Header.php";

?>

<form method="post" action="FamilyEditor.php?FamilyID=<?php echo $iFamilyID ?>">
<input type="hidden" Name="sAction" value="<?php echo $sAction; ?>">
<input type="hidden" Name="iFamilyID" value="<?php echo $iFamilyID; ?>">
<input type="hidden" name="FamCount" value="<?php echo $iFamilyMemberRows; ?>">

<table cellpadding="3" align="center">

	<tr>
		<td colspan="2" align="center">
			<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> Name="FamilySubmit">
			<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Save and Add") . "\" name=\"FamilySubmitAndAdd\">"; } 
			echo "<input type=\"button\" class=\"icButton\" value=\"" . gettext("Cancel") . "\"Name=\"FamilyCancel\"";
			if (strlen($iFamilyID) > 0)
				echo "\"onclick=\"javascript:document.location='FamilyView.php?FamilyID=$iFamilyID';\">";
			else
				echo "\"onclick=\"javascript:document.location='SelectList.php';\">";
			if ( $bErrorFlag ) echo "<br><br><span class=\"LargeText\" style=\"color: red;\">Invalid fields or selections. Changes not saved! Please correct and try again!</span><br><br>"; ?>
		</td>
	</tr>

	<tr>
	<td>
	<table cellpadding="3"><tr>
		<td class="LabelColumn"><?php echo gettext("Family Name:"); ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="Name" id="FamilyName" value="<?php echo htmlentities(stripslashes($sName),ENT_NOQUOTES, "UTF-8"); ?>" maxlength="48"><font color="red"><?php echo $sNameError; ?></font></td>
	</tr>
	
	<tr>
		<td>&nbsp;</td><td>&nbsp;</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Address1:"); ?></td>
		<td class="TextColumn"><input type="text" Name="Address1" value="<?php echo htmlentities(stripslashes($sAddress1),ENT_NOQUOTES, "UTF-8"); ?>" size="50" maxlength="250"></td>
	</tr>
	
	<tr>
		<td class="LabelColumn"><?php echo gettext("Address2:"); ?></td>
		<td class="TextColumn"><input type="text" Name="Address2" value="<?php echo htmlentities(stripslashes($sAddress2),ENT_NOQUOTES, "UTF-8"); ?>" size="50" maxlength="250"></td>
	</tr>
	
	<tr>
		<td class="LabelColumn"><?php echo gettext("City:"); ?></td>
		<td class="TextColumn"><input type="text" Name="City" value="<?php echo htmlentities(stripslashes($sCity),ENT_NOQUOTES, "UTF-8"); ?>" maxlength="50"></td>
	</tr>
	
	<tr>
		<td class="LabelColumn"><?php echo gettext("State:"); ?></td>
		<td class="TextColumn">
			<?php require "Include/StateDropDown.php"; ?>
			OR
			<input type="text" name="StateTextbox" value="<?php if ($sCountry != "United States" && $sCountry != "Canada") echo htmlentities(stripslashes($sState),ENT_NOQUOTES, "UTF-8"); ?>" size="20" maxlength="30">
			<BR><?php echo gettext("(Use the textbox for countries other than US and Canada)"); ?>
		</td>
	</tr>
	
	<tr>
		<td class="LabelColumn">
			<?php
			if($sCountry == "Canada")
			  echo gettext("Postal Code:"); 
			else
			  echo gettext("Zip:");
			?>
		</td>
		<td class="TextColumn">
			<input type="text" Name="Zip" value="<?php echo htmlentities(stripslashes($sZip),ENT_NOQUOTES, "UTF-8"); ?>" maxlength="10" size="8">
		</td>
		
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Country:"); ?></td>
		<td class="TextColumnWithBottomBorder">
			<?php require "Include/CountryDropDown.php" ?>
		</td>
	</tr>

	<tr>
		<td>&nbsp;</td><td>&nbsp;</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Home Phone:"); ?></td>
		<td class="TextColumn">
			<input type="text" Name="HomePhone" value="<?php echo htmlentities(stripslashes($sHomePhone)); ?>" size="30" maxlength="30">
			<input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) echo " checked";?>><?php echo gettext("Do not auto-format"); ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Work Phone:"); ?></td>
		<td class="TextColumn">
			<input type="text" name="WorkPhone" value="<?php echo htmlentities(stripslashes($sWorkPhone)); ?>" size="30" maxlength="30">
			<input type="checkbox" name="NoFormat_WorkPhone" value="1" <?php if ($bNoFormat_WorkPhone) echo " checked";?>><?php echo gettext("Do not auto-format"); ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Mobile Phone:"); ?></td>
		<td class="TextColumn">
			<input type="text" name="CellPhone" value="<?php echo htmlentities(stripslashes($sCellPhone)); ?>" size="30" maxlength="30">
			<input type="checkbox" name="NoFormat_CellPhone" value="1" <?php if ($bNoFormat_CellPhone) echo " checked";?>><?php echo gettext("Do not auto-format"); ?>
		</td>
	</tr>

	<tr>
		<td class="LabelColumn"><?php echo gettext("Email:"); ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="Email" value="<?php echo htmlentities(stripslashes($sEmail)); ?>" size="30" maxlength="100"></td>
	</tr>
<?php if (!$bHideFamilyNewsletter) { /* Newsletter can be hidden - General Settings */ ?>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Send Newsletter:"); ?></td>
		<td class="TextColumn"><input type="checkbox" Name="SendNewsLetter" value="1" <?php if ($bSendNewsLetter) echo " checked"; ?>></td>
	</tr>
<?php } ?>	
	<tr><?php
		if ($_SESSION['bCanvasser']) { // Only show this field if the current user is a canvasser
		echo "<td class='LabelColumn'>" . gettext("Ok To Canvass:") . "</td>\n";
		echo "<td class='TextColumn'><input type=\"checkbox\" Name=\"OkToCanvass\" value=\"1\"";
		if ($bOkToCanvass) echo " checked";
		}?>></td>
	</tr>

	<?php
		if ($rsCanvassers <> 0 && mysql_num_rows($rsCanvassers) > 0) 
		{
			echo "<tr><td class='LabelColumn'>" . gettext("Assign a Canvasser:") . "</td>\n";
			echo "<td class='TextColumnWithBottomBorder'>";
			// Display all canvassers
			echo "<select name='Canvasser'><option value=\"0\">None selected</option>";
			while ($aCanvasser = mysql_fetch_array($rsCanvassers))
			{
				echo "<option value=\"" . $aCanvasser["per_ID"] . "\"";
				if ($aCanvasser["per_ID"]==$fam_Canvasser)
					echo " selected";
				echo ">";
				echo $aCanvasser["per_FirstName"] . " " . $aCanvasser["per_LastName"];
				echo "</option>";
			}
			echo "</select></td></tr>";
		}

		if ($rsBraveCanvassers <> 0 && mysql_num_rows($rsBraveCanvassers) > 0) 
		{
			echo "<tr><td class='LabelColumn'>" . gettext("Assign a Brave Canvasser:") . "</td>\n";
			echo "<td class='TextColumnWithBottomBorder'>";
			// Display all canvassers
			echo "<select name='BraveCanvasser'><option value=\"0\">None selected</option>";
			while ($aBraveCanvasser = mysql_fetch_array($rsBraveCanvassers))
			{
				echo "<option value=\"" . $aBraveCanvasser["per_ID"] . "\"";
				if ($aBraveCanvasser["per_ID"]==$fam_Canvasser)
					echo " selected";
				echo ">";
				echo $aBraveCanvasser["per_FirstName"] . " " . $aBraveCanvasser["per_LastName"];
				echo "</option>";
			}
			echo "</select></td></tr>";
		}
		?>

<?php if ($bUseDonationEnvelopes) { /* Donation envelopes can be hidden - General Settings */ ?>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Envelope number:"); ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="Envelope"
			<?php if($fam_Envelope) echo " value=\"" . $fam_Envelope; 
			?>" size="30" maxlength="50"></td>
	</tr>
<?php } ?>	

	<?php
	//"Assign a Property" block
	// Adding a new family?
	if (!$iFamilyID) 
	{
		// Yes. Get all the family properties
		$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'f' ORDER BY pro_Name";
		$rsProperties = RunQuery($sSQL);
		// Are there any family properties defined?
		if (mysql_num_rows($rsProperties) > 0) 
		{
			// Yes. Display "Assign a Property" block
			echo "<tr><td class='LabelColumn'>" . gettext("Assign a Property:") . "</td>\n";
			echo "<td class='TextColumnWithBottomBorder'>";
			// Display all family properties
			echo "<select name='PropertyID'><option value='0'>None selected</option>";
			while ($aRow = mysql_fetch_array($rsProperties))
			{
				extract($aRow);
				echo "<option value=\"" . $pro_ID . "\">" . $pro_Name . "</option>";
			}
			echo "</select></td></tr>";
		}
	}
	?>
	
	<tr>
		<td>&nbsp;</td><td>&nbsp;</td>
	</tr>
<?php if (!$bHideWeddingDate) { /* Wedding Date can be hidden - General Settings */ ?>
	<tr>
		<?php if ($dWeddingDate == "0000-00-00" || $dWeddingDate == "NULL") $dWeddingDate = ""; ?>
		<td class="LabelColumn"><?php echo gettext("Wedding Date:"); ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="WeddingDate" value="<?php echo $dWeddingDate; ?>" maxlength="12" id="sel1" size="15">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif">&nbsp;<span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo "<BR>" . $sWeddingDateError ?></font></td>
	</tr>
<?php } /* Wedding date can be hidden - General Settings */ ?>

<?php
	if (!$bHideLatLon) { /* Lat/Lon can be hidden - General Settings */ 
		if (!$bHaveXML) { // No point entering if values will just be overwritten
?><tr>
		<td class="LabelColumn"><?php echo gettext("Latitude:"); ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="Latitude" value="<?php echo $nLatitude; ?>" size="30" maxlength="50"></td>
	</tr>
	<tr>
		<td class="LabelColumn"><?php echo gettext("Longitude:"); ?></td>
		<td class="TextColumnWithBottomBorder"><input type="text" Name="Longitude" value="<?php echo $nLongitude; ?>" size="30" maxlength="50"></td>
	</tr>
<?php	} 
	} /* Lat/Lon can be hidden - General Settings */ ?>
	</table>
	</td>

		<?php if ($numCustomFields > 0) { ?>
			<td valign="top">
			<table cellpadding="3">
				<tr>
					<td colspan="2" align="center"><h3><?php echo gettext("Custom Fields"); ?></h3></td>
				</tr>
				<?php
				mysql_data_seek($rsCustomFields,0);

				while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH) )
				{
					extract($rowCustomField);
					if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$fam_custom_FieldSec]]))
					{
						echo "<tr><td class=\"LabelColumn\">" . $fam_custom_Name . "</td><td class=\"TextColumn\">";

						$currentFieldData = trim($aCustomData[$fam_custom_Field]);

						if ($type_ID == 11) $fam_custom_Special = $sPhoneCountry;

						formCustomField($type_ID, $fam_custom_Field, $currentFieldData, $fam_custom_Special, !isset($_POST["FamilySubmit"]));
						echo "<span style=\"color: red; \">" . $aCustomErrors[$fam_custom_Field] . "</span>";
						echo "</td></tr>";
					}
				}
				?>
			</table>
			</td>
		<?php } ?>
	
	</tr>
	
	<tr>
		<td>&nbsp;</td>
	</tr>

	<?php if ($iFamilyMemberRows > 0) { ?>

	<tr>
		<td colspan="2">
		<div class="MediumText"><center><?php if (!strlen($iFamilyID)) { echo gettext("You may create family members now or add them later.  All entries will become <i>new</i> person records."); }?></center></div><br><br>
		<table cellpadding="3" cellspacing="0" width="100%">
		<tr align="center">
			<td>&nbsp;</td>
			<td colspan="3"><i><b><?php echo gettext("Complete Name"); ?></b></i></td>
			<td colspan="2">&nbsp;</td>
			<td colspan="3"><i><b><?php echo gettext("Birth Date"); ?></b></i></td>
		</tr>
		<tr class="TableHeader" align="center">
			<td><?php echo gettext("Family Members"); ?></td>
			<td><?php echo gettext("First"); ?></td>
			<td><?php echo gettext("Middle"); ?></td>
			<td><?php echo gettext("Last"); ?></td>
			<td><?php echo gettext("Gender"); ?></td>
			<td><?php echo gettext("Role"); ?></td>
			<td><?php echo gettext("Month"); ?></td>
			<td><?php echo gettext("Day"); ?></td>
			<td><?php echo gettext("Year"); ?></td>
			<td><?php echo gettext("Classification"); ?></td>
		</tr>
		<?php

		//Get family roles
		$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence";
		$rsFamilyRoles = RunQuery($sSQL);
		$numFamilyRoles = mysql_num_rows($rsFamilyRoles);
		for($c=1; $c <= $numFamilyRoles; $c++)
		{
			$aRow = mysql_fetch_array($rsFamilyRoles);
			extract($aRow);
			$aFamilyRoleNames[$c] = $lst_OptionName;
			$aFamilyRoleIDs[$c] = $lst_OptionID;
		}

		for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++)
		{
		?>
		<tr>
			<td class="LabelColumn"><?php echo $iCount ?>:</td>
			<input type="hidden" name="PersonID<?php echo $iCount ?>" value="<?php echo $aPersonIDs[$iCount] ?>">
			<td class="TextColumn">
				<input name="FirstName<?php echo $iCount ?>" type="text" value="<?php echo $aFirstNames[$iCount] ?>" size="10">
				<div><font color="red"><?php echo $aFirstNameError[$iCount]; ?></font></div>
			</td>
			<td class="TextColumn">
				<input name="MiddleName<?php echo $iCount ?>" type="text" value="<?php echo $aMiddleNames[$iCount] ?>" size="10">
			</td>
			<td class="TextColumn">
				<input name="LastName<?php echo $iCount ?>" type="text" value="<?php echo $aLastNames[$iCount] ?>" size="10">
			</td>
			<td class="TextColumn">
				<select name="Gender<?php echo $iCount ?>">
					<option value="0" <?php if ($aGenders[$iCount] == 0) echo "selected" ?> ><?php echo gettext("Select Gender"); ?></option>
					<option value="1" <?php if ($aGenders[$iCount] == 1) echo "selected" ?> ><?php echo gettext("Male"); ?></option>
					<option value="2" <?php if ($aGenders[$iCount] == 2) echo "selected" ?> ><?php echo gettext("Female"); ?></option>
				</select>
			</td>

			<td class="TextColumn">
				<select name="Role<?php echo $iCount ?>">
					<option value="0" <?php if ($aRoles[$iCount] == 0) echo "selected" ?> ><?php echo gettext("Select Role"); ?></option>
				<?php
				//Build the role select box
				for($c=1; $c <= $numFamilyRoles; $c++)
				{
					echo "<option value=\"" . $aFamilyRoleIDs[$c] . "\"";
					if ($aRoles[$iCount] == $aFamilyRoleIDs[$c]) echo " selected";
					echo ">" . $aFamilyRoleNames[$c] . "</option>";
				}
				?>
				</select>
			</td>
			<td class="TextColumn">
				<select name="BirthMonth<?php echo $iCount ?>">
					<option value="0" <?php if ($aBirthMonths[$iCount] == 0) { echo "selected"; } ?>><?php echo gettext("Unknown"); ?></option>
					<option value="01" <?php if ($aBirthMonths[$iCount] == 1) { echo "selected"; } ?>><?php echo gettext("January"); ?></option>
					<option value="02" <?php if ($aBirthMonths[$iCount] == 2) { echo "selected"; } ?>><?php echo gettext("February"); ?></option>
					<option value="03" <?php if ($aBirthMonths[$iCount] == 3) { echo "selected"; } ?>><?php echo gettext("March"); ?></option>
					<option value="04" <?php if ($aBirthMonths[$iCount] == 4) { echo "selected"; } ?>><?php echo gettext("April"); ?></option>
					<option value="05" <?php if ($aBirthMonths[$iCount] == 5) { echo "selected"; } ?>><?php echo gettext("May"); ?></option>
					<option value="06" <?php if ($aBirthMonths[$iCount] == 6) { echo "selected"; } ?>><?php echo gettext("June"); ?></option>
					<option value="07" <?php if ($aBirthMonths[$iCount] == 7) { echo "selected"; } ?>><?php echo gettext("July"); ?></option>
					<option value="08" <?php if ($aBirthMonths[$iCount] == 8) { echo "selected"; } ?>><?php echo gettext("August"); ?></option>
					<option value="09" <?php if ($aBirthMonths[$iCount] == 9) { echo "selected"; } ?>><?php echo gettext("September"); ?></option>
					<option value="10" <?php if ($aBirthMonths[$iCount] == 10) { echo "selected"; } ?>><?php echo gettext("October"); ?></option>
					<option value="11" <?php if ($aBirthMonths[$iCount] == 11) { echo "selected"; } ?>><?php echo gettext("November"); ?></option>
					<option value="12" <?php if ($aBirthMonths[$iCount] == 12) { echo "selected"; } ?>><?php echo gettext("December"); ?></option>
				</select>
			</td>
			<td class="TextColumn">
				<select name="BirthDay<?php echo $iCount ?>">
					<option value="0">Unk</option>
					<?php for ($x=1; $x < 32; $x++)
					{
						if ($x < 10) { $sDay = "0" . $x; } else { $sDay = $x; }
					?>
					<option value="<?php echo $sDay ?>" <?php if ($aBirthDays[$iCount] == $x) {echo "selected"; } ?>><?php echo $x ?></option>
				<?php } ?>
				</select>
			</td>
			<td class="TextColumn">
			<?php	if ((!$aperFlags[$iCount]) or ($_SESSION['bSeePrivacyData']))
			{
				$updateBirthYear = 1;
			?>
				<input name="BirthYear<?php echo $iCount ?>" type="text" value="<?php echo $aBirthYears[$iCount] ?>" size="4" maxlength="4">
				<div><font color="red"><?php echo $aBirthDateError[$iCount]; ?></font></div>
			<?php }
			else 
			{ 
				$updateBirthYear = 0;
			}
			?>
				&nbsp;
			</td>
			<td>
				<select name="Classification<?php echo $iCount ?>">
					<option value="0" <?php if ($aClassification[$iCount] == 0) echo "selected" ?>><?php echo gettext("Unassigned"); ?></option>
					<option value="0">-----------------------</option>
					<?php
					//Get Classifications for the drop-down
					$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
					$rsClassifications = RunQuery($sSQL);

					//Display Classifications
					while ($aRow = mysql_fetch_array($rsClassifications))
					{
						extract($aRow);
						echo "<option value=\"" . $lst_OptionID . "\"";
						if ($aClassification[$iCount] == $lst_OptionID) echo " selected";
						echo ">" . $lst_OptionName . "&nbsp;";
					}
			echo "</select></td></tr>";
		}
		echo "</table></td></tr>";
	echo "<tr>";
	}
	
	echo "<td colspan=\"2\" align=\"center\">";
	echo "<input type=\"hidden\" Name=\"UpdateBirthYear\" value=\"".$updateBirthYear."\">";

	echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Save") . "\" Name=\"FamilySubmit\">";
	if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"icButton\" value=\"Save and Add\" name=\"FamilySubmitAndAdd\">"; }
	echo "<input type=\"button\" class=\"icButton\" value=\"" . gettext("Cancel") . "\" Name=\"FamilyCancel\"";
	if (strlen($iFamilyID) > 0)
		echo "\"onclick=\"javascript:document.location='FamilyView.php?FamilyID=$iFamilyID';\">";
	else
		echo "\"onclick=\"javascript:document.location='SelectList.php';\">";
	echo "</td></tr></form></table>";
require "Include/Footer.php";
?>