<?php
/*******************************************************************************
*
*  filename    : SelectList.php
*  website     : http://www.churchdb.org
*  copyright   : Copyright 2001-2003 Deane Barker and Chris Gebhardt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

$iTenThousand = 10000;  // Constant used to offset negative choices in drop down lists

// Create array with Classification Information (lst_ID = 1)
$sClassSQL  = "SELECT * FROM list_lst WHERE lst_ID=1 ORDER BY lst_OptionSequence";
$rsClassification = RunQuery($sClassSQL);
unset($aClassificationName);
$aClassificationName[0] = "Unassigned";
while ($aRow = mysql_fetch_array($rsClassification))
{
	extract($aRow);
	$aClassificationName[intval($lst_OptionID)]=$lst_OptionName;
}

// Create array with Family Role Information (lst_ID = 2)
$sFamRoleSQL  = "SELECT * FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence";
$rsFamilyRole = RunQuery($sFamRoleSQL);
unset($aFamilyRoleName);
$aFamilyRoleName[0] = "Unassigned";
while ($aRow = mysql_fetch_array($rsFamilyRole))
{
	extract($aRow);
	$aFamilyRoleName[intval($lst_OptionID)]=$lst_OptionName;
}

// Create array with Group Type Information (lst_ID = 3)
$sGroupTypeSQL  = "SELECT * FROM list_lst WHERE lst_ID=3 ORDER BY lst_OptionSequence";
$rsGroupTypes = RunQuery($sGroupTypeSQL);
unset($aGroupTypes);
while ($aRow = mysql_fetch_array($rsGroupTypes))
{
	extract($aRow);
	$aGroupTypes[intval($lst_OptionID)]=$lst_OptionName;
}

// Filter received user input as needed
if (strlen($_GET["Sort"]))
        $sSort = FilterInput($_GET["Sort"]);
else
        $sSort = "name";

if (isset($_GET["PrintView"])) 
	$bPrintView = true;
if (strlen($_GET["Filter"]))
	$sFilter = FilterInput($_GET["Filter"]);
if (strlen($_GET["Letter"]))
	$sLetter = strtoupper(FilterInput($_GET["Letter"],'char'));


if (strlen($_GET["mode"]))
	$sMode = FilterInput($_GET["mode"]);
elseif (isset($_SESSION['SelectListMode']))
	$sMode = $_SESSION['SelectListMode'];

switch ($sMode)
	{
	case ('groupassign'):
		$_SESSION['SelectListMode'] = $sMode;
		break;
	case ('family'):
		$_SESSION['SelectListMode'] = $sMode;
		break;
	default:
		$_SESSION['SelectListMode'] = 'person';
		break;
	}

// Save default search mode
$_SESSION['bSearchFamily'] = ($sMode != 'person');



if (isset($_GET["Number"]))
{
    $_SESSION['SearchLimit'] = FilterInput($_GET["Number"],'int');
    $uSQL =	" UPDATE user_usr SET usr_SearchLimit = " . $_SESSION['SearchLimit'] .
			" WHERE usr_per_ID = " . $_SESSION['iUserID'];
    $rsUser = RunQuery($uSQL);
}

if (isset($_GET["PersonColumn3"])){
	$_SESSION['sPersonColumn3'] = FilterInput($_GET["PersonColumn3"]);
}

if (isset($_GET["PersonColumn5"])){
	$_SESSION['sPersonColumn5'] = FilterInput($_GET["PersonColumn5"]);
}


// Set the page title
if ($sMode == 'person')
{
    $sPageTitle = gettext("Person Listing");
    $iMode = 1;

	if (strlen($_GET["Classification"]))
		$iClassification = FilterInput($_GET["Classification"],'int');
	if (strlen($_GET["FamilyRole"]))
		$iFamilyRole = FilterInput($_GET["FamilyRole"],'int');
    if (strlen($_GET["Gender"])) 
		$iGender = FilterInput($_GET["Gender"],'int');
    if (strlen($_GET["grouptype"]))
    {
        $iGroupType = FilterInput($_GET["grouptype"],'int');
        if (strlen($_GET["groupid"]))
        {
            $iGroupID = FilterInput($_GET["groupid"],'int');
            if ($iGroupID == 0) unset($iGroupID);
        }
        if (strlen($_GET["grouproleid"]))
        {
            $iRoleID = FilterInput($_GET["grouproleid"],'int');
            if ($iRoleID == 0) unset($iRoleID);
        }
    }
}
elseif ($sMode == 'groupassign')
{
    $sPageTitle = gettext("Group Assignment Helper");
    $iMode = 2;

	if (strlen($_GET["Classification"]))
		$iClassification = FilterInput($_GET["Classification"],'int');
	if (strlen($_GET["FamilyRole"]))
		$iFamilyRole = FilterInput($_GET["FamilyRole"],'int');
    if (strlen($_GET["Gender"]))
		$iGender = FilterInput($_GET["Gender"],'int');
    if (isset($_GET["type"]))
        $iGroupTypeMissing = FilterInput($_GET["type"],'int');
    else
        $iGroupTypeMissing = 1;
}
else
{
    $sPageTitle = gettext("Family Listing");
    $iMode = 3;
}

$iPerPage = $_SESSION['SearchLimit'];

if (!$PrintView)
		$sHeaderFile = "Include/Header.php";
else
		$sHeaderFile = "Include/Header-Short.php";

if ($iMode == 1 || $iMode == 2)
{
    // SQL for group-assignment helper
    if ($iMode == 2)
    {
    	$sBaseSQL = "SELECT *, IF(LENGTH(per_Zip) > 0,per_Zip,fam_Zip) AS zip " .
					"FROM person_per LEFT JOIN family_fam " .
					"ON person_per.per_fam_ID = family_fam.fam_ID ";

        // Find people who are part of a group of the specified type.
        // MySQL doesn't have subqueries until version 4.1.. for now, do it the hard way
        $sSQLsub =	"SELECT per_ID FROM person_per LEFT JOIN person2group2role_p2g2r " .
					"ON p2g2r_per_ID = per_ID LEFT JOIN group_grp " .
					"ON grp_ID = p2g2r_grp_ID " .
					"WHERE grp_Type = $iGroupTypeMissing GROUP BY per_ID";
        $rsSub = RunQuery($sSQLsub);

        if (mysql_num_rows($rsSub) > 0)
        {
            $sExcludedIDs = "";
            while ($aTemp = mysql_fetch_row($rsSub))
            {
                $sExcludedIDs .= $aTemp[0] . ",";
            }
            $sExcludedIDs = substr($sExcludedIDs,0,-1);
            $sGroupWhereExt = " AND per_ID NOT IN (" . $sExcludedIDs . ")";
        }
 	}

    // SQL for standard Person List
    if ($iMode == 1)
    {
        // Set the base SQL
        $sBaseSQL =	"SELECT *, IF(LENGTH(per_Zip) > 0,per_Zip,fam_Zip) AS zip " .
					"FROM person_per LEFT JOIN family_fam " .
					"ON per_fam_ID = family_fam.fam_ID ";

		$sGroupWhereExt = ""; // Group Filtering Logic
		$sJoinExt = "";
		if (isset($iGroupType))
		{
			if ($iGroupType >= 0)
			{	
				$sJoinExt = " LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID ".
							" LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID ";
				$sGroupWhereExt =	" AND grp_type = ".$iGroupType." ";

				if (isset($iGroupID))
					if ($iGroupID >= 0)
					{
                        if (isset($iRoleID))
                        {
                            $sJoinExt = " LEFT JOIN person2group2role_p2g2r ".
                                        " ON per_ID = p2g2r_per_ID ".
                                        " LEFT JOIN list_lst ".
                                        " ON p2g2r_grp_ID = lst_ID ";
                            $sGroupWhereExt =   " AND p2g2r_grp_ID=".$iGroupID." " .
                                                " AND p2g2r_per_ID=per_ID " .
                                                " AND p2g2r_rle_ID=".$iRoleID." ";
                        }
                        else
                        {
                            $sJoinExt =	" LEFT JOIN person2group2role_p2g2r ".
                                        " ON per_ID = p2g2r_per_ID ";
                            $sGroupWhereExt =	" AND p2g2r_grp_ID=".$iGroupID." ".
                                                " AND p2g2r_per_ID = per_ID ";
                        }
					}
					else
					{
						$sJoinExt =	" LEFT JOIN person2group2role_p2g2r ".
									" ON per_ID = p2g2r_per_ID " .
									" LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID ";
						$sGroupWhereExt =	" AND grp_type=".$iGroupType." ".
											" AND per_ID NOT IN ".
                                            " (SELECT p2g2r_per_ID FROM person2group2role_p2g2r ".
                                            "  WHERE p2g2r_grp_ID=".($iGroupID+$iTenThousand).") ";
					}
 			}
			else
			{
				$sJoinExt = " LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID ".
							" LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID ";
				$sGroupWhereExt =	" AND (grp_type!=".($iGroupType+$iTenThousand)." ".
											"OR p2g2r_grp_ID IS NULL)";
			}
		}
    }

    if (isset($sFilter))
    {
        // Check if there's a space
        if (strstr($sFilter," "))
        {
            // break on the space...
            $aFilter = explode(" ",$sFilter);

            // use the results to check the first and last names
            $sFilterWhereExt =	" AND per_FirstName LIKE '%" . $aFilter[0] . "%' " .
								" AND per_LastName LIKE '%" . $aFilter[1] . "%' ";
        } else
		{
            $sFilterWhereExt =	" AND (per_FirstName LIKE '%" . $sFilter . "%' " .
								" OR per_LastName LIKE '%" . $sFilter . "%') ";
		}
    }

	$sClassificationWhereExt = "";
	if (isset($iClassification))
		if ($iClassification >= 0)
			$sClassificationWhereExt = " AND per_cls_ID=".$iClassification." ";
		else
			$sClassificationWhereExt = " AND per_cls_ID!=".
											($iClassification+$iTenThousand)." ";
	
	$sFamilyRoleWhereExt = "";
	if (isset($iFamilyRole))
		if ($iFamilyRole >= 0)
			$sFamilyRoleWhereExt = " AND per_fmr_ID=".$iFamilyRole." ";
		else
			$sFamilyRoleWhereExt = " AND per_fmr_ID!=".($iFamilyRole+$iTenThousand)." ";

    if (isset($iGender))
        $sGenderWhereExt = " AND per_Gender = " . $iGender;
	else
		$sGenderWhereExt = "";

    if (isset($sLetter))
        $sLetterWhereExt = " AND per_LastName LIKE '" . $sLetter . "%'";
	else
		$sLetterWhereExt = "";

	$sGroupBySQL = " GROUP BY per_ID";

    $sWhereExt =	$sGroupWhereExt . $sFilterWhereExt . $sClassificationWhereExt .
					$sFamilyRoleWhereExt . $sGenderWhereExt . $sLetterWhereExt;

    $sSQL = $sBaseSQL . $sJoinExt . " WHERE 1" . $sWhereExt . $sGroupBySQL;


	// URL to redirect back to this same page
	$sRedirect = "SelectList.php?";
	if (isset($_GET["mode"])) $sRedirect .= "mode=" . $_GET["mode"] . "&";
	if (isset($_GET["type"])) $sRedirect .= "type=" . $_GET["type"] . "&";
	if (isset($_GET["Filter"])) $sRedirect .= "Filter=" . $_GET["Filter"] . "&";
	if (isset($_GET["Sort"])) $sRedirect .= "Sort=" . $_GET["Sort"] . "&";
	if (isset($_GET["Letter"])) $sRedirect .= "Letter=" . $_GET["Letter"] . "&";
	if (isset($_GET["Classification"])) $sRedirect .= "Classification=" . $_GET["Classification"] . "&";
	if (isset($_GET["FamilyRole"])) $sRedirect .= "FamilyRole=" . $_GET["FamilyRole"] . "&";
	if (isset($_GET["Gender"])) $sRedirect .= "Gender=" . $_GET["Gender"] . "&";
	if (isset($_GET["grouptype"])) $sRedirect .= "grouptype=" . $_GET["grouptype"] . "&";
	if (isset($_GET["groupid"])) $sRedirect .= "groupid=" . $_GET["groupid"] . "&";
	if (isset($_GET["grouproleid"])) $sRedirect .= "grouproleid=" . $_GET["grouproleid"] . "&";
	if (isset($_GET["Number"])) $sRedirect .= "Number=" . $_GET["Number"] . "&";
	if (isset($_GET["Result_Set"])) $sRedirect .= "Result_Set=" . $_GET["Result_Set"] . "&";

	$sRedirect = substr($sRedirect,0,-1); // Chop off last &

    // If AddToCart submit button was used, run the query, add people to cart, and view cart
    if (isset($_GET["AddAllToCart"]))
    {
        $rsPersons = RunQuery($sSQL);
        while ($aRow = mysql_fetch_row($rsPersons))
        {
            AddToPeopleCart($aRow[0]);
        }

    } elseif (isset($_GET["IntersectCart"]))
    {
        $rsPersons = RunQuery($sSQL);
        while ($aRow = mysql_fetch_row($rsPersons))
	        $aItemsToProcess[] = $aRow[0];

        if (isset($_SESSION['aPeopleCart']))
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'],$aItemsToProcess);

    } elseif (isset($_GET["RemoveFromCart"]))
    {
		$rsPersons = RunQuery($sSQL);
        while ($aRow = mysql_fetch_row($rsPersons))
        	$aItemsToProcess[] = $aRow[0];

        if (isset($_SESSION['aPeopleCart']))
        	$_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'],$aItemsToProcess);

	}

    // Get the total number of persons
    $rsPer = RunQuery($sSQL);
    $Total = mysql_num_rows($rsPer);

    // Select the proper sort SQL
    switch($sSort)
    {
        case "family":
                $sOrderSQL = " ORDER BY fam_Name";
                break;
        case "zip":
                $sOrderSQL = " ORDER BY zip, per_LastName, per_FirstName";
                break;
        case "entered":
                $sOrderSQL = " ORDER BY per_DateEntered DESC";
                break;
        case "edited";
                $sOrderSQL = " ORDER BY per_DateLastEdited DESC";
                break;
        default:
                $sOrderSQL = " ORDER BY per_LastName, per_FirstName";
                break;
    }

    // Regular PersonList display
    if (!$bPrintView)
    {
        // Append a LIMIT clause to the SQL statement
        if (empty($_GET['Result_Set']))
            $Result_Set = 0;
        else
            $Result_Set = FilterInput($_GET['Result_Set'],'int');

        $sLimitSQL .= " LIMIT $Result_Set, $iPerPage";

        // Run the query with order and limit to get the final result for this list page
        $finalSQL = $sSQL . $sOrderSQL . $sLimitSQL;
        $rsPersons = RunQuery($finalSQL);

        // Run query to get first letters of last name.
		$sSQL = "SELECT DISTINCT LEFT(per_LastName,1) AS letter FROM person_per ".
				$sJoinExt . " WHERE 1 $sWhereExt ORDER BY letter";
		$rsLetters = RunQuery($sSQL);

		require "$sHeaderFile";

        echo "<form method=\"get\" action=\"SelectList.php\" name=\"PersonList\">";

        if ($iMode == 1)
        {
            echo "<p align=\"center\">";
            if ($_SESSION['bAddRecords'])
			{
            	echo "<a href=\"PersonEditor.php\">";
				echo gettext("Add a New Person Record") . "</a><BR>";
			}

            echo "<a href=\"SelectList.php?mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID";
            if($sSort) 
				echo "&Sort=$sSort";

            echo "&Letter=$sLetter&PrintView=1\">" . gettext("View Printable Page of this Listing") . "</a>";
        } else
		{
        	$sSQLtemp = "SELECT * FROM list_lst WHERE lst_ID = 3";
            $rsGroupTypes = RunQuery($sSQLtemp);
            echo '<p align="center" class="MediumText">' . gettext("Show people <b>not</b> in this group type:");
            echo '<select name="type" onchange="this.form.submit()">';
            while ($aRow = mysql_fetch_array($rsGroupTypes))
            {
                extract($aRow);
                echo '<option value="' . $lst_OptionID . '"';
                if ($iGroupTypeMissing == $lst_OptionID) { echo ' selected'; }
                echo '>' . $lst_OptionName . '&nbsp;';
            }
            echo "</select></p>";
        }
                
		?>

                <table align="center"><tr><td align="center">
                <?php echo gettext("Sort order:"); ?>
                <select name="Sort" onchange="this.form.submit()">
                        <option value="name" <?php if ($sSort == "name" || empty($sSort)) echo "selected";?>><?php echo gettext("By Name"); ?></option>
                        <option value="family" <?php if ($sSort == "family") echo "selected";?>><?php echo gettext("By Family"); ?></option>
                        <option value="zip" <?php if ($sSort == "zip") echo "selected";?>><?php echo gettext("By ZIP/Postal Code"); ?></option>
                        <option value="entered" <?php if ($sSort == "entered") echo "selected";?>><?php echo gettext("By Newest Entries"); ?></option>
                        <option value="edited" <?php if ($sSort == "edited") echo "selected";?>><?php echo gettext("By Recently Edited"); ?></option>

                </select>&nbsp;
                <input type="text" name="Filter" value="<?php echo $sFilter;?>">
                <input type="hidden" name="mode" value="<?php echo $sMode;?>">
                <input type="hidden" name="Letter" value="<?php echo $sLetter;?>">
                <input type="submit" class="icButton" <?php echo 'value="' . gettext("Apply Filter") . '"'; ?>>

                </td></tr>
				<?php

				echo '	<tr><td align="center">
						<select name="Gender" onchange="this.form.submit()">
						<option value="" ';
				if (!isset($iGender)) 
					echo " selected "; 
				echo '> ' . gettext ("Male & Female") . '</option>';

				echo '<option value="1"'; 
				if ($iGender == 1) 
					echo ' selected ';
				echo '> ' . gettext ("Male") . '</option>';

				echo '<option value="2"'; 
				if ($iGender == 2)
					echo ' selected ';
				echo '> ' . gettext ("Female") . '</option></select>';

				// **********
				// Classification drop down list
				echo '	<select name="Classification" onchange="this.form.submit()">
						<option value="" ';
				if (!isset($iClassification)) 
					echo ' selected ';
				echo '>' . gettext("All Classifications") . '</option>';

				foreach ($aClassificationName as $key => $value) 
				{
					echo '<option value="'.$key.'"';
					if (isset($iClassification))
						if ($iClassification == $key) 
							echo ' selected ';
					echo '>'.$value.'</option>';
				}

				foreach ($aClassificationName as $key => $value) 
				{
					echo '<option value="'.($key-$iTenThousand).'"';
					if (isset($iClassification))
						if ($iClassification == ($key-$iTenThousand)) 
							echo ' selected ';
					echo '>! '.$value.'</option>';
				}
				echo '</select>';

				// **********				
				// Family Role Drop Down Box
				echo '<select name="FamilyRole" onchange="this.form.submit()">';
				echo '<option value="" ';
				if (!isset($iFamilyRole)) 
					echo ' selected ';
				echo '>' . gettext("All Family Roles") . '</option>';

				foreach ($aFamilyRoleName as $key => $value) 
				{
					echo '<option value="'.$key.'"';
					if (isset($iFamilyRole))
						if ($iFamilyRole == $key)
							echo ' selected ';
					echo '>'.$value.'</option>';
				}

				foreach ($aFamilyRoleName as $key => $value)
				{
					echo '<option value="'.($key-$iTenThousand).'"';
					if (isset($iFamilyRole))
						if ($iFamilyRole == ($key-$iTenThousand))
							echo ' selected ';
					echo '>! '.$value.'</option>';
				}

				echo '</select>';

				//grouptype drop down box
                if ($iMode == 1) {

				echo '<select name="grouptype" onchange="this.form.submit()">';

				echo '<option value="" ';
				if (!isset($iGroupType)) 
					echo ' selected ';
				echo '>' . gettext("All Group Types") . '</option>';

 				foreach ($aGroupTypes as $key => $value) 
				{
					echo '<option value="'.$key.'"';
					if (isset($iGroupType))
						if ($iGroupType == $key)
							echo ' selected ';
					echo '>'.$value.'</option>';
				}

 				foreach ($aGroupTypes as $key => $value) 
				{
					echo '<option value="'.($key-$iTenThousand).'"';
					if (isset($iGroupType))
						if ($iGroupType == ($key-$iTenThousand))
							echo ' selected ';
					echo '>! '.$value.'</option>';
				}
				echo '</select>';


				if (isset($iGroupType) && ($iGroupType > -1))
				{
					// Create array with Group Information
					$sGroupsSQL  =	"SELECT * FROM group_grp WHERE grp_Type = $iGroupType " .
									"ORDER BY grp_Name ";

					$rsGroups = RunQuery($sGroupsSQL);
					unset($aGroupNames);
					while ($aRow = mysql_fetch_array($rsGroups))
					{
						extract($aRow);
						$aGroupNames[intval($grp_ID)]=$grp_Name;
					}

					echo '	<select name="groupid" onchange="this.form.submit()">
							<option value="" ';
					if (!isset($iGroupType)) echo ' selected ';
					echo '>' . gettext("All Groups") . '</option>';

 					foreach ($aGroupNames as $key => $value) 
					{
						echo '<option value="'.$key.'"';
						if (isset($iGroupType))
							if ($iGroupID == $key)
								echo ' selected ';
						echo '>'.$value.'</option>';
					}

 					foreach ($aGroupNames as $key => $value) 
					{
						echo '<option value="'.($key-$iTenThousand).'"';
						if (isset($iGroupType))
							if ($iGroupID == ($key-$iTenThousand))
								echo ' selected ';
						echo '>! '.$value.'</option>';
					}
					echo '</select>';
				}

                // *********    
                // Create Group Role drop down box
				if (isset($iGroupID) && ($iGroupID > -1))
				{

                    // Get the group's role list ID
                    $sSQL = "SELECT grp_RoleListID ".
                            "FROM group_grp WHERE grp_ID =" . $iGroupID;
                    $aTemp = mysql_fetch_array(RunQuery($sSQL));
                    $iRoleListID = $aTemp[0];

                    // Get the roles
                    $sSQL = "SELECT * FROM list_lst WHERE lst_ID = " . $iRoleListID .
                            " ORDER BY lst_OptionSequence";
                    $rsRoles = RunQuery($sSQL);
					unset($aGroupRoles);
					while ($aRow = mysql_fetch_array($rsRoles))
					{
						extract($aRow);
						$aGroupRoles[intval($lst_OptionID)]=$lst_OptionName;
					}

					echo '	<select name="grouproleid" onchange="this.form.submit()">
							<option value="" ';
					if (!isset($iRoleID)) echo ' selected ';
					echo '>' . gettext("All Roles") . '</option>';

 					foreach ($aGroupRoles as $key => $value) 
					{
						echo '<option value="'.$key.'"';
						if (isset($iRoleID))
							if ($iRoleID == $key)
								echo ' selected ';
						echo '>'.$value.'</option>';
					}
                    /*
 					foreach ($aGroupNames as $key => $value) 
					{
						echo '<option value="'.($key-$iTenThousand).'"';
						if (isset($iGroupType))
							if ($iGroupID == ($key-$iTenThousand))
								echo ' selected ';
						echo '>! '.$value.'</option>';
					}*/
					echo '</select>';
				}



			} ?>

                        <input type="button" class="icButton" value="<?php echo gettext("Clear Filters"); ?>" onclick="javascript:document.location='SelectList.php?mode=<?php echo $sMode; ?>&Sort=<?php echo $sSort; ?>&type=<?php echo $iGroupTypeMissing; ?>'"><BR><BR>

                        <input name="AddAllToCart" type="submit" class="icButton" <?php echo 'value="' . gettext("Add to Cart") . '"'; ?>>&nbsp;
                        <input name="IntersectCart" type="submit" class="icButton" <?php echo 'value="' . gettext("Intersect with Cart") . '"'; ?>>&nbsp;
                        <input name="RemoveFromCart" type="submit" class="icButton" <?php echo 'value="' . gettext("Remove from Cart") . '"'; ?>>

                        </td></tr>
						</form></table>
                <?php

                // Create Sort Links
                echo '<div align="center">';
                echo "<a href=\"SelectList.php?mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID";
                if($sSort) echo "&Sort=$sSort";
                echo "\">" . gettext("View All") . "</a>";
                while ($aLetter = mysql_fetch_row($rsLetters))
                {
						$aLetter[0] = strtoupper($aLetter[0]);
                        if ($aLetter[0] == $sLetter) {
                                echo "&nbsp;&nbsp;|&nbsp;&nbsp;" . $aLetter[0];
                        } else {
                                echo "&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"SelectList.php?mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID";
                                if($sSort) echo "&Sort=$sSort";
                                echo "&Letter=" . $aLetter[0] . "\">" . $aLetter[0] . "</a>";
                        }
                }
                echo "</div><BR>";

                // Create Next / Prev Links and $Result_Set Value
                if ($Total > 0)
                {
                        echo "<div align=\"center\">";
                        echo "<form method=\"get\" action=\"SelectList.php\" name=\"ListNumber\">";

                        // Show previous-page link unless we're at the first page
                        if ($Result_Set < $Total && $Result_Set > 0)
                        {
                                $thisLinkResult = $Result_Set - $iPerPage;
                                echo "<a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Sort=$sSort&Letter=$sLetter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID\">". gettext("Previous Page") . "</A>&nbsp;&nbsp;";
                        }

                        // Calculate starting and ending Page-Number Links
                        $Pages = ceil($Total / $iPerPage);
                        $startpage =  (ceil($Result_Set / $iPerPage)) - 6;
                        if ($startpage <= 2)
                                $startpage = 1;
                        $endpage = (ceil($Result_Set / $iPerPage)) + 9;
                        if ($endpage >= ($Pages - 1))
                                $endpage = $Pages;

                        // Show Link "1 ..." if startpage does not start at 1
                        if ($startpage != 1)
                                echo "<a href=\"SelectList.php?Result_Set=0&mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Sort=$sSort&Letter=$sLetter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID\">1</a> ... \n";

                        // Display page links
                        if ($Pages > 1)
                        {
                                for ($c = $startpage; $c <= $endpage; $c++)
                                {
                                        $b = $c - 1;
                                        $thisLinkResult = $iPerPage * $b;
                                        if ($thisLinkResult != $Result_Set)
                                                echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Sort=$sSort&Letter=$sLetter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID\">$c</a>&nbsp;\n";
                                        else
                                                echo "&nbsp;&nbsp;[ " . $c . " ]&nbsp;&nbsp;";
                                }
                        }

                        // Show Link "... xx" if endpage is not the maximum number of pages
                        if ($endpage != $Pages)
                        {
                                $thisLinkResult = ($Pages - 1) * $iPerPage;
                                echo " ... <a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Sort=$sSort&Letter=$sLetter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID\">$Pages</a>\n";
                        }
                        // Show next-page link unless we're at the last page
                        if ($Result_Set >= 0 && $Result_Set < $Total)
                        {
                                $thisLinkResult=$Result_Set+$iPerPage;
                                if ($thisLinkResult<$Total)
                                        echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=$sMode&type=$iGroupTypeMissing&Filter=$sFilter&Sort=$sSort&Letter=$sLetter&Classification=$iClassification&FamilyRole=$iFamilyRole&Gender=$iGender&grouptype=$iGroupType&groupid=$iGroupID&grouproleid=$iRoleID\">" . gettext("Next Page") . "</a>";
                        }

                        echo "<input type=\"hidden\" name=\"mode\" value=\"";
						echo $sMode . "\">";
                        if($iGroupTypeMissing > 0) {
                            echo "<input type=\"hidden\" name=\"type\" value=\"";
							echo $iGroupTypeMissing . "\">"; }
                        if(isset($sFilter)) {
                            echo "<input type=\"hidden\" name=\"Filter\" value=\"";
							echo $sFilter . "\">"; }
                        if(isset($sSort)) {
                            echo "<input type=\"hidden\" name=\"Sort\" value=\"";
							echo $sSort . "\">"; }
                        if(isset($sLetter)) {
                            echo "<input type=\"hidden\" name=\"Letter\" value='";
							echo $sLetter . "'\">"; }
                        if(isset($iClassification)) {
                            echo "<input type=\"hidden\" name=\"Classification\" value='";
							echo $iClassification ."'\">"; }
                        if(isset($iFamilyRole)) {
                            echo "<input type=\"hidden\" name=\"FamilyRole\" value='";
							echo $iFamilyRole ."'\">"; }
                        if(isset($iGender)) {
                            echo "<input type=\"hidden\" name=\"Gender\" value='";
							echo $iGender ."'\">"; }
                        if(isset($iGroupType)) {
                            echo "<input type=\"hidden\" name=\"grouptype\" value='";
							echo $iGroupType ."'\">"; }
                        if(isset($iGroupID)) {
                            echo "<input type=\"hidden\" name=\"groupid\" value='";
							echo $iGroupID ."'\">"; }

                        // Display record limit per page
                        if ($_SESSION['SearchLimit'] == "5")
                                $sLimit5 = "selected";
                        if ($_SESSION['SearchLimit'] == "10")
                                $sLimit10 = "selected";
                        if ($_SESSION['SearchLimit'] == "20")
                                $sLimit20 = "selected";
                        if ($_SESSION['SearchLimit'] == "25")
                                $sLimit25 = "selected";
                        if ($_SESSION['SearchLimit'] == "50")
                                $sLimit50 = "selected";

                        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". gettext("Display:") . "&nbsp;
				<select class=\"SmallText\" name=\"Number\" onchange=\"this.form.submit()\">
                                <option value=\"5\" $sLimit5>5</option>
                                <option value=\"10\" $sLimit10>10</option>
                                <option value=\"20\" $sLimit20>20</option>
                                <option value=\"25\" $sLimit25>25</option>
                                <option value=\"50\" $sLimit50>50</option>
				</select>&nbsp;
                        </form>
                        </div>
                        <BR>";
                 } ?>

