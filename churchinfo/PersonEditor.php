<?php
/*******************************************************************************
 *
 *  filename    : PersonEditor.php
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2005 Michael Wilt
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

//Set the page title
$sPageTitle = gettext("Person Editor");

//Get the PersonID out of the querystring
$iPersonID = FilterInput($_GET["PersonID"],'int');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (strlen($iPersonID) > 0)
{
	$sSQL = "SELECT per_fam_ID FROM person_per WHERE per_ID = " . $iPersonID;
	$rsPerson = RunQuery($sSQL);
	extract(mysql_fetch_array($rsPerson));

	if (mysql_num_rows($rsPerson) == 0)
	{
		Redirect("Menu.php");
		exit;
	}

	if ( !(
	       $_SESSION['bEditRecords'] ||
	       ($_SESSION['bEditSelf'] && $iPersonID==$_SESSION['iUserID']) ||
	       ($_SESSION['bEditSelf'] && $per_fam_ID==$_SESSION['iFamID'])
		  )
	   )
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
// Get Field Security List Matrix
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence";
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsSecurityGrp))
{
	extract ($aRow);
	$aSecurityType[$lst_OptionID] = $lst_OptionName;
}


// Get the list of custom person fields
$sSQL = "SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

//Is this the second pass?
if (isset($_POST["PersonSubmit"]) || isset($_POST["PersonSubmitAndAdd"]))
{
	//Get all the variables from the request object and assign them locally
	$sTitle = FilterInput($_POST["Title"]);
	$sFirstName = FilterInput($_POST["FirstName"]);
	$sMiddleName = FilterInput($_POST["MiddleName"]);
	$sLastName = FilterInput($_POST["LastName"]);
	$sSuffix = FilterInput($_POST["Suffix"]);
	$iGender = FilterInput($_POST["Gender"],'int');
	$sAddress1 = FilterInput($_POST["Address1"]);
	$sAddress2 = FilterInput($_POST["Address2"]);
	$sCity = FilterInput($_POST["City"]);
	$sZip	= FilterInput($_POST["Zip"]);
	$sCountry = FilterInput($_POST["Country"]);
	$iFamily = FilterInput($_POST["Family"],'int');
	$iFamilyRole = FilterInput($_POST["FamilyRole"],'int');

	// Get their family's country in case person's country was not entered
	if ($iFamily > 0) {
		$sSQL = "SELECT fam_Country FROM family_fam WHERE fam_ID = " . $iFamily;
		$rsFamCountry = RunQuery($sSQL);
		extract(mysql_fetch_array($rsFamCountry));
	}

	$sCountryTest = SelectWhichInfo($sCountry, $fam_Country, false);
	if ($sCountryTest == "United States" || $sCountryTest == "Canada")
		$sState = FilterInput($_POST["State"]);
	else
		$sState = FilterInput($_POST["StateTextbox"]);

	$sHomePhone = FilterInput($_POST["HomePhone"]);
	$sWorkPhone = FilterInput($_POST["WorkPhone"]);
	$sCellPhone = FilterInput($_POST["CellPhone"]);
	$sEmail = FilterInput($_POST["Email"]);
	$sWorkEmail = FilterInput($_POST["WorkEmail"]);
	$iBirthMonth = FilterInput($_POST["BirthMonth"],'int');
	$iBirthDay = FilterInput($_POST["BirthDay"],'int');
	$iBirthYear = FilterInput($_POST["BirthYear"],'int');
	$bHideAge = isset($_POST["HideAge"]);
	$dFriendDate = FilterInput($_POST["FriendDate"]);
	$dMembershipDate = FilterInput($_POST["MembershipDate"]);
	$iClassification = FilterInput($_POST["Classification"],'int');
	$sEnvelope = FilterInput($_POST['EnvID'],'int');
	$iupdateBirthYear = FilterInput($_POST['updateBirthYear'],'int');

	$bNoFormat_HomePhone = isset($_POST["NoFormat_HomePhone"]);
	$bNoFormat_WorkPhone = isset($_POST["NoFormat_WorkPhone"]);
	$bNoFormat_CellPhone = isset($_POST["NoFormat_CellPhone"]);

	//Initialize the error flag
	$bErrorFlag = false;

	//Adjust variables as needed
	if ($iFamily == 0)	$iFamilyRole = 0;

	//Validate the Last Name.  If family selected, but no last name, inherit from family.
	if (strlen($sLastName) < 1)
	{
		if ($iFamily < 1) {
			$sLastNameError = gettext("You must enter a Last Name if no Family is selected.");
			$bErrorFlag = true;
		} else {
			$sSQL = "SELECT fam_Name FROM family_fam WHERE fam_ID = " . $iFamily;
			$rsFamName = RunQuery($sSQL);
			$aTemp = mysql_fetch_array($rsFamName);
			$sLastName = $aTemp[0];
		}
	}

	// If they entered a full date, see if it's valid
//	if ($updateBirthYear == 1) 
//	{
		if (strlen($iBirthYear) > 0)
		{
			if ($iBirthYear == 0) { // If zero set to NULL
				$iBirthYear = NULL;
			} elseif ($iBirthYear > 2155 || $iBirthYear < 1901) {
				$sBirthYearError = gettext("Invalid Year: allowable values are 1901 to 2155");
				$bErrorFlag = true;
			} elseif ($iBirthMonth > 0 && $iBirthDay > 0) {
				if (!checkdate($iBirthMonth,$iBirthDay,$iBirthYear)) {
					$sBirthDateError = gettext("Invalid Birth Date.");
					$bErrorFlag = true;
				}
			}
		}
//	}

	// Validate Friend Date if one was entered
	if (strlen($dFriendDate) > 0)
	{
		$dateString = parseAndValidateDate($dFriendDate, $locale = "US", $pasfut = "past");
		if ( $dateString === FALSE ) {
			$sFriendDateError = "<span style=\"color: red; \">" 
								. gettext("Not a valid Friend Date") . "</span>";
			$bErrorFlag = true;
		} else {
			$dFriendDate = $dateString;
		}
	}

	// Validate Membership Date if one was entered
	if (strlen($dMembershipDate) > 0)
	{
		$dateString = parseAndValidateDate($dMembershipDate, $locale = "US", $pasfut = "past");
		if ( $dateString === FALSE ) {
			$sMembershipDateError = "<span style=\"color: red; \">" 
								. gettext("Not a valid Membership Date") . "</span>";
			$bErrorFlag = true;
		} else {
			$dMembershipDate = $dateString;
		}
	}

	// Validate Email
	if (strlen($sEmail) > 0)
	{
		if ( checkEmail($sEmail) == false ) {
			$sEmailError = "<span style=\"color: red; \">" 
								. gettext("Email is Not Valid") . "</span>";
			$bErrorFlag = true;
		} else {
			$sEmail = $sEmail;
		}
	}
	
	// Validate Work Email
	if (strlen($sWorkEmail) > 0)
	{
		if ( checkEmail($sWorkEmail) == false ) {
			$sWorkEmailError = "<span style=\"color: red; \">" 
								. gettext("Work Email is Not Valid") . "</span>";
			$bErrorFlag = true;
		} else {
			$sWorkEmail = $sWorkEmail;
		}
	}

	// Validate all the custom fields
	while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH) )
	{
		extract($rowCustomField);

		if (($aSecurityType[$custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$custom_FieldSec]]))
		{
			$currentFieldData = FilterInput($_POST[$custom_Field]);

			$bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $custom_Field, $aCustomErrors);

			// assign processed value locally to $aPersonProps so we can use it to generate the form later
			$aCustomData[$custom_Field] = $currentFieldData;
		}
	}

	//If no errors, then let's update...
	if (!$bErrorFlag)
	{
		$sPhoneCountry = SelectWhichInfo($sCountry,$fam_Country,false);

		if (!$bNoFormat_HomePhone) $sHomePhone = CollapsePhoneNumber($sHomePhone,$sPhoneCountry);
		if (!$bNoFormat_WorkPhone) $sWorkPhone = CollapsePhoneNumber($sWorkPhone,$sPhoneCountry);
		if (!$bNoFormat_CellPhone) $sCellPhone = CollapsePhoneNumber($sCellPhone,$sPhoneCountry);

		//If no birth year, set to NULL
		if ((strlen($iBirthYear) != 4) )
		{
			$iBirthYear = "NULL";
		} else {
			$iBirthYear = "'$iBirthYear'";
		}

		// New Family (add)
		// Family will be named by the Last Name. 
		if ($iFamily == -1)
		{
			$sSQL = "INSERT INTO family_fam (fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_HomePhone, fam_WorkPhone, fam_CellPhone, fam_Email, fam_DateEntered, fam_EnteredBy)
					VALUES ('" . $sLastName . "','" . $sAddress1 . "','" . $sAddress2 . "','" . $sCity . "','" . $sState . "','" . $sZip . "','" . $sCountry . "','" . $sHomePhone . "','" . $sWorkPhone . "','". $sCellPhone . "','". $sEmail . "','" . date("YmdHis") . "'," . $_SESSION['iUserID'].")";
			//Execute the SQL
			RunQuery($sSQL);
			//Get the key back
			$sSQL = "SELECT MAX(fam_ID) AS iFamily FROM family_fam";
			$rsLastEntry = RunQuery($sSQL);
			extract(mysql_fetch_array($rsLastEntry));
		}

		if ($bHideAge) {
			$per_Flags = 1;
		} else {
			$per_Flags = 0;
		} 

		// New Person (add)
		if (strlen($iPersonID) < 1)
		{
			if (!$_SESSION['bFinance'] || strlen($sEnvelope) < 1)
				$sEnvelope = "NULL";

			$sSQL = "INSERT INTO person_per (per_Title, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_Gender, per_Address1, per_Address2, per_City, per_State, per_Zip, per_Country, per_HomePhone, per_WorkPhone, per_CellPhone, per_Email, per_WorkEmail, per_BirthMonth, per_BirthDay, per_BirthYear, per_Envelope, per_fam_ID, per_fmr_ID, per_MembershipDate, per_cls_ID, per_DateEntered, per_EnteredBy, per_FriendDate, per_Flags ) 
			         VALUES ('" . $sTitle . "','" . $sFirstName . "','" . $sMiddleName . "','" . $sLastName . "','" . $sSuffix . "'," . $iGender . ",'" . $sAddress1 . "','" . $sAddress2 . "','" . $sCity . "','" . $sState . "','" . $sZip . "','" . $sCountry . "','" . $sHomePhone . "','" . $sWorkPhone . "','" . $sCellPhone . "','" . $sEmail . "','" . $sWorkEmail . "'," . $iBirthMonth . "," . $iBirthDay . "," . $iBirthYear . "," . $sEnvelope . "," . $iFamily . "," . $iFamilyRole . ",";
			if ( strlen($dMembershipDate) > 0 )
				$sSQL .= "\"" . $dMembershipDate . "\"";
			else
				$sSQL .= "NULL";
			$sSQL .= "," . $iClassification . ",'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ",";

			if ( strlen($dFriendDate) > 0 )
				$sSQL .= "\"" . $dFriendDate . "\"";
			else
				$sSQL .= "NULL";

			$sSQL .= ", " . $per_Flags;
			$sSQL .= ")";

			$bGetKeyBack = True;

		// Existing person (update)
		} else {

			//$sBirthYear = ($iupdateBirthYear & 1) ? "per_BirthYear=" . $iBirthYear. ", " : "";
			$sSQL = "UPDATE person_per SET per_Title = '" . $sTitle . "',per_FirstName = '" . $sFirstName . "',per_MiddleName = '" . $sMiddleName . "', per_LastName = '" . $sLastName . "', per_Suffix = '" . $sSuffix . "', per_Gender = " . $iGender . ", per_Address1 = '" . $sAddress1 . "', per_Address2 = '" . $sAddress2 . "', per_City = '" . $sCity . "', per_State = '" . $sState . "', per_Zip = '" . $sZip . "', per_Country = '" . $sCountry . "', per_HomePhone = '" . $sHomePhone . "', per_WorkPhone = '" . $sWorkPhone . "', per_CellPhone = '" . $sCellPhone . "', per_Email = '" . $sEmail . "', per_WorkEmail = '" . $sWorkEmail . "', per_BirthMonth = " . $iBirthMonth . ", per_BirthDay = " . $iBirthDay . ", " . "per_BirthYear = ". $iBirthYear. ", per_fam_ID = " . $iFamily . ", per_Fmr_ID = " . $iFamilyRole . ", per_cls_ID = " . $iClassification . ", per_MembershipDate = ";
			if ( strlen($dMembershipDate) > 0 )
				$sSQL .= "\"" . $dMembershipDate . "\"";
			else
				$sSQL .= "NULL";

			if ($_SESSION['bFinance'])
			{
				if (strlen($sEnvelope) < 1) $sEnvelope = "NULL";
				$sSQL .= ", per_Envelope = " . $sEnvelope;
			}

			$sSQL .= ", per_DateLastEdited = '" . date("YmdHis") . "', per_EditedBy = " . $_SESSION['iUserID'] . ", per_FriendDate =";

			if ( strlen($dFriendDate) > 0 )
				$sSQL .= "\"" . $dFriendDate . "\"";
			else
				$sSQL .= "NULL";

			$sSQL .= ", per_Flags=" . $per_Flags;

			$sSQL .= " WHERE per_ID = " . $iPersonID;

			$bGetKeyBack = false;
		}

		//Execute the SQL
		RunQuery($sSQL);

		// If this is a new person, get the key back and insert a blank row into the person_custom table
		if ($bGetKeyBack)
		{
			$sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
			$rsPersonID = RunQuery($sSQL);
			extract(mysql_fetch_array($rsPersonID));
			$sSQL = "INSERT INTO `person_custom` (`per_ID`) VALUES ('" . $iPersonID . "')";
			RunQuery($sSQL);
		}

		// Update the custom person fields.
		if ($numCustomFields > 0)
		{
//			$sSQL = "REPLACE INTO person_custom SET ";
			mysql_data_seek($rsCustomFields,0);
			
			$sSQL = "";
			while ( $rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH) )
			{
				extract($rowCustomField);
				if (($aSecurityType[$custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$custom_FieldSec]]))
				{
					$currentFieldData = trim($aCustomData[$custom_Field]);
					sqlCustomField($sSQL, $type_ID, $currentFieldData, $custom_Field, $sPhoneCountry);
				}
			}

			// chop off the last 2 characters (comma and space) added in the last while loop iteration.
			if ($sSQL > "") {
				$sSQL = "REPLACE INTO person_custom SET " . $sSQL . " per_ID = " . $iPersonID;
				RunQuery($sSQL);
			}
//			$sSQL = substr($sSQL,0,-2);

//			$sSQL .= ", per_ID = " . $iPersonID;

			//Execute the SQL
		}

		// Check for redirection to another page after saving information: (ie. PersonEditor.php?previousPage=prev.php?a=1;b=2;c=3)
		if ($previousPage != "") {
			$previousPage = str_replace(";","&",$previousPage) ;
			Redirect($previousPage . $iPersonID);
		}
		else if (isset($_POST["PersonSubmit"]))
		{
			//Send to the view of this person
			Redirect("PersonView.php?PersonID=" . $iPersonID);
		}
		else
		{
			//Reload to editor to add another record
			Redirect("PersonEditor.php");
		}

	}

	// Set the envelope in case the form failed.
	$per_Envelope = $sEnvelope;

} else {

	//FirstPass
	//Are we editing or adding?
	if (strlen($iPersonID) > 0)
	{
		//Editing....
		//Get all the data on this record

		$sSQL = "SELECT * FROM person_per LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_ID = " . $iPersonID;
		$rsPerson = RunQuery($sSQL);
		extract(mysql_fetch_array($rsPerson));

		$sTitle = $per_Title;
		$sFirstName = $per_FirstName;
		$sMiddleName = $per_MiddleName;
		$sLastName = $per_LastName;
		$sSuffix = $per_Suffix;
		$iGender = $per_Gender;
		$sAddress1 = $per_Address1;
		$sAddress2 = $per_Address2;
		$sCity = $per_City;
		$sState = $per_State;
		$sZip	= $per_Zip;
		$sCountry = $per_Country;
		$sHomePhone = $per_HomePhone;
		$sWorkPhone = $per_WorkPhone;
		$sCellPhone = $per_CellPhone;
		$sEmail = $per_Email;
		$sWorkEmail = $per_WorkEmail;
		$iBirthMonth = $per_BirthMonth;
		$iBirthDay = $per_BirthDay;
		$iBirthYear = $per_BirthYear;
		$bHideAge = ($per_Flags & 1) != 0;
		$iOriginalFamily = $per_fam_ID;
		$iFamily = $per_fam_ID;
		$iFamilyRole = $per_fmr_ID;
		$dMembershipDate = $per_MembershipDate;
		$dFriendDate = $per_FriendDate;
		$iClassification = $per_cls_ID;
		$iViewAgeFlag = $per_Flags;

		$sPhoneCountry = SelectWhichInfo($sCountry,$fam_Country,false);

		$sHomePhone = ExpandPhoneNumber($per_HomePhone,$sPhoneCountry,$bNoFormat_HomePhone);
		$sWorkPhone = ExpandPhoneNumber($per_WorkPhone,$sPhoneCountry,$bNoFormat_WorkPhone);
		$sCellPhone = ExpandPhoneNumber($per_CellPhone,$sPhoneCountry,$bNoFormat_CellPhone);

		//The following values are True booleans if the family record has a value for the
		//indicated field.  These are used to highlight field headers in red.
		$bFamilyAddress1 = strlen($fam_Address1);
		$bFamilyAddress2 = strlen($fam_Address2);
		$bFamilyCity = strlen($fam_City);
		$bFamilyState = strlen($fam_State);
		$bFamilyZip = strlen($fam_Zip);
		$bFamilyCountry = strlen($fam_Country);
		$bFamilyHomePhone = strlen($fam_HomePhone);
		$bFamilyWorkPhone = strlen($fam_WorkPhone);
		$bFamilyCellPhone = strlen($fam_CellPhone);
		$bFamilyEmail = strlen($fam_Email);

		$sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $iPersonID;
		$rsCustomData = RunQuery($sSQL);
		$aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);
	}
	else
	{
		//Adding....
		//Set defaults
		$sCity = $sDefaultCity;
		$sCountry = $sDefaultCountry;
		$sState = $sDefaultState;
		$iFamily = "0";
		$iFamilyRole = "0";
		$iClassification = "0";
		$bHomeBound = False;
		$dFriendDate = date("Y-m-d"); // Default friend date is today
	}
}

//Get Classifications for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
$rsClassifications = RunQuery($sSQL);

//Get Families for the drop-down
$sSQL = "SELECT * FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence";
$rsFamilyRoles = RunQuery($sSQL);

require "Include/Header.php";

?>

<form method="post" action="PersonEditor.php?PersonID=<?php echo $iPersonID; ?>" name="PersonEditor">

<table cellpadding="3" align="center">

	<tr>
		<td <?php if ($numCustomFields > 0) echo "colspan=\"2\""; ?> align="center">
			<input type="submit" class="icButton" value="<?php echo gettext("Save"); ?>" name="PersonSubmit">
			<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Save and Add") . "\" name=\"PersonSubmitAndAdd\">"; } ?>
			<input type="button" class="icButton" value="<?php echo gettext("Cancel"); ?>" name="PersonCancel" onclick="javascript:document.location='<?php if (strlen($iPersonID) > 0) { echo "PersonView.php?PersonID=" . $iPersonID; } else {echo "SelectList.php?mode=person"; } ?>';">
		</td>
	</tr>

	<tr>
		<td <?php if ($numCustomFields > 0) echo "colspan=\"2\""; ?> class="SmallText" align="center">
			<?php echo gettext("Items in"); ?> <span style="color: red;"><?php echo gettext("red"); ?></span> <?php echo gettext("have corresponding values on the associated family record."); ?>
		</td>
	</tr>
	<tr>
		<td <?php if ($numCustomFields > 0) echo "colspan=\"2\""; ?> align="center">
		<?php if ( $bErrorFlag ) echo "<span class=\"LargeText\" style=\"color: red;\">" . gettext("Invalid fields or selections. Changes not saved! Please correct and try again!") . "</span>"; ?>
		</td>
	</tr>
	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td colspan="2" align="center"><h3><?php echo gettext("Standard Fields"); ?></h3></td>
			</tr>
			<tr>
				<td class="LabelColumn" <?php addToolTip("Examples: Mr., Mrs., Dr., Rev."); ?>><?php echo gettext("Title:"); ?></td>
				<td class="TextColumn"><input type="text" name="Title" id="Title" value="<?php echo htmlentities(stripslashes($sTitle),ENT_NOQUOTES, "UTF-8"); ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("First Name:"); ?></td>
				<td class="TextColumn"><input type="text" name="FirstName" id="FirstName" value="<?php echo htmlentities(stripslashes($sFirstName),ENT_NOQUOTES, "UTF-8"); ?>"><br><font color="red"><?php echo $sFirstNameError ?></font></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Middle Name:"); ?></td>
				<td class="TextColumn"><input type="text" name="MiddleName" id="MiddleName" value="<?php echo htmlentities(stripslashes($sMiddleName),ENT_NOQUOTES, "UTF-8"); ?>"><br><font color="red"><?php echo $sMiddleNameError ?></font></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Last Name:"); ?></td>
				<td class="TextColumn"><input type="text" name="LastName" id="LastName" value="<?php echo htmlentities(stripslashes($sLastName),ENT_NOQUOTES, "UTF-8"); ?>"><br><font color="red"><?php echo $sLastNameError ?></font></td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Examples: Jr., Sr., III"); ?>><?php echo gettext("Suffix:"); ?></td>
				<td class="TextColumn"><input type="text" name="Suffix" id="Suffix" value="<?php echo htmlentities(stripslashes($sSuffix),ENT_NOQUOTES, "UTF-8"); ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Gender:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="Gender">
						<option value="0"><?php echo gettext("Select Gender"); ?></option>
						<option value="1" <?php if ($iGender == 1) { echo "selected"; } ?>><?php echo gettext("Male"); ?></option>
						<option value="2" <?php if ($iGender == 2) { echo "selected"; } ?>><?php echo gettext("Female"); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<td>&nbsp;</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("If a family member, select the appropriate family from the list. Otherwise, leave this as is."); ?>><?php echo gettext("Family:"); ?></td>
				<td class="TextColumn">
					<select name="Family" size="8">
						<option value="0" selected><?php echo gettext("Unassigned"); ?></option>
						<option value="-1"><?php echo gettext("Create a new family (using last name)"); ?></option>
						<option value="0">-----------------------</option>

						<?php
						while ($aRow = mysql_fetch_array($rsFamilies))
						{
							extract($aRow);

							echo "<option value=\"" . $fam_ID . "\"";
							if ($iFamily == $fam_ID) { echo " selected"; }
							echo ">" . $fam_Name . "&nbsp;" . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
						}
						?>

					</select>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Select the appropriate role for the individual. If no family is assigned, do not assign a role."); ?>><?php echo gettext("Family Role:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="FamilyRole">
						<option value="0"><?php echo gettext("Unassigned"); ?></option>
						<option value="0">-----------------------</option>

						<?php
						while ($aRow = mysql_fetch_array($rsFamilyRoles))
						{
							extract($aRow);

							echo "<option value=\"" . $lst_OptionID . "\"";
							if ($iFamilyRole == $lst_OptionID) { echo " selected"; }
							echo ">" . $lst_OptionName . "&nbsp;";
						}
						?>

					</select>
				</td>
			</tr>

			<tr>
				<td>&nbsp;</td>
			</tr>
<?php if (!$bHidePersonAddress) { /* Person Address can be hidden - General Settings */ ?>
			<tr>
				<td class="LabelColumn" <?php addToolTip("Main address for an individual. If the address does not differ from the family, leave this field blank."); ?>><?php if ($bFamilyAddress1) { echo "<span style=\"color: red;\">"; } ?><?php echo gettext("Address1:"); ?></span></td>
				<td class="TextColumn"><input type="text" name="Address1" value="<?php echo htmlentities(stripslashes($sAddress1),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="50"></td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Additional information if needed. If the address does not differ from the fmaily, leave this field blank."); ?>><?php if ($bFamilyAddress2) { echo "<span style=\"color: red;\">"; } ?><?php echo gettext("Address2:"); ?></span></td>
				<td class="TextColumn"><input type="text" name="Address2" value="<?php echo htmlentities(stripslashes($sAddress2),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="50"></td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("If the city does not differ from the family, leave this field blank."); ?>><?php if ($bFamilyCity) { echo "<span style=\"color: red;\">"; } ?><?php echo gettext("City:"); ?></span></td>
				<td class="TextColumn"><input type="text" name="City" value="<?php echo htmlentities(stripslashes($sCity),ENT_NOQUOTES, "UTF-8"); ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Either use the drop-down menu (for the US) or text box. If the state does not differ from the family, leave this field blank."); ?>><?php if ($bFamilyState) { echo "<span style=\"color: red;\">"; } ?><?php echo gettext("State:"); ?></span></td>
				<td class="TextColumn">
					<?php require "Include/StateDropDown.php"; ?>
					OR
					<input type="text" name="StateTextbox" value="<?php if ($sPhoneCountry != "United States" && $sPhoneCountry != "Canada") echo htmlentities(stripslashes($sState),ENT_NOQUOTES, "UTF-8"); ?>" size="20" maxlength="30">
					<BR><?php echo gettext("(Use the textbox for countries other than US and Canada)"); ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("If the ZIP does not differ from the family, leave this field blank."); ?>><?php if ($bFamilyZip) { echo "<span style=\"color: red;\">"; } ?><?php echo gettext("Zip:"); ?></span></td>
				<td class="TextColumn"><input type="text" name="Zip" value="<?php echo htmlentities(stripslashes($sZip),ENT_NOQUOTES, "UTF-8"); ?>" maxlength="10" size="8"></td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Use the drop-down menu to select the appropriate country. If the Country does not differ from the family, leave this field blank."); ?>><?php if ($bFamilyCountry) { echo "<span style=\"color: red;\">"; } ?><?php echo gettext("Country:"); ?></span></td>
				<td class="TextColumnWithBottomBorder">
					<?php require "Include/CountryDropDown.php"; ?>
				</td>
			</tr>
