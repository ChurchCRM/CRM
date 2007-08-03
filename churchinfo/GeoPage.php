<?php
/*******************************************************************************
*
*  filename    : GeoPage.php
*  website     : http://www.churchdb.org
*  copyright   : Copyright 2004-2005 Michael Wilt
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

require "Include/GeoCoder.php";

function CompareDistance ($elem1, $elem2)
{
  if ($elem1["Distance"] > $elem2["Distance"])
    return (1);
  else if ($elem1["Distance"] == $elem2["Distance"])
    return (0);
  else
    return (-1);
}

function SortByDistance ($array)
{
  $newArr = $array;
  usort ($newArr, CompareDistance);
  return ($newArr);
}

// Create an associated array of family information sorted by distance from
// a particular family.
function FamilyInfoByDistance ($iFamily)
{
	// Handle the degenerate case of no family selected by just making the array without
	// distance and bearing data, and don't bother to sort it.
	if ($iFamily) {
		// Get info for the selected family
		$sSQL = "SELECT fam_ID as selected_fam_ID, fam_Name as selected_fam_Name, fam_Address1 as selected_fam_Address1, fam_City as selected_fam_City, fam_State as selected_fam_State, fam_Zip as selected_fam_Zip, fam_Latitude as selected_fam_Latitude, fam_Longitude as selected_fam_Longitude from family_fam WHERE fam_ID=" . $iFamily;
		$rsFamilies = RunQuery ($sSQL);
		extract (mysql_fetch_array($rsFamilies));
	}

	// Compute distance and bearing from the selected family to all other families
	$sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State, fam_Zip, fam_Latitude, fam_Longitude from family_fam";

	$rsFamilies = RunQuery ($sSQL);
	while ($aFam = mysql_fetch_array($rsFamilies)) {
		extract ($aFam);

		if ($iFamily) {
			$results[$fam_ID]["Distance"] = floatval(LatLonDistance ($selected_fam_Latitude, $selected_fam_Longitude, $fam_Latitude, $fam_Longitude));
			$results[$fam_ID]["Bearing"] = LatLonBearing ($selected_fam_Latitude, $selected_fam_Longitude, $fam_Latitude, $fam_Longitude);
		}
		$results[$fam_ID]["fam_Name"] = $fam_Name;
		$results[$fam_ID]["fam_Address1"] = $fam_Address1;
		$results[$fam_ID]["fam_City"] = $fam_City;
		$results[$fam_ID]["fam_State"] = $fam_State;
		$results[$fam_ID]["fam_Zip"] = $fam_Zip;
		$results[$fam_ID]["fam_Latitude"] = $fam_Latitude;
		$results[$fam_ID]["fam_Longitude"] = $fam_Longitude;
        $results[$fam_ID]["fam_ID"] = $fam_ID;
	}

	if ($iFamily) {
		$resultsByDistance = SortByDistance($results);
	} else {
		$resultsByDistance = $results;
	}
	return ($resultsByDistance);
}

/* End of functions ... code starts here */

//Set the page title
$sPageTitle = gettext("Family Geographic Utilities");

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

// Get the Family if specified in the query string
$iFamily = FilterInput($_GET["Family"],'int');
$iNumNeighbors = FilterInput ($_GET["NumNeighbors"],'int');

if ($iNumNeighbors == 0)
	$iNumNeighbors = 15;

$nMaxDistance = 10; // miles, default value

$bClassificationPost = FALSE;
$sClassificationList = "";

//Is this the second pass?
if (    isset($_POST["FindNeighbors"]) || 
        isset($_POST["DataFile"]) || 
        isset($_POST["PersonIDList"]))
{
	//Get all the variables from the request object and assign them locally
	$iFamily = FilterInput($_POST["Family"]);
	$iNumNeighbors = FilterInput ($_POST["NumNeighbors"]);
	$nMaxDistance = FilterInput ($_POST["MaxDistance"]);
	$sCoordFileName = FilterInput ($_POST["CoordFileName"]);
	$sCoordFileFormat = FilterInput ($_POST["CoordFileFormat"]);
	$sCoordFileFamilies = FilterInput ($_POST["CoordFileFamilies"]);

    foreach ($aClassificationName as $key => $value) {
        $sClassNum = "Classification" . $key;
        if (isset($_POST["$sClassNum"]))
        {
            $bClassificationPost = TRUE;
            if (strlen($sClassificationList))
                $sClassificationList .= ",";
            $sClassificationList .= $key;
        }
    }
}

// Check if cart needs to be updated
if(isset($_POST["PersonIDList"]))
{
    $aIDsToProcess = explode(",", $_POST["PersonIDList"]);   

    //Do we add these people to cart?
    if(isset($_POST["AddAllToCart"]))
    {   AddArrayToPeopleCart($aIDsToProcess); }

    //Do we intersect these people with cart (keep values that are in both arrays)
    if(isset($_POST["IntersectCart"]))
    {   IntersectArrayWithPeopleCart($aIDsToProcess); }  

    if(isset($_POST["RemoveFromCart"]))
    {   RemoveArrayFromPeopleCart($aIDsToProcess); }

    //sort the cart
    sort($_SESSION['aPeopleCart']);
}