<?php

// At this point we have finished the forms at the top of SelectList.  
// Now begin the table displaying results.

// Read if sort by person is selected columns 3 and 5 are user selectable.  If the
// user has not selected a value then read from session variable.
if (!isset($sPersonColumn3)) {
	switch ($_SESSION['sPersonColumn3']) 
	{
	case ("Family Role"):
		$sPersonColumn3 = "Family Role";
		break;
	case ("Gender"):
		$sPersonColumn3 = "Gender";
		break;
	default:
		$sPersonColumn3 = "Classification";
	break;
	}
}

if (!isset($sPersonColumn5)) {
	switch ($_SESSION['sPersonColumn5']) 
	{
	case ("Home Phone"):
		$sPersonColumn5 = "Home Phone";
		break;
	case ("Work Phone"):
		$sPersonColumn5 = "Work Phone";
		break;
    case ("Mobile Phone"):
        $sPersonColumn5 = "Mobile Phone";
        break;
	default:
		$sPersonColumn5 = "Zip/Postal Code";
	break;
	}
}


// Results table begins here
echo '<table cellpadding="4" align="center" cellspacing="0" width="100%">';
echo '<tr class="TableHeader">';
                                
if ($_SESSION['bEditRecords']) 
    echo '<td width="25">' . gettext("Edit") . '</td>';