<?php } ?>
			<tr>
				<td>&nbsp;</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Format: xxx-xxx-xxxx Ext. xxx.<br>If the Home Phone does not differ from the family, leave this field blank."); ?>>
					<?php
					if ($bFamilyHomePhone)
						echo "<span style=\"color: red;\">". gettext("Home Phone:") ."</span>";
					else
						echo gettext("Home Phone:");
					?>
				</td>
				<td class="TextColumn">
					<input type="text" name="HomePhone" value="<?php echo htmlentities(stripslashes($sHomePhone),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="30">
					<br><input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) echo " checked";?>><?php echo gettext("Do not auto-format"); ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Format: xxx-xxx-xxxx Ext. xxx.<br>If the Work Phone does not differ from the family, leave this field blank."); ?>>
					<?php
					if ($bFamilyWorkPhone)
						echo "<span style=\"color: red;\">" . gettext("Work Phone:") . "</span>";
					else
						echo gettext("Work Phone:");
					?>
				</td>
				<td class="TextColumn">
					<input type="text" name="WorkPhone" value="<?php echo htmlentities(stripslashes($sWorkPhone),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="30">
					<br><input type="checkbox" name="NoFormat_WorkPhone" value="1" <?php if ($bNoFormat_WorkPhone) echo " checked";?>><?php echo gettext("Do not auto-format"); ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Format: xxx-xxx-xxxx Ext. xxx.<br>If the Mobile Phone does not differ from the family, leave this field blank."); ?>>
					<?php
					if ($bFamilyCellPhone)
						echo "<span style=\"color: red;\">" . gettext("Mobile Phone:") . "</span>";
					else
						echo gettext("Mobile Phone:");
					?>
				</td>
				<td class="TextColumn">
					<input type="text" name="CellPhone" value="<?php echo htmlentities(stripslashes($sCellPhone),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="30">
					<br><input type="checkbox" name="NoFormat_CellPhone" value="1" <?php if ($bNoFormat_CellPhone) echo " checked";?>><?php echo gettext("Do not auto-format"); ?>
				</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("If the Email does not differ from the family, leave this field blank."); ?>>
					<?php
						if ($bFamilyEmail)
							echo "<span style=\"color: red;\">" . gettext("Email:") . "</span></td>";
						else
							echo gettext("Email:") . "</td>";
					?>
				<td class="TextColumnWithBottomBorder"><input type="text" name="Email" value="<?php echo htmlentities(stripslashes($sEmail),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="100"><font color="red"><?php echo $sEmailError ?></font></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Work / Other Email:"); ?></td>
				<td class="TextColumnWithBottomBorder"><input type="text" name="WorkEmail" value="<?php echo htmlentities(stripslashes($sWorkEmail),ENT_NOQUOTES, "UTF-8"); ?>" size="30" maxlength="100"><font color="red"><?php echo $sWorkEmailError ?></font></td>
			</tr>

			<tr>
				<td>&nbsp;</td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Use drop down-menus to select the birth date. If the year is not known, you can still include the date (for birthday reference), although age will not be calculated."); ?>><?php echo gettext("Birth Date:"); ?></td>
				<td class="TextColumn">
					<select name="BirthMonth">
						<option value="0" <?php if ($iBirthMonth == 0) { echo "selected"; } ?>><?php echo gettext("Unknown"); ?></option>
						<option value="01" <?php if ($iBirthMonth == 1) { echo "selected"; } ?>><?php echo gettext("January"); ?></option>
						<option value="02" <?php if ($iBirthMonth == 2) { echo "selected"; } ?>><?php echo gettext("February"); ?></option>
						<option value="03" <?php if ($iBirthMonth == 3) { echo "selected"; } ?>><?php echo gettext("March"); ?></option>
						<option value="04" <?php if ($iBirthMonth == 4) { echo "selected"; } ?>><?php echo gettext("April"); ?></option>
						<option value="05" <?php if ($iBirthMonth == 5) { echo "selected"; } ?>><?php echo gettext("May"); ?></option>
						<option value="06" <?php if ($iBirthMonth == 6) { echo "selected"; } ?>><?php echo gettext("June"); ?></option>
						<option value="07" <?php if ($iBirthMonth == 7) { echo "selected"; } ?>><?php echo gettext("July"); ?></option>
						<option value="08" <?php if ($iBirthMonth == 8) { echo "selected"; } ?>><?php echo gettext("August"); ?></option>
						<option value="09" <?php if ($iBirthMonth == 9) { echo "selected"; } ?>><?php echo gettext("September"); ?></option>
						<option value="10" <?php if ($iBirthMonth == 10) { echo "selected"; } ?>><?php echo gettext("October"); ?></option>
						<option value="11" <?php if ($iBirthMonth == 11) { echo "selected"; } ?>><?php echo gettext("November"); ?></option>
						<option value="12" <?php if ($iBirthMonth == 12) { echo "selected"; } ?>><?php echo gettext("December"); ?></option>
					</select>
					<select name="BirthDay">
						<option value="0"><?php echo gettext("Unk"); ?></option>
						<?php for ($x=1; $x < 32; $x++)
						{
							if ($x < 10) { $sDay = "0" . $x; } else { $sDay = $x; }
						?>
							<option value="<?php echo $sDay ?>" <?php if ($iBirthDay == $x) {echo "selected"; } ?>><?php echo $x ?></option>
						<?php } ?>
					</select>
				<font color="red"><?php echo $sBirthDateError ?></font>
