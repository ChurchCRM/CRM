<?php
/*******************************************************************************
 *
 *  filename    : CSVImport.php
 *  last change : 2003-10-02
 *  description : Tool for importing CSV person data into InfoCentral
 *
 *  http://www.infocentral.org/
 *  Copyright 2003 Chris Gebhardt
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

if (!$_SESSION['bAdmin']) {
	Redirect("Menu.php");
	exit;
}

// Set the page title and include HTML header
$sPageTitle = "CSV Import";
require "Include/Header.php";

$iStage = 1;

// Is the CSV file being uploaded?
if (isset($_POST["UploadCSV"]))
{
	// Check if a valid CSV file was actually uploaded
	if ($_FILES['CSVfile']['name'] == "")
	{
		$csvError = gettext("No file selected for upload.");
	}

	// Valid file, so save it and display the import mapping form.
	else
	{
		$system_temp = ini_get("session.save_path");
		$csvTempFile = $system_temp . "/import.csv";
		move_uploaded_file($_FILES['CSVfile']['tmp_name'], $csvTempFile);

		// create the file pointer
		$pFile = fopen ($csvTempFile, "r");

		// count # lines in the file
		$iNumRows = 0;
		while ($tmp = fgets($pFile,2048)) $iNumRows++;
		rewind($pFile);

		// create the form
		?>
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">

		<?php
		echo gettext("Total number of rows in the CSV file:") . $iNumRows;
		echo "<br><br>";
		echo "<table border=1>";

		// grab and display up to the first 8 lines of data in the CSV in a table
		$iRow = 0;
		while (($aData = fgetcsv($pFile, 2048, ",")) && $iRow++ < 9)
		{
			$numCol = count($aData);

			echo "<tr>";
			for ($col = 0; $col < $numCol; $col++) {
				echo "<td>" . $aData[$col] . "&nbsp;</td>";
			}
			echo "</tr>";
		}

		fclose($pFile);

		$sSQL = "SELECT * FROM person_custom_master ORDER BY custom_Order";
		$rsCustomFields = RunQuery($sSQL);

		// add select boxes for import destination mapping
		for ($col = 0; $col < $numCol; $col++)
		{
		?>
			<td>
			<select name="<?php echo "col" . $col;?>">
				<option value="0"><?php echo gettext("Ignore this Field"); ?></option>
				<option value="1"><?php echo gettext("Title"); ?></option>
				<option value="2"><?php echo gettext("First Name"); ?></option>
				<option value="3"><?php echo gettext("Middle Name"); ?></option>
				<option value="4"><?php echo gettext("Last Name"); ?></option>
				<option value="5"><?php echo gettext("Suffix"); ?></option>
				<option value="6"><?php echo gettext("Gender"); ?></option>
				<option value="7"><?php echo gettext("Donation Envelope"); ?></option>
				<option value="8"><?php echo gettext("Address1"); ?></option>
				<option value="9"><?php echo gettext("Address2"); ?></option>
				<option value="10"><?php echo gettext("City"); ?></option>
				<option value="11"><?php echo gettext("State"); ?></option>
				<option value="12"><?php echo gettext("Zip"); ?></option>
				<option value="13"><?php echo gettext("Country"); ?></option>
				<option value="14"><?php echo gettext("Home Phone"); ?></option>
				<option value="15"><?php echo gettext("Work Phone"); ?></option>
				<option value="16"><?php echo gettext("Mobile Phone"); ?></option>
				<option value="17"><?php echo gettext("Email"); ?></option>
				<option value="18"><?php echo gettext("Work / Other Email"); ?></option>
				<option value="19"><?php echo gettext("Birth Date"); ?></option>
				<option value="20"><?php echo gettext("Membership Date"); ?></option>

				<?php
				mysql_data_seek($rsCustomFields,0);
				while ($aRow = mysql_fetch_array($rsCustomFields))
				{
					extract($aRow);
					// No easy way to import person-from-group or custom-list types
					if ($type_ID != 9 && $type_ID != 12)
						echo "<option value=\"" . $custom_Field . "\">" . $custom_Name . "</option>";
				}
				?>
			</select>
			</td>
		<?php
		}

		echo "</table>";
		?>
		<BR>
		<input type="checkbox" value="1" name="IgnoreFirstRow"><?php echo gettext("Ignore first CSV row (to exclude a header)"); ?>
		<BR><BR>
		<BR>
		<input type="checkbox" value="1" name="MakeFamilyRecords"><?php echo gettext("Make Family records for each Person imported"); ?>
		<BR><BR>
		<select name="DateMode">
			<option value="1">YYYY-MM-DD</option>
			<option value="2">MM-DD-YYYY</option>
			<option value="3">DD-MM-YYYY</option>
		</select>
		<?php echo gettext("NOTE: Separators (dashes, etc.) or lack thereof do not matter"); ?>
		<BR><BR>
		<?php
			$sCountry = $sDefaultCountry;
			require "Include/CountryDropDown.php";
			echo gettext("Default country if none specified otherwise");

			$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
			$rsClassifications = RunQuery($sSQL);
		?>
		<BR><BR>
		<select name="Classification">
			<option value="0"><?php echo gettext("Unassigned"); ?></option>
			<option value="0">-----------------------</option>

			<?php
				while ($aRow = mysql_fetch_array($rsClassifications))
				{
					extract($aRow);
					echo "<option value=\"" . $lst_OptionID . "\"";
					echo ">" . $lst_OptionName . "&nbsp;";
				}
			?>
		</select>
		<?php echo gettext("Classification"); ?>
		<BR><BR>
		<input type="submit" class="icButton" value="<?php echo gettext("Perform Import"); ?>" name="DoImport">
		</form>

		<?php
		$iStage = 2;
	}
}


// Has the import form been submitted yet?
if (isset($_POST["DoImport"]))
{
	$system_temp = ini_get("session.save_path");
	$csvTempFile = $system_temp . "/import.csv";

	// make sure the file still exists
	if (file_exists($csvTempFile))
	{
		// create the file pointer
		$pFile = fopen ($csvTempFile, "r");

		$bHasCustom = false;
		$sDefaultCountry = FilterInput($_POST["Country"]);
		$iClassID = FilterInput($_POST["Classification"],'int');
		$iDateMode = FilterInput($_POST["DateMode"],'int');

		// Get the number of CSV columns for future reference
		$aData = fgetcsv($pFile, 2048, ",");
		$numCol = count($aData);
		if (!isset($_POST["IgnoreFirstRow"])) rewind($pFile);

		// Put the column types from the mapping form into an array
		for ($col = 0; $col < $numCol; $col++)
		{
			if (substr($_POST["col" . $col],0,1) == "c")
			{
				$aColumnCustom[$col] = 1;
				$bHasCustom = true;
			}
			else
			{
				$aColumnCustom[$col] = 0;
			}
			$aColumnID[$col] = $_POST["col" . $col];
		}

		if ($bHasCustom)
		{
			$sSQL = "SELECT * FROM person_custom_master";
			$rsCustomFields = RunQuery($sSQL);

			while ($aRow = mysql_fetch_array($rsCustomFields))
			{
				extract($aRow);
				$aCustomTypes[$custom_Field] = $type_ID;
			}
		}

		//
		// Need to lock the person_custom and person_per tables!!
		//

		$aPersonTableFields = array (
				1=>"per_Title", 2=>"per_FirstName", 3=>"per_MiddleName", 4=>"per_LastName",
				5=>"per_Suffix", 6=>"per_Gender", 7=>"per_Envelope", 8=>"per_Address1", 9=>"per_Address2",
				10=>"per_City", 11=>"per_State", 12=>"per_Zip", 13=>"per_Country", 14=>"per_HomePhone",
				15=>"per_WorkPhone", 16=>"per_CellPhone", 17=>"per_Email", 18=>"per_WorkEmail",
				20=>"per_MembershipDate");

		$importCount = 0;

		while ($aData = fgetcsv($pFile, 2048, ","))
		{
			// Use the default country from the mapping form in case we don't find one otherwise
			$sCountry = $sDefaultCountry;

			$sSQLpersonFields = "INSERT INTO person_per (";
			$sSQLpersonData = " VALUES (";
			$sSQLcustom = "UPDATE person_custom SET ";

			// Build the person_per SQL first.
			// We do this in case we can get a country, which will allow phone number parsing later
			for ($col = 0; $col < $numCol; $col++)
			{
				// Is it not a custom field?
				if (!$aColumnCustom[$col])
				{
					$currentType = $aColumnID[$col];

					// handler for each of the 20 person_per table column possibilities
					switch($currentType)
					{
						// Simple strings.. no special processing
						case 1: case 2: case 3: case 4: case 5: case 8: case 9:
						case 10: case 11: case 12: case 17: case 18:
							$sSQLpersonData .= "'" . addslashes($aData[$col]) . "',";
							break;

						// Country.. also set $sCountry for use later!
						case 13:
							$sCountry = $aData[$col];
							break;

						// Gender.. check for multiple possible designations from input
						case 6:
							switch(strtolower($aData[$col]))
							{
								case 'male': case 'm': case 'boy': case 'man':
									$sSQLpersonData .= "1, ";
									break;
								case 'female': case 'f': case 'girl': case 'woman':
									$sSQLpersonData .= "2, ";
									break;
								default:
									$sSQLpersonData .= "0, ";
									break;
							}
							break;

						// Donation envelope.. make sure it's available!
						case 7:
							$iEnv = FilterInput($aData[$col],'int');
							$sSQL = "SELECT '' FROM person_per WHERE per_Envelope = " . $iEnv;
							$rsTemp = RunQuery($sSQL);
							if (mysql_num_rows($rsTemp) == 0)
								$sSQLpersonData .= $iEnv . ", ";
							else
								$sSQLpersonData .= "NULL, ";
							break;

						// Birth date.. parse multiple date standards.. then split into day,month,year
						case 19:
							$sDate = $aData[$col];
							$aDate = ParseDate($sDate,$iDateMode);
                            $sSQLpersonData .= $aDate[0] . "," . $aDate[1] . "," . $aDate[2] . ",";
							break;

						// Membership date.. parse multiple date standards
						case 20:
							$sDate = $aData[$col];
							$aDate = ParseDate($sDate,$iDateMode);
							$sSQLpersonData .= "\"" . $aDate[0] . "-" . $aDate[1] . "-" . $aDate[2] . "\",";
							break;

						// Ignore field option
						case 0:

						// Phone numbers.. uh oh.. don't know country yet.. wait to do a second pass!
						case 14: case 15: case 16:
						default:
							break;

					}

					// Birthday is a special case because it is stored across 3 columns
					switch($currentType)
					{
						case 19:
							$sSQLpersonFields .= "per_BirthYear, per_BirthMonth, per_BirthDay,";
							break;
						case 0: case 13: case 14: case 15: case 16:
							break;
						default:
							$sSQLpersonFields .= $aPersonTableFields[$currentType] . ", ";
							break;
					}
				}
			}

			// Second pass at the person_per SQL.. this time we know the Country
			for ($col = 0; $col < $numCol; $col++)
			{
				// Is it not a custom field?
				if (!$aColumnCustom[$col])
				{
					$currentType = $aColumnID[$col];
					switch($currentType)
					{
						// Phone numbers..
						case 14: case 15: case 16:
							$sSQLpersonData .= "'" . addslashes(CollapsePhoneNumber($aData[$col],$sCountry)) . "',";
							$sSQLpersonFields .= $aPersonTableFields[$currentType] . ", ";
							break;
						default:
							break;
					}
				}
			}

			// Finish up the person_per SQL..
			$sSQLpersonData .= $iClassID . ",'" . addslashes($sCountry) . "',";
			$sSQLpersonData .= "'" . date("YmdHis") . "'," . $_SESSION['iUserID'] . ")";
			$sSQLpersonFields .= "per_cls_ID, per_Country, per_DateEntered, per_EnteredBy)";
			$sSQLperson = $sSQLpersonFields . $sSQLpersonData;

			RunQuery($sSQLperson);
			//echo "<br>" . $sSQLperson . "<br>";

         // Make a one-person family if requested
   		if (isset($_POST["MakeFamilyRecords"])) {
			   $sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
			   $rsPersonID = RunQuery($sSQL);
			   extract(mysql_fetch_array($rsPersonID));
			   $sSQL = "SELECT * FROM person_per WHERE per_ID = " . $iPersonID;
			   $rsNewPerson = RunQuery($sSQL);
			   extract(mysql_fetch_array($rsNewPerson));
               
               // see if there is a family with same last name and address
               $sSQL = "SELECT fam_ID, fam_name, fam_Address1 
                        FROM family_fam where fam_Name = '".addslashes($per_LastName)."' 
                        AND fam_Address1 = '".addslashes($per_Address1)."'";
               $rsExistingFamily = RunQuery($sSQL);
               $famid = 0;
               if(mysql_num_rows($rsExistingFamily) > 0)
               {
                   extract(mysql_fetch_array($rsExistingFamily));
                   $famid = $fam_ID;
               }
               else
               {
                   $sSQL = "INSERT INTO family_fam (fam_ID,
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
                                                 fam_DateEntered, 
                                                 fam_EnteredBy)
                            VALUES (NULL, " .
                                   "\"" . $per_LastName . "\", " .
                                   "\"" . $per_Address1 . "\", " .
                                   "\"" . $per_Address2 . "\", " .
                                   "\"" . $per_City . "\", " .
                                   "\"" . $per_State . "\", " .
                                   "\"" . $per_Zip . "\", " .
                                   "\"" . $per_Country . "\", " .
                                   "\"" . $per_HomePhone . "\", " .
                                   "\"" . $per_WorkPhone . "\", " .
                                   "\"" . $per_CellPhone . "\", " .
                                   "\"" . $per_Email . "\"," .
                                   "\"" . date("YmdHis") . "\"," .
                                   "\"" . $_SESSION['iUserID'] . "\");";
                   RunQuery($sSQL);
                   $famid = "LAST_INSERT_ID()"; 
               }   
               $sSQL = "UPDATE person_per SET per_fam_ID = " . $famid . " WHERE per_ID = " . $per_ID;
               RunQuery($sSQL);
         }


			if ($bHasCustom)
			{
				// Get the last inserted person ID and insert a dummy row in the person_custom table
				$sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
				$rsPersonID = RunQuery($sSQL);
				extract(mysql_fetch_array($rsPersonID));
				$sSQL = "INSERT INTO `person_custom` (`per_ID`) VALUES ('" . $iPersonID . "')";
				RunQuery($sSQL);
				//echo "<br>" . $sSQL . "<br>";

				// Build the person_custom SQL
				for ($col = 0; $col < $numCol; $col++)
				{
					// Is it a custom field?
					if ($aColumnCustom[$col])
					{
						$currentType = $aCustomTypes[$aColumnID[$col]];
						$currentFieldData = trim($aData[$col]);

						// If date, first parse it to the standard format..
						if ($currentType == 2)
						{
							$aDate = ParseDate($currentFieldData,$iDateMode);
							$currentFieldData = implode("-",$aDate);
						}
						// If boolean, convert to the expected values for custom field
						elseif ($currentType == 1)
						{
							if (strlen($currentFieldData))
								$currentFieldData = ConvertToBoolean($currentFieldData) + 1;
						}
						else
							$currentFieldData = addslashes($currentFieldData);

						// aColumnID is the custom table column name
						sqlCustomField($sSQLcustom, $currentType, $currentFieldData, $aColumnID[$col], $sCountry);
					}
				}

				// Finalize and run the update for the person_custom table.
				$sSQLcustom = substr($sSQLcustom,0,-2);
				$sSQLcustom .= " WHERE per_ID = " . $iPersonID;
				RunQuery($sSQLcustom);
				//echo "<br>" . $sSQLcustom . "<br>";
			}

			$importCount++;
		}

		fclose($pFile);

		// delete the temp file
		unlink($csvTempFile);

		$iStage = 3;
	}
	else
		echo gettext("ERROR: the uploaded CSV file no longer exists!");
}

if ($iStage == 1)
{
	// Display the select file form
	echo "<p style=\"color: red\">" . $csvError . "</p>";
	echo "<form method=\"post\" action=\"" . $_SERVER['PHP_SELF'] . "\" enctype=\"multipart/form-data\">";
	echo "<input class=\"icTinyButton\" type=\"file\" name=\"CSVfile\"> <input type=\"submit\" class=\"icButton\" value=\"" . gettext("Upload CSV File") . "\" name=\"UploadCSV\">";
	echo "</form>";
}

if ($iStage == 3)
{
	echo "<p class=\"MediumLargeText\">" . gettext("Data import successful.") . ' ' . $importCount . ' ' . gettext("persons were imported") . "</p>";
}

// Returns a date array [year,month,day]
function ParseDate($sDate,$iDateMode)
{
    $cSeparator = "";
    $sDate = trim($sDate);
    for($i=0;$i<strlen($sDate);$i++)
    {
        if(is_numeric(substr($sDate,$i,1))) continue;
        $cSeparator = substr($sDate,$i,1);
        break;
    }
    $aDate[0] = "0000";
    $aDate[1] = "00";
    $aDate[2] = "00";
    
	switch($iDateMode)
	{
		// International standard: YYYY-MM-DD
		case 1:
			// Remove separator if it exists
			if (!is_numeric($cSeparator))
				$sDate = str_replace($cSeparator,"",$sDate);
             if(strlen($sDate) == 8)
             {
                $aDate[0] = substr($sDate,0,4);
                $aDate[1] = substr($sDate,4,2);
                $aDate[2] = substr($sDate,6,2);
             }
			break;

		// MM-DD-YYYY
		case 2:
			// Remove separator if it exists and add leading 0s to m and d if needed
			if ($cSeparator!="")
            {
				$tmpDate = explode($cSeparator,$sDate);
                 $aDate[0] = strlen($tmpDate[2]) == 4 ? $tmpDate[2] : "0000";
                 $aDate[1] = strlen($tmpDate[0]) == 2 ? $tmpDate[0] : "0".$tmpDate[0];
                 $aDate[2] = strlen($tmpDate[1]) == 2 ? $tmpDate[1] : "0".$tmpDate[1];
            }
            else
            {
                if(strlen($sDate) == 8)
                {
                    $aDate[0] = substr($sDate,4,4);
                    $aDate[1] = substr($sDate,0,2);
                    $aDate[2] = substr($sDate,2,2);
                }
            }
			break;

		// DD-MM-YYYY
		case 3:
			// Remove separator if it exists and add leading 0s to m and d if needed
			if ($cSeparator!="")
            {
				$tmpDate = explode($cSeparator,$sDate);
                 $aDate[0] = strlen($tmpDate[2]) == 4 ? $tmpDate[2] : "0000";
                 $aDate[1] = strlen($tmpDate[1]) == 2 ? $tmpDate[1] : "0".$tmpDate[1];
                 $aDate[2] = strlen($tmpDate[0]) == 2 ? $tmpDate[0] : "0".$tmpDate[0];
            }
            else
            {
                if(strlen($sDate) == 8)
                {
                    $aDate[0] = substr($sDate,4,4);
                    $aDate[1] = substr($sDate,2,2);
                    $aDate[2] = substr($sDate,0,2);
                }
            }
			break;
	}
    if((0 + $aDate[0]) < 1901) $aDate[0] = "0000";
	return $aDate;
}

require "Include/Footer.php";
?>
