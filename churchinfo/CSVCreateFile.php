<?php
/*******************************************************************************
 *
 *  filename    : CSVCreateFile.php
 *  last change : 2003-06-11
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
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

// Turn ON output buffering
ob_start();

// Get Source and Format from the request object and assign them locally
$sSource = strtolower($_POST["Source"]);
$sFormat = strtolower($_POST["Format"]);
$bSkipIncompleteAddr = isset($_POST["SkipIncompleteAddr"]);
$bSkipNoEnvelope = isset($_POST["SkipNoEnvelope"]);

// Get the custom fields
if ($sFormat == "default")
{
	$sSQL = "SELECT * FROM person_custom_master ORDER BY custom_Order";
	$rsCustomFields = RunQuery($sSQL);
}

//Get family roles
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence";
$rsFamilyRoles = RunQuery($sSQL);
while ($aRow =mysql_fetch_array($rsFamilyRoles))
{
	extract($aRow);
	$familyRoles[$lst_OptionID] = $lst_OptionName;
	$roleSequence[$lst_OptionSequence] = $lst_OptionID;
}

//
// Prepare the MySQL query
//

$sJoinFamTable = " LEFT JOIN family_fam ON per_fam_ID = fam_ID ";

// If our source is the cart contents, we don't need to build a WHERE filter string
if ($sSource == "cart")
	$sWhereExt = "AND per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ")";

else
{
	// If we're filtering by groups, include the p2g2r table
	if (!empty($_POST["GroupID"])) $sGroupTable = ", person2group2role_p2g2r";

	// Prepare any extentions to the WHERE clauses
	$sWhereExt = "";
	if (!empty($_POST["Classification"]))
	{
		$count = 0;
		foreach ($_POST["Classification"] as $Cls)
		{
			$Class[$count++] = FilterInput($Cls,'int');
		}
		if ($count == 1)
		{
			if ($Class[0])
				$sWhereExt .= "AND per_cls_ID = " . $Class[0] . " ";
		}
		else
		{
			$sWhereExt .= "AND (per_cls_ID = " . $Class[0];
			for($i = 1; $i < $count; $i++)
				$sWhereExt .= " OR per_cls_ID = " . $Class[$i];
			$sWhereExt .= ") ";
			// this is silly: should be something like..  $sWhereExt .= "AND per_cls_ID IN
		}
	}

	if (!empty($_POST["FamilyRole"]))
	{
		$count = 0;
		foreach ($_POST["FamilyRole"] as $Fmr)
		{
			$Class[$count++] = FilterInput($Fmr,'int');
		}
		if ($count == 1)
		{
			if ($Class[0])
				$sWhereExt .= "AND per_fmr_ID = " . $Class[0] . " ";
		}
		else
		{
			$sWhereExt .= "AND (per_fmr_ID = " . $Class[0];
			for($i = 1; $i < $count; $i++)
			{
				$sWhereExt .= " OR per_fmr_ID = " . $Class[$i];
			}
			$sWhereExt .= ") ";
		}
	}

	if (!empty($_POST["Gender"]))
		$sWhereExt .= "AND per_Gender = " . FilterInput($_POST["Gender"],'int') . " ";

	if (!empty($_POST["GroupID"]))
	{
		$count = 0;
		foreach ($_POST["GroupID"] as $Grp)
		{
			$Class[$count++] = FilterInput($Grp,'int');
		}
		if ($count == 1)
		{
			if ($Class[0])
				$sWhereExt .= "AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID = " . $Class[0] . " ";
		}
		else
		{
			$sWhereExt .= "AND per_ID = p2g2r_per_ID AND (p2g2r_grp_ID = " . $Class[0];
			for($i = 1; $i < $count; $i++)
			{
				$sWhereExt .= " OR p2g2r_grp_ID = " . $Class[$i];
			}
			$sWhereExt .= ") ";
		}

		// This is used for individual mode to remove duplicate rows from people assigned multiple groups.
		$sGroupBy = " GROUP BY per_ID";
	}

	if (!empty($_POST["MembershipDate1"]))
		$sWhereExt .= "AND per_MembershipDate >= '" . FilterInput($_POST["MembershipDate1"],'char',10) . "' ";
	if ($_POST["MembershipDate2"] != date("Y-m-d"))
		$sWhereExt .= "AND per_MembershipDate <= '" . FilterInput($_POST["MembershipDate2"],'char',10) . "' ";

	$refDate = getdate(time());

	if (!empty($_POST["BirthDate1"]))
	{
		$sWhereExt .= "AND DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d') >= '" . FilterInput($_POST["BirthDate1"],'char',10) . "' ";
	}

	if ($_POST["BirthDate2"] != date("Y-m-d"))
	{
		$sWhereExt .= "AND DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d') <= '" . FilterInput($_POST["BirthDate2"],'char',10) . "' ";
	}

	if (!empty($_POST["AnniversaryDate1"]))
	{
		$annivStart = getdate(strtotime(FilterInput($_POST["AnniversaryDate1"])));

		// Add year to query if not in future
		if ($annivStart["year"] < date("Y") || ($annivStart["year"] == date("Y") && $annivStart["mon"] <= date("m") && $annivStart["mday"] <= date("d")))
			$sWhereExt .= "AND fam_WeddingDate >= '" . FilterInput($_POST["AnniversaryDate1"],'char',10) . "' ";
		else
			$sWhereExt .= "AND DAYOFYEAR(fam_WeddingDate) >= DAYOFYEAR('" . FilterInput($_POST["AnniversaryDate1"],'char',10) . "') ";
	}

	if ($_POST["AnniversaryDate2"] != date("Y-m-d"))
	{
		$annivEnd=getdate(strtotime(FilterInput($_POST["AnniversaryDate2"],'char',10)));

		// Add year to query if not in future
		if ($annivEnd["year"] < date("Y") || ($annivEnd["year"] == date("Y") && $annivEnd["mon"] <= date("m") && $annivEnd["mday"] <= date("d")))
			$sWhereExt .= "AND  fam_WeddingDate <= '" . FilterInput($_POST["AnniversaryDate2"],'char',10) . "' ";
		else
		{
			$refDate = getdate(strtotime($_POST["AnniversaryDate2"]));
			$sWhereExt .= "AND  DAYOFYEAR(fam_WeddingDate) <= DAYOFYEAR('" . FilterInput($_POST["AnniversaryDate2"],'char',10) . "') ";
		}
	}

	if (!empty($_POST["EnterDate1"]))
		$sWhereExt .= "AND per_DateEntered >= '" . FilterInput($_POST["EnterDate1"],'char',10) . "' ";
	if ($_POST["EnterDate2"] != date("Y-m-d"))
		$sWhereExt .= "AND per_DateEntered <= '" . FilterInput($_POST["EnterDate2"],'char',10) . "' ";
}

if ($sFormat == "addtocart")
{
	// Get individual records to add to the cart

	$sSQL = "SELECT per_ID FROM person_per $sGroupTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy";
    $sSQL .= " ORDER BY per_LastName";
    $rsLabelsToWrite = RunQuery($sSQL);
	while($aRow = mysql_fetch_array($rsLabelsToWrite))
	{
		extract($aRow);
        AddToPeopleCart($per_ID);
	}
    Redirect("CartView.php");
}
else
{
	// Build the complete SQL statement

	if ($sFormat == "rollup")
	{
		$sSQL = "(SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM person_per $sGroupTable $sJoinFamTable WHERE per_fam_ID = 0 $sWhereExt)
		UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM person_per $sGroupTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount = 1)
		UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM person_per $sGroupTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount > 1) ORDER BY SortMe";
	}
	else
	{
		$sSQL = "SELECT * FROM person_per $sGroupTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy ORDER BY per_LastName";
	}

	//Execute whatever SQL was entered
	$rsLabelsToWrite = RunQuery($sSQL);

	//Produce Header Based on Selected Fields
	if ($sFormat == "rollup")
	{
		$headerString = "\"Name\",";
	}
	else
	{
		$headerString = "\"LastName\",";
		if (!empty($_POST["Title"])) $headerString .= "\"Title\",";
		if (!empty($_POST["FirstName"])) $headerString .= "\"FirstName\",";
		if (!empty($_POST["Suffix"])) $headerString .= "\"Suffix\",";
		if (!empty($_POST["MiddleName"])) $headerString .= "\"MiddleName\",";
	}

	if (!empty($_POST["Address1"])) $headerString .= "\"Address1\",";
	if (!empty($_POST["Address2"])) $headerString .= "\"Address2\",";
	if (!empty($_POST["City"])) $headerString .= "\"City\",";
	if (!empty($_POST["State"])) $headerString .= "\"State\",";
	if (!empty($_POST["Zip"])) $headerString .= "\"Zip\",";
	if (!empty($_POST["Country"])) $headerString .= "\"Country\",";
	if (!empty($_POST["HomePhone"])) $headerString .= "\"HomePhone\",";
	if (!empty($_POST["WorkPhone"])) $headerString .= "\"WorkPhone\",";
	if (!empty($_POST["CellPhone"])) $headerString .= "\"CellPhone\",";
	if (!empty($_POST["Email"])) $headerString .= "\"Email\",";
	if (!empty($_POST["WorkEmail"])) $headerString .= "\"WorkEmail\",";
	if (!empty($_POST["Envelope"])) $headerString .= "\"Envelope Number\",";
	if (!empty($_POST["MembershipDate"])) $headerString .= "\"MembershipDate\",";


	if ($sFormat == "default")
	{
		if (!empty($_POST["BirthdayDate"])) $headerString .= "\"BirthDate\",";
		if (!empty($_POST["Age"])) $headerString .= "\"Age\",";
		if (!empty($_POST["PrintFamilyRole"])) $headerString .= "\"FamilyRole\",";
	}
	else
	{
		if (!empty($_POST["BirthdayDate"])) $headerString .= "\"AnnivDate\",";
		if (!empty($_POST["Age"])) $headerString .= "\"Anniv\",";
	}

	// Add any custom field names to the header, unless using family roll-up mode
	$bUsedCustomFields = false;
	if ($sFormat == "default")
	{
		while($aRow = mysql_fetch_array($rsCustomFields))
		{
			extract($aRow);
			if (isset($_POST["$custom_Field"]))
			{
				$bUsedCustomFields = true;
				$headerString .= "\"$custom_Name\",";
			}
		}
	}

	$headerString = substr($headerString,0,-1);
	$headerString .= "\n";

	header("Content-type: text/x-csv");
	header("Content-Disposition: attachment; filename=infocentral-export-" . date("Ymd-Gis") . ".csv");

	echo $headerString;

	while($aRow = mysql_fetch_array($rsLabelsToWrite))
	{
		$per_Title = "";
		$per_FirstName = "";
		$per_MiddleName = "";
		$per_LastName = "";
		$per_Suffix = "";
		$per_Address1 = "";
		$per_Address2 = "";
		$per_City = "";
		$per_State = "";
		$per_Zip = "";
		$per_Country = "";
		$per_HomePhone = "";
		$per_WorkPhone = "";
		$per_CellPhone = "";
		$per_Email = "";
		$per_WorkEmail = "";
		$per_Envelope = "";
		$per_MembershipDate = "";

		$per_BirthDay = "";
		$per_BirthMonth = "";
		$per_BirthYear = "";

		$fam_Address1 = "";
		$fam_Address2 = "";
		$fam_City = "";
		$fam_State = "";
		$fam_Zip = "";
		$fam_Country = "";
		$fam_HomePhone = "";
		$fam_WorkPhone = "";
		$fam_CellPhone = "";
		$fam_Email = "";
		$fam_WeddingDate = "";

		$sCountry = "";

		extract($aRow);

		// If we are doing a family roll-up, we want to favor available family address / phone numbers over the individual data returned
		if ($sFormat == "rollup")
		{
			$sPhoneCountry = SelectWhichInfo($fam_Country, $per_Country, False);
			$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), ExpandPhoneNumber($per_HomePhone,$sPhoneCountry,$dummy), False);
			$sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), ExpandPhoneNumber($per_WorkPhone,$sPhoneCountry,$dummy), False);
			$sCellPhone = SelectWhichInfo(ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), ExpandPhoneNumber($per_CellPhone,$sPhoneCountry,$dummy), False);
			$sCountry = SelectWhichInfo($fam_Country,$per_Country,False);
			SelectWhichAddress($sAddress1, $sAddress2, $fam_Address1, $fam_Address2, $per_Address1, $per_Address2, False);
			$sCity = SelectWhichInfo($fam_City,$per_City,False);
			$sState = SelectWhichInfo($fam_State,$per_State,False);
			$sZip = SelectWhichInfo($fam_Zip,$per_Zip,False);
			$sEmail = SelectWhichInfo($fam_Email,$per_Email,False);
		}
		// Otherwise, the individual data gets precedence over the family data
		else
		{
			$sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, False);
			$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), False);
			$sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), False);
			$sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), False);
			$sCountry = SelectWhichInfo($per_Country,$fam_Country,False);
			SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, False);
			$sCity = SelectWhichInfo($per_City,$fam_City,False);
			$sState = SelectWhichInfo($per_State,$fam_State,False);
			$sZip = SelectWhichInfo($per_Zip,$fam_Zip,False);
			$sEmail = SelectWhichInfo($per_Email,$fam_Email,False);
		}

		// Check if we're filtering out people with incomplete addresses
		if (!($bSkipIncompleteAddr && (strlen($sCity) == 0 || strlen($sState) == 0 || strlen($sZip) == 0 || (strlen($sAddress1) == 0 && strlen($sAddress2) == 0))))
		{
			// Check if we're filtering out people with no envelope number assigned
			// ** should move this to the WHERE clause
			if (!($bSkipNoEnvelope && (strlen($per_Envelope) == 0)))
			{
				// If we are doing family roll-up, we use a single, formatted name field
				if ($sFormat == "default")
				{
					$sString = "\"" . $per_LastName;
					if (isset($_POST["Title"])) $sString .= "\",\"" . $per_Title;
					if (isset($_POST["FirstName"])) $sString .= "\",\"" . $per_FirstName;
					if (isset($_POST["Suffix"])) $sString .= "\",\"" . $per_Suffix;
					if (isset($_POST["MiddleName"])) $sString .= "\",\"" . $per_MiddleName;
				}
				else if ($sFormat == "rollup")
				{
					if ($memberCount > 1)
						$sString = "\"" . $fam_Name . " Family";
					else
						$sString = "\"" . $per_LastName . ", " . $per_FirstName;
				}

				if (isset($_POST["Address1"])) $sString .= "\",\"" . $sAddress1;
				if (isset($_POST["Address2"])) $sString .= "\",\"" . $sAddress2;
				if (isset($_POST["City"])) $sString .= "\",\"" . $sCity;
				if (isset($_POST["State"])) $sString .= "\",\"" . $sState;
				if (isset($_POST["Zip"])) $sString .= "\",\"" . $sZip;
				if (isset($_POST["Country"])) $sString .= "\",\"" . $sCountry;
				if (isset($_POST["HomePhone"])) $sString .= "\",\"" . $sHomePhone;
				if (isset($_POST["WorkPhone"])) $sString .= "\",\"" . $sWorkPhone;
				if (isset($_POST["CellPhone"])) $sString .= "\",\"" . $sCellPhone;
				if (isset($_POST["Email"])) $sString .= "\",\"" . $sEmail;
				if (isset($_POST["WorkEmail"])) $sString .= "\",\"" . $per_WorkEmail;
				if (isset($_POST["Envelope"])) $sString .= "\",\"" . $per_Envelope;
				if (isset($_POST["MembershipDate"])) $sString .= "\",\"" . $per_MembershipDate;

				if ($sFormat == "default")
				{
					if (isset($_POST["BirthdayDate"]))
					{
						$sString .= "\",\"";
						if ($per_BirthYear != '')
							$sString .= $per_BirthYear . "-";
						else
							$sString .= "0000-";
						$sString .= $per_BirthMonth . "-" . $per_BirthDay;
					}

					if (isset($_POST["Age"]))
					{
						if (isset($per_BirthYear))
							$age = $refDate["year"] - $per_BirthYear - ($per_BirthMonth > $refDate["mon"] || ($per_BirthMonth == $refDate["mon"] && $per_BirthDay > $refDate["mday"]));
						else
							$age = "";

						$sString .= "\",\"" . $age;
					}

					if (isset($_POST["PrintFamilyRole"]))
					{
						$sString .= "\",\"" . $familyRoles[$per_fmr_ID];
					}
				}
				else
				{
					if (isset($_POST["BirthdayDate"]))
					{
						$sString .= "\",\"" . $fam_WeddingDate;
					}

					if (isset($_POST["Age"]))
					{
						if (isset($fam_WeddingDate))
						{
							$annivDate = getdate(strtotime($fam_WeddingDate));
							$age = $refDate["year"] - $annivDate["year"] - ($annivDate["mon"] > $refDate["mon"] || ($annivDate["mon"] == $refDate["mon"] && $annivDate["mday"] > $refDate["mday"]));
						}
						else
							$age = "";

						$sString .= "\",\"" . $age;
					}
				}

				if ($bUsedCustomFields)
				{
					$sSQLcustom = "SELECT * FROM person_custom WHERE per_ID = " . $per_ID;
					$rsCustomData = RunQuery($sSQLcustom);
					$aCustomData = mysql_fetch_array($rsCustomData);

					// Write custom field data
					mysql_data_seek($rsCustomFields,0);
					while($aCustomField = mysql_fetch_array($rsCustomFields))
					{
						$custom_Field = "";
						$custom_Special = "";
						$type_ID = "";

						extract($aCustomField);
						if (isset($_POST["$custom_Field"]))
						{
							if ($type_ID == 11) $custom_Special = $sCountry;
							$sString .= "\",\"" . displayCustomField($type_ID, trim($aCustomData[$custom_Field]), $custom_Special);
						}
					}
				}
				$sString .= "\"\n";
				echo $sString;
			}
		}
	}
}


// Turn OFF output buffering
ob_end_flush();

?>