<?php /* */?>
				</td>
			</tr>
			<?php /*	if (($_SESSION['bSeePrivacyData']) || (strlen($iPersonID) < 1))
			{
				$updateBirthYear = 1;
			*/ ?>
			<tr>
				<td class="LabelColumn" <?php addToolTip("It must be in four-digit format (XXXX).<br>If the birth date is not known, you can still include the date (for age reference), although birthday will not be calculated."); ?>><?php echo gettext("Birth Year:"); ?></td>
				<td class="TextColumn"><input type="text" name="BirthYear" value="<?php echo $iBirthYear ?>" maxlength="4" size="5"><font color="red"><br><?php echo $sBirthYearError ?></font><br><font size="1"><?php echo gettext("Must be four-digit format."); ?></font></td>
				<td class="TextColumn"><input type="checkbox" name="HideAge" value="1" <?php if ($bHideAge) echo " checked";?>><?php echo gettext("Hide Age"); ?></td>
			</tr>
			<?php /*
			} else {
				$updateBirthYear = 0;
			} */ ?>
<?php /* ?>
				 <input type="text" name="BirthYear" value="<?php echo $iBirthYear ?>" maxlength="4" size="5"><font color="red"><br><?php echo $sBirthYearError ?></font><br><font size="2"><?php echo gettext("Leave year blank to hide age."); ?></font>

				</td>
			</tr>
<?php */ ?>
			<tr>
				<td>&nbsp;</td>
			</tr>