if (isset($_POST["DataFile"]))
{
	$resultsByDistance = FamilyInfoByDistance ($iFamily);

	if ($sCoordFileFormat == "GPSVisualizer")
		$filename = $sCoordFileName . ".csv";
	else if ($sCoordFileFormat == "StreetAtlasUSA")
		$filename = $sCoordFileName . ".txt";
	
	header("Content-Disposition: attachment; filename=$filename");

	if ($sCoordFileFormat == "GPSVisualizer")
		echo "Name,Latitude,Longitude\n";

	$counter = 0;

	foreach ($resultsByDistance as $oneResult) {
		if ($sCoordFileFamilies == "NeighborFamilies") {
			if ($counter++ == $iNumNeighbors)
				break;
			if ($oneResult["Distance"] > $nMaxDistance)
				break;
		}

		// Skip over the ones with no data
		if ($oneResult["fam_Latitude"] == 0)
			continue;

		if ($sCoordFileFormat == "GPSVisualizer") {
			echo $oneResult["fam_Name"] . "," . $oneResult["fam_Latitude"] . "," . $oneResult["fam_Longitude"] . "\n";
		} else if ($sCoordFileFormat == "StreetAtlasUSA") {
			echo "BEGIN SYMBOL\n";
			echo $oneResult["fam_Latitude"] . "," . $oneResult["fam_Longitude"] . "," . $oneResult["fam_Name"] . "," . "Green Star\n";
			echo "END\n";
		}
	}

	exit;
}

if (isset($_POST["UpdateAllFamilies"]))
{
	redirect ("UpdateAllLatLon.php");
}

require "Include/Header.php";

echo '<form method="POST" action="GeoPage.php" name="GeoPage">';
echo '<table>';

//Get Families for the list
$sSQL = "SELECT * FROM family_fam ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

// Make the family list
echo "<tr><td class=\"LabelColumn\">" . gettext("Select Family:") . "</td>\n";
echo "<td class=\"TextColumn\">\n";
echo "<select name=\"Family\" size=\"8\">";
while ($aRow = mysql_fetch_array($rsFamilies))
{
	extract($aRow);

	echo "\n<option value=\"" . $fam_ID . "\"";
	if ($iFamily == $fam_ID) { echo " selected"; }
	echo ">" . $fam_Name . "&nbsp;" . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
}
echo "</select></td></tr>\n";

echo "<tr>\n";
echo "	<td class=\"LabelColumn\">" . gettext("Maximum number of neighbors:") . "</td>\n";
echo "	<td class=\"TextColumn\"><input type=\"text\" name=\"NumNeighbors\" value=\"" . $iNumNeighbors . "\"></td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "	<td class=\"LabelColumn\">" . gettext("Maximum distance") . " (" . strtolower($sDistanceUnit) . "): </td>\n";
echo "	<td class=\"TextColumn\"><input type=\"text\" name=\"MaxDistance\" value=\"" . $nMaxDistance . "\"></td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "	<td class=\"LabelColumn\">" . gettext("Data file format:") . "</td>\n";
echo "	<td class=\"TextColumn\">\n";
echo "      <input type=\"radio\" name=\"CoordFileFormat\" value=\"GPSVisualizer\"" . ($sCoordFileFormat=="GPSVisualizer" ? " checked" : "") . ">" . gettext ("GPS Visualizer");
echo "      <input type=\"radio\" name=\"CoordFileFormat\" value=\"StreetAtlasUSA\"" . ($sCoordFileFormat=="StreetAtlasUSA" ? " checked" : "") . ">" . gettext ("Street Atlas USA");
echo "  </td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "	<td class=\"LabelColumn\">" . gettext("Include families in coordinate file:") . "</td>\n";
echo "	<td class=\"TextColumn\">\n";
echo "      <input type=\"radio\" name=\"CoordFileFamilies\" value=\"AllFamilies\"" . ($sCoordFileFamilies=="AllFamilies" ? " checked" : "") . ">" . gettext ("All Families");
echo "      <input type=\"radio\" name=\"CoordFileFamilies\" value=\"NeighborFamilies\"" . ($sCoordFileFamilies=="NeighborFamilies" ? " checked" : "") . ">" . gettext ("Neighbor Families");
echo "  </td>\n";
echo "</tr>\n";

echo '<tr>';
echo "	<td class=\"LabelColumn\">" . gettext("Coordinate data base file name") . "</td>";
echo "	<td class=\"TextColumn\"><input type=\"text\" name=\"CoordFileName\" value=\"" . $sCoordFileName . "\"></td>";
echo "</tr>";