echo '<td><a href="SelectList.php?mode=' .$sMode. '&type=' .$iGroupTypeMissing;
echo '&Sort=name&Filter=' .$sFilter. '">' . gettext("Name") . '</a></td>';

echo '<form><td>';
echo '<input type="hidden" name="mode" value="' .$sMode. '">';
if($iGroupTypeMissing > 0) 
	echo '<input type="hidden" name="type" value="' .$iGroupTypeMissing. '">';
if(isset($sFilter)) 
	echo '<input type="hidden" name="Filter" value="' .$sFilter. '">';
if(isset($sSort)) 
	echo '<input type="hidden" name="Sort" value="' .$sSort. '">';
if(isset($sLetter)) 
	echo "<input type=\"hidden\" name=\"Letter\" value='" .$sLetter. "'\">";
if(isset($iClassification)) 
	echo "<input type=\"hidden\" name=\"Classification\" value='" .$iClassification. "'\">";
if(isset($iFamilyRole)) 
	echo "<input type=\"hidden\" name=\"FamilyRole\" value='" .$iFamilyRole. "'\">";
if(isset($iGender)) 
	echo "<input type=\"hidden\" name=\"Gender\" value='" .$iGender. "'\">"; 
if(isset($iGroupType)) 
	echo "<input type=\"hidden\" name=\"grouptype\" value='" .$iGroupType. "'\">"; 