<?php if (!$bHideFriendDate) { /* Friend Date can be hidden - General Settings */ ?>
			<tr>
				<td class="LabelColumn" <?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Friend Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="FriendDate" value="<?php echo $dFriendDate; ?>" maxlength="10" id="sel2" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel2', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sFriendDateError ?></font></td>
			</tr>
<?php } ?>	
			<tr>
				<td class="LabelColumn" <?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Membership Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="MembershipDate" value="<?php echo $dMembershipDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sMembershipDateError ?></font></td>
			</tr>

			<tr>
				<td class="LabelColumn" <?php addToolTip("Select the appropriate classification. These can be set using the classification manager in admin."); ?>><?php echo gettext("Classification:"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="Classification">
						<option value="0"><?php echo gettext("Unassigned"); ?></option>
						<option value="0">-----------------------</option>

						<?php
						while ($aRow = mysql_fetch_array($rsClassifications))
						{
							extract($aRow);

							echo "<option value=\"" . $lst_OptionID . "\"";
							if ($iClassification == $lst_OptionID) { echo " selected"; }
							echo ">" . $lst_OptionName . "&nbsp;";
						}
						?>

					</select>
				</td>
			</tr>
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
					
					if (($aSecurityType[$custom_FieldSec] == 'bAll') or ($_SESSION[$aSecurityType[$custom_FieldSec]]))
					{
						echo "<tr><td class=\"LabelColumn\">" . $custom_Name . "</td><td class=\"TextColumn\">";

						$currentFieldData = trim($aCustomData[$custom_Field]);

						if ($type_ID == 11) $custom_Special = $sPhoneCountry;

						formCustomField($type_ID, $custom_Field, $currentFieldData, $custom_Special, !isset($_POST["PersonSubmit"]));
						echo "<span style=\"color: red; \">" . $aCustomErrors[$custom_Field] . "</span>";
						echo "</td></tr>";
					}
				}
				?>
			</table>
			</td>
		<?php } ?>

	<tr>
		<td>&nbsp;</td>
	</tr>

	<tr>
		<td <?php if ($numCustomFields > 0) echo "colspan=\"2\""; ?> align="center">
			<?php // echo "<input type=\"hidden\" Name=\"updateBirthYear\" value=\"".$updateBirthYear."\">"; ?>
			<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save") . '"'; ?> name="PersonSubmit">
			<?php if ($_SESSION['bAddRecords']) { echo "<input type=\"submit\" class=\"icButton\" value=\"" . gettext("Save and Add") . "\" name=\"PersonSubmitAndAdd\">"; } ?>
			<input type="button" class="icButton" <?php echo 'value="' . gettext("Cancel") . '"'; ?> name="PersonCancel" onclick="javascript:document.location='<?php if (strlen($iPersonID) > 0) { echo "PersonView.php?PersonID=" . $iPersonID; } else {echo "SelectList.php?mode=person"; } ?>';">
		</td>
	</tr>

	</form>

</table>

<?php
require "Include/Footer.php";
?>