echo '<tr>';
echo '<td></td>';
echo '<td><input type="submit" class="icButton" name="DataFile" value="' . gettext("Make Data File") . '"></td>';
echo '<td><input type="submit" class="icButton" name="UpdateAllFamilies" value="' . gettext("Update All Family Coordinates") . '"></td>';
echo "</tr></table>\n";

echo "<CENTER><br><br><h3>Show neighbors with these classifications.</h3>";
echo '<table>';
echo '<tr><td><br></td><td><input type="submit" class="icButton" name="FindNeighbors" value="' . gettext("Show Neighbors") . '"></td></tr>'."\n";

foreach ($aClassificationName as $key => $value) {
    echo '<tr><td class="LabelColumn">'.$value.'</td>';
    echo '<td class="TextColumn">';
    echo '<input name="Classification'.$key.'" type="checkbox" ';
    echo 'id="'.$value.'" value="1" ';
    $sClassNum = "Classification" . $key;
    if (!$bClassificationPost || isset($_POST["$sClassNum"]))
        echo "checked";
    echo "><br></td></tr>\n";
}


echo '</table></CENTER>';

if (isset($_POST["FindNeighbors"]) && !$iFamily)
{
    echo "<center><h2>Please select a Family</h2></center>";
}

unset($aPersonIDs);

if (    $iFamily != 0 &&
        (isset($_POST["FindNeighbors"]) ||
        isset($_POST["PersonIDList"])))
{
	$resultsByDistance = FamilyInfoByDistance ($iFamily);

	$counter = 0;

	// Column Headings
	echo "<table cellpadding='4' align='center' cellspacing='0' width='100%'>\n";
	echo "<tr class='TableHeader'>\n";
	echo "<td width='25'>".gettext("Distance") . "</td>\n";
	echo "<td>".gettext("Direction")."</a></td>\n";
	echo "<td>".gettext("Name")."</a></td>\n";
	echo "<td>".gettext("Address")."</td>\n";
	echo "<td>".gettext("City")."</td>\n";
	echo "<td>".gettext("State")."</td>\n";
	echo "<td>".gettext("Zip")."</td>\n";
	echo "<td>".gettext("Latitude")."</td>\n";
	echo "<td>".gettext("Longitude")."</td>\n";
	echo "</tr>\n";

	foreach ($resultsByDistance as $oneResult) {

		if ($counter >= $iNumNeighbors)
			break;

		if ($oneResult["Distance"] > $nMaxDistance)
			break;

        // Determine how many people in this family will be listed
        $sSQL = "SELECT * from person_per where per_fam_ID=".$oneResult["fam_ID"];
        if ($bClassificationPost) 
        {
            $sSQL .= " AND per_cls_ID IN (".$sClassificationList.")";
        }
        $rsPeople = RunQuery($sSQL);
        $numListed = mysql_num_rows($rsPeople);
        
        if (!$numListed) // skip familes with zero members
            continue;

        $counter++;

        //Alternate the row color
        $sRowClass = AlternateRowStyle($sRowClass);

        echo "\n".'<tr class="' .$sRowClass. '">';
		echo "<td>" . $oneResult["Distance"] . "</td>";
		echo "<td>" . $oneResult["Bearing"] . "</td>";
		echo "<td><B>" . $oneResult["fam_Name"] . "</B></td>";
		echo "<td>" . $oneResult["fam_Address1"] . "</td>";
		echo "<td>" . $oneResult["fam_City"] . "</td>";
		echo "<td>" . $oneResult["fam_State"] . "</td>";
		echo "<td>" . $oneResult["fam_Zip"] . "</td>";
		echo "<td>" . $oneResult["fam_Latitude"] . "</td>";
		echo "<td>" . $oneResult["fam_Longitude"] . "</td>";
		echo "</tr>\n";


        while ($aRow = mysql_fetch_array($rsPeople)) {
            extract($aRow);

            if (!in_array($per_ID,$aPersonIDs)) {
                $aPersonIDs[] = $per_ID;
            }

            echo '<tr class="' .$sRowClass. '"><td><BR></td>';
            echo '<td><BR></td><td align="RIGHT">'.$per_FirstName." ".$per_LastName.'</td>';
            echo '<td align="RIGHT">'.$aClassificationName[$per_cls_ID].'</td>';
            echo '<td><br></td><td<br></td><td><br></td><td><br></td><td><br></td></tr>';
            echo "\n";
        }
	}
	echo '</table>' . "\n";

    $sPersonIDList = implode(",", $aPersonIDs);
    echo '<input type="hidden" name="PersonIDList" value="'.$sPersonIDList.'">';
    echo '<br><center>';
    echo '<input name="AddAllToCart" type="submit" class="icButton" value="' . gettext("Add to Cart") . '">&nbsp';
    echo '<input name="IntersectCart" type="submit" class="icButton" value="' . gettext("Intersect with Cart") . '">&nbsp';
    echo '<input name="RemoveFromCart" type="submit" class="icButton" value="' . gettext("Remove from Cart") . '">';
    echo '</center><br>';

}
echo '</form>';
?>