if(isset($iGroupID))
	echo "<input type=\"hidden\" name=\"groupid\" value='" .$iGroupID. "'\">";

echo "<select class=\"SmallText\" name=\"PersonColumn3\" onchange=\"this.form.submit()\">";

$aPersonCol3 = array("Classification","Family Role","Gender");
foreach($aPersonCol3 as $s)
{
	$sel = "";
	if($sPersonColumn3 == $s) 
		$sel = " selected";
	echo '<option value="'.$s.'"'.$sel.'>'.gettext($s).'</option>';
}

echo "</select></td>";

echo "<td><a href=\"SelectList.php?mode=" .$sMode. "&type=" .$iGroupTypeMissing;
echo "&Sort=family&Filter=" .$sFilter. "\">" . gettext("Family") . "</a></td>";

//echo "<td>" . gettext("Zip/Postal Code") . "</td></form>";

echo "<td>";
echo "<select class=\"SmallText\" name=\"PersonColumn5\" onchange=\"this.form.submit()\">";
$aPersonCol5 = array("Home Phone","Work Phone","Mobile Phone","Zip/Postal Code");
foreach($aPersonCol5 as $s)
{
	$sel = "";
	if($sPersonColumn5 == $s) 
		$sel = " selected";
	echo '<option value="'.$s.'"'.$sel.'>'.gettext($s).'</option>';
}
echo "</select></td></form>";


echo "<td>" . gettext("Cart") . "</td>";

if ($iMode == 1) 
{
	echo "<td>" . gettext("vCard") . "</td>";
	echo "<td>" . gettext("Printable Record") . "</td>";
} else 
{
	echo "<td>" . gettext("Assign") . "</td>";
}
 
echo "</tr>";
echo "<tr><td>&nbsp;</td></tr>";

$sRowClass = "RowColorA";

$iPrevFamily = -1;

//Loop through the person recordset
while ($aRow = mysql_fetch_array($rsPersons))
{
	$per_Title = "";
	$per_FirstName = "";
	$per_MiddleName = "";
	$per_LastName = "";
	$per_Suffix = "";
	$per_Gender = "";

	$fam_Name = "";
	$fam_Address1 = "";
	$fam_Address2 = "";
	$fam_City = "";
	$fam_State = "";

	extract($aRow);

	// Add alphabetical headers based on sort
	$sBlankLine = "<tr><td>&nbsp;</td></tr>";
	switch($sSort)
	{
	case "family":
		if ($fam_ID != $iPrevFamily || $iPrevFamily == -1)
		{
			echo $sBlankLine;
			echo "<tr><td></td><td class=\"ControlBreak\">";

			if (strlen($fam_Name) > 0) 
				echo $fam_Name;
			else
				echo "Unassigned";

			echo "</td></tr>";
			$sRowClass = "RowColorA";
		}
		break;

	case "name":
		if (strtoupper(substr($per_LastName,0,1)) != $sPrevLetter)
		{
			echo $sBlankLine;
			echo "<tr><td></td>";
			echo "<td class=\"ControlBreak\">" . strtoupper(substr($per_LastName,0,1));
			echo "</td></tr>";
			$sRowClass = "RowColorA";
		}
		break;

	default:
		break;
	} // end switch

	//Alternate the row color
	$sRowClass = AlternateRowStyle($sRowClass);

	//Display the row
    echo "<tr class=" .$sRowClass. "\">";
	if ($_SESSION['bEditRecords']) 
	{
		echo "<td><a href=\"PersonEditor.php?PersonID=" .$per_ID. "\">";
		echo gettext(Edit) . "</a></td>";
	} 

	echo "<td><a href=\"PersonView.php?PersonID=" .$per_ID. "\">";
	echo FormatFullName($per_Title, $per_FirstName, $per_MiddleName, 
						$per_LastName, $per_Suffix, 3);
	echo "</a>&nbsp;</td>";

	echo "<td>";
	if ($sPersonColumn3 == "Classification") 
 		echo $aClassificationName[$per_cls_ID]; 
	elseif ($sPersonColumn3 == "Family Role")
		echo $aFamilyRoleName[$per_fmr_ID];
	else 
	{	// Display Gender
		switch ($per_Gender) 
		{
			case 1: echo gettext("Male"); break; 
			case 2: echo gettext("Female"); break; 
			default: echo "";
		}
	}
	echo "&nbsp;</td>";

	echo "<td>";
	if ($fam_Name != "") 
	{
		echo "<a href=\"FamilyView.php?FamilyID=" . $fam_ID . "\">" . $fam_Name;
		echo FormatAddressLine($fam_Address1, $fam_City, $fam_State) . "</a>";
	}
	echo "&nbsp;</td>";

	echo "<td>";
    // Phone number or zip code
	if ($sPersonColumn5 == "Home Phone") 
    {
        echo SelectWhichInfo(ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy),
                ExpandPhoneNumber($per_HomePhone,$fam_Country,$dummy), True);
    }
	elseif ($sPersonColumn5 == "Work Phone")
    {
        echo SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$fam_Country,$dummy),
                ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), True);
    }
    elseif ($sPersonColumn5 == "Mobile Phone")
    {
        echo SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$fam_Country,$dummy),
                ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), True);
    }
	else 
    { 
        if (strlen($zip)) 
            echo $zip; 
        else 
            echo gettext("unassigned");
    } 
	echo "</td>";

	echo "<td>";
	if (!isset($_SESSION['aPeopleCart']) || !in_array($per_ID, $_SESSION['aPeopleCart'], false))
	{

		// Add to cart option
		if (substr($sRedirect, -1, 1) == '?')
			echo "<a onclick=saveScrollCoordinates()
					href=\"" .$sRedirect. "AddToPeopleCart=" .$per_ID. "\">";
		elseif (substr($sRedirect, -1, 1) == '&')
			echo "<a onclick=saveScrollCoordinates()
					href=\"" .$sRedirect. "AddToPeopleCart=" .$per_ID. "\">";
		else
			echo "<a onclick=saveScrollCoordinates() 
					href=\"" .$sRedirect. "&AddToPeopleCart=" .$per_ID. "\">";

		echo gettext("Add to Cart") . "</a>";
	} else
	{
		// Remove from cart option
		if (substr($sRedirect, -1, 1) == '?')
			echo "<a onclick=saveScrollCoordinates() 
					href=\"" .$sRedirect. "RemoveFromPeopleCart=" .$per_ID. "\">";
		elseif (substr($sRedirect, -1, 1) == '&')
			echo "<a onclick=saveScrollCoordinates()
					href=\"" .$sRedirect. "RemoveFromPeopleCart=" .$per_ID. "\">";
		else
			echo "<a onclick=saveScrollCoordinates()
					href=\"" .$sRedirect. "&RemoveFromPeopleCart=" .$per_ID. "\">";

		echo gettext("Remove") . "</a>";

	}

	if ($iMode == 1) 
	{
		echo "<td><a href=\"VCardCreate.php?PersonID=" .$per_ID. "\">";
		echo gettext("Create vCard") . "</a></td>";
		echo "<td><a href=\"PrintView.php?PersonID=" .$per_ID. "\">";
		echo gettext("Print Page") . "</a></td>";
	} else 
	{
		echo "<td><a href=\"PersonToGroup.php?PersonID=" .$per_ID;
		echo "&prevquery=" . rawurlencode($_SERVER["QUERY_STRING"]) . "\">";
		echo gettext("Add to Group") . "</a></td>";
	}

	echo "</tr>";
                 
	//Store the family to enable the control break
	$iPrevFamily = $fam_ID;

	//If there was no family, set it to 0
	if (strlen($iPrevFamily) < 1)
		$iPrevFamily = 0;

	//Store the first letter of this record to enable the control break
	$sPrevLetter = strtoupper(substr($per_LastName,0,1));

} // end of while loop

//Close the table
echo "</table>\n";

require "Include/Footer.php";
exit;

} 

else // if (!($iMode == 1 || $iMode == 2))
{

require "$sHeaderFile";

?>
<table cellpadding="2" align="center" cellspacing="0" width="100%">

                        <tr class="TableHeader">
                                <td><?php echo gettext("Name"); ?></td>
                                <td><?php echo gettext("Address"); ?><br><?php echo gettext("City, State Zip"); ?></td>
                                <td><?php echo gettext("Home Phone") . " /"; ?>
                                <br><?php echo gettext("Work Phone") . " /"; ?>
                                <br><?php echo gettext("Cell Phone"); ?></td>
                                <td><?php echo gettext("Primary E-mail") . " /"; ?>
                                <br><?php echo gettext("Work / Other E-mail"); ?></td>
                                <td><?php echo gettext("Date Entered"); ?></td>
                        </tr>
                <?php

                $sRowClass = "RowColorA";

                $iPrevFamily = -1;

                $finalSQL = $sSQL . $sOrderSQL;
                $rsPersons = RunQuery($finalSQL);

                //Loop through the person recordset
                while ($aRow = mysql_fetch_array($rsPersons))
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
                        $per_DateEntered = "";
                        $fam_Name = "";
                        $fam_Address1 = "";
                        $fam_Address2 = "";
                        $fam_City = "";
                        $fam_State = "";
                        $fam_Zip = "";
                        $fam_Country = "";
                        $fam_HomePhone = "";
                        $fam_CellPhone = "";
                        $fam_Email = "";

                        extract($aRow);

                        //Alternate the row color
                        $sRowClass = AlternateRowStyle($sRowClass);

                        // Assign the values locally, after selecting whether to display the family or person information
                        SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, False);
                        $sCity = SelectWhichInfo($per_City, $fam_City, False);
                        $sState = SelectWhichInfo($per_State, $fam_State, False);
                        $sZip = SelectWhichInfo($per_Zip, $fam_Zip, False);
                        $sCountry = SelectWhichInfo($per_Country, $fam_Country, False);
                        $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sCountry,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), False);
                        $sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$sCountry,$dummy), ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), False);
                        $sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sCountry,$dummy), ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), False);
                        $sUnformattedEmail = SelectWhichInfo($per_Email, $fam_Email, False);

                        //Display the row
                        ?>

                        <tr class="<?php echo $sRowClass; ?>">
                                <td><?php echo FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0); ?>&nbsp;</td>
                                <td><?php echo $sAddress1;?>&nbsp;<?php if ($sAddress1 != "" && $sAddress2 != "") { echo ", "; } ?><?php if ($sAddress2 != "") echo $sAddress2; ?>
                                <?php if ($sCity || $sState || $sZip)
                                        echo "<br>" . $sCity . ", " . $sState . " " . $sZip; ?></td>
                                <td><?php echo $sHomePhone ?>&nbsp;
                                <?php if($sWorkPhone) echo "<br>" . $sWorkPhone; ?>
                                <?php if($sCellPhone) echo "<br>" . $sCellPhone; ?></td>
                                <td><?php echo $sUnformattedEmail ?>&nbsp;
                                <br><?php echo $per_WorkEmail ?></td>
                                <td><?php echo $per_DateEntered ?>&nbsp;</td>
                        </tr>

                        <?php

                        //Store the family to enable the control break
                        $iPrevFamily = $fam_ID;

                        //If there was no family, set it to 0
                        if (strlen($iPrevFamily) < 1)
                        {

                                $iPrevFamily = 0;

                        }

                        //Store the first letter of this record to enable the control break
                        $sPrevLetter = strtoupper(substr($per_LastName,0,1));

                }
                //Close the table
                echo "</table>\n";
                require "Include/Footer-Short.php";
        }
}
/**********************
**  Family Listing  **
**********************/
else
{
		require "$sHeaderFile";

        // Base SQL
        $sSQL = "SELECT * FROM family_fam";

        if (isset($sLetter))
                $sSQL .= " WHERE fam_Name LIKE '" . $sLetter . "%'";
        elseif (isset($sFilter))
        {
                // break on the space...
                // $aFilter = explode(" ",$sFilter);
                //$sSQL .= " WHERE fam_Name LIKE '%" . $aFilter[0] . "%'";

                $sSQL .= " WHERE fam_Name LIKE '%" . $sFilter . "%'";
        }

        //Apply the sort based on what was passed in
        switch($sSort)
        {
                case "entered":
                        $sSQL = $sSQL . " ORDER BY fam_DateEntered DESC";
                        break;

                default:
                        $sSQL = $sSQL . " ORDER BY fam_Name";
                        break;
        }

        $rsFamCount = RunQuery($sSQL);
        $Total = mysql_num_rows($rsFamCount);

        // Append a LIMIT clause to the SQL statement
        if (empty($_GET['Result_Set']))
        {
                $Result_Set = 0;
                $sSQL .= " LIMIT $Result_Set, $iPerPage";
        }
        else
        {
                $Result_Set = FilterInput($_GET['Result_Set'],'int');
                $sSQL .= " LIMIT $Result_Set, $iPerPage";
        }

        // Run The Query With a Limit to get result
        $rsFamilies = RunQuery($sSQL);

        // Run query to get first letters of name.
        $sSQL = "SELECT DISTINCT LEFT(fam_Name,1) AS letter FROM family_fam ORDER BY letter";
        $rsLetters = RunQuery($sSQL);

        //Does this user have AddModify permissions?
        if ($_SESSION['bAddRecords']) { echo "<div align=\"center\"><a href=\"FamilyEditor.php\">" . gettext("Add a New Family Record") . "</a></div><BR>"; }
        ?>
        <form method="get" action="SelectList.php" name="FamilyList">
                <p align="center">
                <?php echo gettext("Sort order:"); ?>
                <select name="Sort">
                        <option value="name"><?php echo gettext("By Name"); ?></option>
                        <option value="entered"><?php echo gettext("By Newest Entries"); ?></option>
                </select>&nbsp;
                <input type="text" name="Filter">
                <input type="hidden" name="mode" value="family">
                <input type="submit" class="icButton" value="<?php echo gettext("Apply Filter"); ?>">
                </p>
        </form>
        <?php
        // Create Sort Links
        echo "<div align=\"center\">";
        echo "<a href=\"SelectList.php?mode=family\">" . gettext("View All") . "</a>";
        while ($aLetter = mysql_fetch_array($rsLetters))
        {
                echo "&nbsp;&nbsp;|&nbsp;&nbsp;<a href=\"SelectList.php?mode=family";
                if($sSort) echo "&Sort=$sSort";
                echo "&Letter=" . $aLetter[0] . "\">" . $aLetter[0] . "</a>";
        }

        echo "</div>";
        echo "<BR>";

        // Create Next / Prev Links and $Result_Set Value
        if ($Total > 0)
        {
                echo "<div align=\"center\">";
                echo "<form method=\"get\" action=\"SelectList.php\" name=\"ListNumber\">";

                // Show previous-page link unless we're at the first page
                if ($Result_Set < $Total && $Result_Set > 0)
                {
                        $thisLinkResult = $Result_Set - $iPerPage;
                        echo "<A HREF=\"SelectList.php?Result_Set=$thisLinkResult&mode=family&Filter=$sFilter&Sort=$sSort&Letter=$sLetter\">Previous Page</A>&nbsp;&nbsp;";
                }

                // Calculate starting and ending Page-Number Links
                $Pages = ceil($Total / $iPerPage);
                $startpage =  (ceil($Result_Set / $iPerPage)) - 6;
                if ($startpage <= 2)
                        $startpage = 1;
                $endpage = (ceil($Result_Set / $iPerPage)) + 9;
                if ($endpage >= ($Pages - 1))
                        $endpage = $Pages;

                // Show Link "1 ..." if startpage does not start at 1
                if ($startpage != 1)
                        echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=0&mode=family&Filter=$sFilter&Sort=$sSort&Letter=$sLetter\">1</a> ... \n";

                // Display page links
                if ($Pages > 1)
                {
                        for ($c = $startpage; $c <= $endpage; $c++)
                        {
                                $b = $c - 1;
                                $thisLinkResult = $iPerPage * $b;
                                if ($thisLinkResult != $Result_Set)
                                        echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=family&Filter=$sFilter&Sort=$sSort&Letter=$sLetter\">$c</a>&nbsp;\n";
                                else
                                        echo "&nbsp;&nbsp;[ " . $c . " ]&nbsp;&nbsp;";
                        }
                }

                // Show Link "... xx" if endpage is not the maximum number of pages
                if ($endpage != $Pages)
                {
                        $thisLinkResult = ($Pages - 1) * $iPerPage;
                        echo " ... <a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=family&Filter=$sFilter&Sort=$sSort&Letter=$sLetter\">$Pages</a>\n";
                }

                // Show next-page link unless we're at the last page
                if ($Result_Set >= 0 && $Result_Set < $Total)
                {
                        $thisLinkResult=$Result_Set+$iPerPage;
                        if ($thisLinkResult<$Total)
                                echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=$thisLinkResult&mode=family&Filter=$sFilter&Sort=$sSort&Letter=$sLetter\">" . gettext("Next Page") . "</a>";
                }
                ?>

                <input type="hidden" name="mode" value="family">
                <?php
                if(isset($sFilter))
                        echo "<input type=\"hidden\" name=\"Filter\" value=\"" . $sFilter . "\">";
                if(isset($sSort))
                        echo "<input type=\"hidden\" name=\"Sort\" value=\"" . $sSort . "\">";
                if(isset($sLetter))
                        echo "<input type=\"hidden\" name=\"Letter\" value='" . $sLetter . "'\">";

                // Display record limit per page
                if ($_SESSION['SearchLimit'] == "5")
                        $sLimit5 = "selected";
                if ($_SESSION['SearchLimit'] == "10")
                        $sLimit10 = "selected";
                if ($_SESSION['SearchLimit'] == "20")
                        $sLimit20 = "selected";
                if ($_SESSION['SearchLimit'] == "25")
                        $sLimit25 = "selected";
                if ($_SESSION['SearchLimit'] == "50")
                        $sLimit50 = "selected";

                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". gettext("Display:") . "&nbsp;
                <select class=\"SmallText\" name=\"Number\">
                        <option value=\"5\" $sLimit5>5</option>
                        <option value=\"10\" $sLimit10>10</option>
                        <option value=\"20\" $sLimit20>20</option>
                        <option value=\"25\" $sLimit25>25</option>
                        <option value=\"50\" $sLimit50>50</option>
                </select>&nbsp;
                <input type=\"submit\" class=\"icTinyButton\" value=\"". gettext("Go") ."\">
                </form>
                </div>";

         } ?>
        <BR>

        <table cellpadding="4" align="center" cellspacing="0" width="100%">

        <tr class="TableHeader">
                <?php if ($_SESSION['bEditRecords']) { ?>
                        <td width="25"><?php echo gettext("Edit"); ?></td>
                <?php } ?>
                <td><?php echo gettext("Family Name"); ?></td>
                <?php if ($bFamListFirstNames) echo "<td>" . gettext("First Name") . "</td>"; ?>
                <td><?php echo gettext("Address"); ?></td>
                <td><?php echo gettext("City"); ?></td>
                <td><?php echo gettext("State"); ?></td>
                <td><?php echo gettext("Last Edited"); ?></td>
        </tr>

        <tr>
                <td>&nbsp;</td>
        </tr>

        <?php
        //Loop through the family recordset
        while ($aRow = mysql_fetch_array($rsFamilies))
        {
                // Unfortunately, extract()'s behavior with NULL array entries is inconsistent across different PHP versions
                // To be safe, we need to manually clear these variables.
                $fam_Name = "";
                $fam_Address1 = "";
                $fam_Address2 = "";
                $fam_City = "";
                $fam_State = "";
                $fam_DateLastEdited = "";

                extract($aRow);

                if ($bFamListFirstNames)
                {
                        // build string of member first names
                        $sFirstNames = "";
                        $sSQL = "SELECT per_FirstName FROM person_per
                                LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
                                WHERE per_fam_ID = " . $fam_ID . " ORDER BY fmr.lst_OptionSequence";
                        $rsFirstNames = RunQuery($sSQL);

                        $bFirstItem = true;
                        while ($aTemp = mysql_fetch_array($rsFirstNames))
                        {
                                if ($bFirstItem) {
                                        $sFirstNames .= $aTemp["per_FirstName"];
                                        $bFirstItem = false;
                                }
                                else
                                        $sFirstNames .= ", " . $aTemp["per_FirstName"];
                        }
                }

                //Does this family name start with a new letter?
                if (strtoupper(substr($fam_Name,0,1)) != $sPrevLetter)
                {
                        //Display the header
                        echo $sBlankLine;
                        echo "<tr><td class=\"ControlBreak\" colspan=\"4\"><b>" . strtoupper(substr($fam_Name,0,1)) . "</b></td></tr>";
                        $sBlankLine = "<tr><td>&nbsp;</td></tr>";
                        $sRowClass = "RowColorA";
                }

                //Alternate the row style
                $sRowClass = AlternateRowStyle($sRowClass);

                //Display the row
                ?>

                <tr class="<?php echo $sRowClass ?>">
                        <?php if ($_SESSION['bEditRecords']) { ?>
                                <td><a href="FamilyEditor.php?FamilyID=<?php echo $fam_ID . "\">" . gettext ("Edit"); ?></a></td>
                        <?php } ?>
                        <td><a href="FamilyView.php?FamilyID=<?php echo $fam_ID ?>"><?php echo $fam_Name ?></a>&nbsp;</td>
                        <?php if ($bFamListFirstNames) echo "<td>" . $sFirstNames . "</td>"; ?>
                        <td><?php echo $fam_Address1;?><?php if ($fam_Address1 != "" && $fam_Address2 != "") { echo ", "; } ?><?php if ($fam_Address2 != "") echo $fam_Address2; ?>&nbsp;</td>
                        <td><?php echo $fam_City ?>&nbsp;</td>
                        <td><?php echo $fam_State ?>&nbsp;</td>
                        <td><?php echo $fam_DateLastEdited ?>&nbsp;</td>
                </tr>
                <?php
                //Store the first letter of the family name to allow for the control break
                $sPrevLetter = strtoupper(substr($fam_Name,0,1));
        }

        //Close the table
        echo "</table>";
        require "Include/Footer.php";
        exit;
}
?>
