<?php
/*******************************************************************************
*
*  filename    : /Include/Functions.php
*  website     : http://www.churchdb.org
*  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
*                Copyright 2004-1012 Michael Wilt
*
*  LICENSE:
*  (C) Free Software Foundation, Inc.
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 3 of the License, or
*  (at your option) any later version.
*
*  This program is distributed in the hope that it will be useful, but
*  WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
*  General Public License for more details.
*
*  http://www.gnu.org/licenses
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/
// Initialization common to all ChurchInfo scripts

//
// Basic security checks:
//

if (empty($bSuppressSessionTests))  // This is used for the login page only.
{
    // Basic security: If the UserID isn't set (no session), redirect to the login page
    if (!isset($_SESSION['iUserID']))
    {
        Redirect("Default.php");
        exit;
    }

    // Basic security: If $sRootPath has changed we have changed databases without logging in 
    // redirect to the login page 
    if ($_SESSION['sRootPath'] !== $sRootPath )
    {
        Redirect("Default.php");
        exit;
    }

    // Check for login timeout.  If login has expired, redirect to login page
    if ($sSessionTimeout > 0)
    {
        if ((time() - $_SESSION['tLastOperation']) > $sSessionTimeout)
        {
            Redirect("Default.php?timeout");
            exit;
        }
        else {
            $_SESSION['tLastOperation'] = time();
        }
    }

    // If this user needs to change password, send to that page
    if ($_SESSION['bNeedPasswordChange'] && !isset($bNoPasswordRedirect))
    {
        Redirect("UserPasswordChange.php?PersonID=" . $_SESSION['iUserID']);
        exit;
    }

    // Check if https is required

    // Note: PHP has limited ability to access the address bar 
    // url.  PHP depends on Apache or other web server
    // to provide this information.  The web server
    // may or may not be configured to pass the address bar url
    // to PHP.  As a workaround this security check is now performed
    // by the browser using javascript.  The browser always has 
    // access to the address bar url.  Search for basic security checks
    // in Include/Header-functions.php

}
// End of basic security checks

// if magic_quotes off and array
function addslashes_deep($value)
{
    $value = is_array($value) ?
                array_map('addslashes_deep', $value) :
                addslashes($value);

    return $value;
}

// If Magic Quotes is turned off, do the same thing manually..
if (empty($_SESSION['bHasMagicQuotes']))
{
    foreach ($_REQUEST as $key=>$value) $value = addslashes_deep($value);
}

// Constants
$aPropTypes = array(
    1 => gettext("True / False"),
    2 => gettext("Date"),
    3 => gettext("Text Field (50 char)"),
    4 => gettext("Text Field (100 char)"),
    5 => gettext("Text Field (long)"),
    6 => gettext("Year"),
    7 => gettext("Season"),
    8 => gettext("Number"),
    9 => gettext("Person from Group"),
    10 => gettext("Money"),
    11 => gettext("Phone Number"),
    12 => gettext("Custom Drop-Down List")
);

// Are they adding an entire group to the cart?
if (isset($_GET["AddGroupToPeopleCart"])) {
    AddGroupToPeopleCart(FilterInput($_GET["AddGroupToPeopleCart"],'int'));
    $sGlobalMessage = gettext("Group successfully added to the Cart.");
}

// Are they removing an entire group from the Cart?
if (isset($_GET["RemoveGroupFromPeopleCart"])) {
    RemoveGroupFromPeopleCart(FilterInput($_GET["RemoveGroupFromPeopleCart"],'int'));
    $sGlobalMessage = gettext("Group successfully removed from the Cart.");
}

// Are they adding a person to the Cart?
if (isset($_GET["AddToPeopleCart"])) {
    AddToPeopleCart(FilterInput($_GET["AddToPeopleCart"],'int'));
    $sGlobalMessage = gettext("Selected record successfully added to the Cart.");
}

// Are they removing a person from the Cart?
if (isset($_GET["RemoveFromPeopleCart"])) {
    RemoveFromPeopleCart(FilterInput($_GET["RemoveFromPeopleCart"],'int'));
    $sGlobalMessage = gettext("Selected record successfully removed from the Cart.");
}

// Are they emptying their cart?
if (isset($_GET["Action"]) && ($_GET["Action"] == "EmptyCart")) {
    $_SESSION['aPeopleCart'] = array ();
    $sGlobalMessage = gettext("Your cart has been successfully emptied.");
}

if (isset($_POST["BulkAddToCart"])) {

    $aItemsToProcess = explode(",",$_POST["BulkAddToCart"]);

    if (isset($_POST["AndToCartSubmit"]))
    {
        if (isset($_SESSION['aPeopleCart']))
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'],$aItemsToProcess);
    }
    elseif (isset($_POST["NotToCartSubmit"]))
    {
        if (isset($_SESSION['aPeopleCart']))
            $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'],$aItemsToProcess);
    }
    else
    {
        for ($iCount = 0; $iCount < count($aItemsToProcess); $iCount++) {
            AddToPeopleCart(str_replace(",","",$aItemsToProcess[$iCount]));
        }
        $sGlobalMessage = $iCount . " " . gettext("item(s) added to the Cart.");
    }
}

//
// Some very basic functions that all scripts use
//

function RedirectURL($sRelativeURL)
// Convert a relative URL into an absolute URL and return absolute URL.
{
    global $sRootPath;
    global $sDocumentRoot;

if (empty($_SESSION['sURLPath'])) {
    $sErrorMessage = "Fatal Error: \$_SESSION['sURLPath'] is empty.<br> <a href='Default.php'>Click here to login</a>.\n";
    die ($sErrorMessage);
}

    // Test if file exists before redirecting.  May need to remove
    // query string first.
    $iQueryString = strpos($sRelativeURL,'?');
    if ($iQueryString) {
        $sPathExtension = substr($sRelativeURL,0,$iQueryString);
    } else {
        $sPathExtension = $sRelativeURL;
    }

    // The idea here is to get the file path into this form:
    //     $sFullPath = $sDocumentRoot.$sRootPath.$sPathExtension
    // The Redirect URL is then in this form: 
    //     $sRedirectURL = $_SESSION['sURLPath'].$sPathExtension

    $sFullPath = str_replace('\\','/',$sDocumentRoot.'/'.$sPathExtension);

    // With the query string removed we can test if file exists
    if (file_exists($sFullPath) && is_readable($sFullPath)) {
        return ($_SESSION['sURLPath'] .'/' . $sRelativeURL);
    } else {
        $sErrorMessage = 'Fatal Error: Cannot access file: '.$sFullPath."<br>\n";
        $sErrorMessage .= "\$sPathExtension = $sPathExtension<br>\n";
        $sErrorMessage .= "\$sDocumentRoot = $sDocumentRoot<br>\n";
        $sErrorMessage .= "\$_SESSION['sURLPath'] = ";
        $sErrorMessage .= $_SESSION['sURLPath'] . "<br>\n";

        die ($sErrorMessage);
    }
}

// Convert a relative URL into an absolute URL and redirect the browser there.
function Redirect($sRelativeURL)
{
    $sRedirectURL = RedirectURL($sRelativeURL);
    header("Location: " . $sRedirectURL);
    exit;
}

// Returns the current fiscal year
function CurrentFY()
{
    global $iFYMonth;

    $yearNow = date ("Y");
    $monthNow = date ("m");
    $FYID = $yearNow - 1996;
    if ($monthNow >= $iFYMonth)
        $FYID += 1;
    return ($FYID);
}

// PrintFYIDSelect: make a fiscal year selection menu.
function PrintFYIDSelect ($iFYID, $selectName)
{
    echo "<select name=\"" . $selectName . "\">";

    echo "<option value=\"0\">" . gettext("Select Fiscal Year") . "</option>";

    for ($fy = 1; $fy < CurrentFY() + 2; $fy++) {
        echo "<option value=\"" . $fy . "\"";
        if ($iFYID == $fy)
            echo " selected";
        echo ">";
        echo MakeFYString ($fy);
    }
    echo "</select>";
}

// Formats a fiscal year string
function MakeFYString ($iFYID)
{
    global $iFYMonth;
    $monthNow = date ("m");

    if ($iFYMonth == 1)
        return (1996 + $iFYID);
    else
        return (1995 + $iFYID . "/" . substr (1996 + $iFYID, 2, 2));
}

// Runs an SQL query.  Returns the result resource.
// By default stop on error, unless a second (optional) argument is passed as false.
function RunQuery($sSQL, $bStopOnError = true)
{
    global $cnInfoCentral;
    global $debug;

    if ($result = mysql_query($sSQL, $cnInfoCentral))
        return $result;
    elseif ($bStopOnError)
    {
        if ($debug)
            die(gettext("Cannot execute query.") . "<p>$sSQL<p>" . mysql_error());
        else
            die("Database error or invalid data");
    }
    else
        return FALSE;
}

function FilterInputArr ($arr, $key, $type='string', $size=1)
{
	if (array_key_exists ($key, $arr))
		return FilterInput ($arr[$key], $type, $size);
	else
		return FilterInput ("", $type, $size);
}

// Sanitizes user input as a security measure
// Optionally, a filtering type and size may be specified.  By default, strip any tags from a string.
// Note that a database connection must already be established for the mysql_real_escape_string function to work.
function FilterInput($sInput,$type = 'string',$size = 1)
{
    if (strlen($sInput) > 0)
    {
        switch($type) {
            case 'string':
                // or use htmlspecialchars( stripslashes( ))
                $sInput = strip_tags(trim($sInput));
                if (get_magic_quotes_gpc())
                    $sInput = stripslashes($sInput);
                $sInput = mysql_real_escape_string($sInput);
                return $sInput;
            case 'htmltext':
                $sInput = strip_tags(trim($sInput),'<a><b><i><u>');
                if (get_magic_quotes_gpc())
                    $sInput = stripslashes($sInput);
                $sInput = mysql_real_escape_string($sInput);
                return $sInput;
            case 'char':
                $sInput = substr(trim($sInput),0,$size);
                if (get_magic_quotes_gpc())
                    $sInput = stripslashes($sInput);
                $sInput = mysql_real_escape_string($sInput);
                return $sInput;
            case 'int':
                return (int) intval(trim($sInput));
            case 'float':
                return (float) floatval(trim($sInput));
            case 'date':
                // Attempts to take a date in any format and convert it to YYYY-MM-DD format
                return date("Y-m-d",strtotime($sInput));
        }
    }
    else
    {
        return "";
    }
}

//
// Adds a person to a group with specified role.
// Returns false if the operation fails. (such as person already in group)
//
function AddToGroup($iPersonID, $iGroupID, $iRoleID)
{
    global $cnInfoCentral;

    // Was a RoleID passed in?
    if ($iRoleID == 0) {
        // No, get the Default Role for this Group
        $sSQL = "SELECT grp_DefaultRole FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rsRoleID = RunQuery($sSQL);
        $Row = mysql_fetch_row($rsRoleID);
        $iRoleID = $Row[0];
    }

    $sSQL = "INSERT INTO person2group2role_p2g2r (p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID) VALUES (" . $iPersonID . ", " . $iGroupID . ", " . $iRoleID . ")";
    $result = RunQuery($sSQL,false);

    if ($result)
    {
        // Check if this group has special properties
        $sSQL = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
        $rsTemp = RunQuery($sSQL);
        $rowTemp = mysql_fetch_row($rsTemp);
        $bHasProp = $rowTemp[0];

        if ($bHasProp == 'true')
        {
            $sSQL = "INSERT INTO `groupprop_" . $iGroupID . "` (`per_ID`) VALUES ('" . $iPersonID . "')";
            RunQuery($sSQL);
        }
    }

    return $result;
}

function RemoveFromGroup($iPersonID, $iGroupID)
{
    $sSQL = "DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = " . $iPersonID . " AND p2g2r_grp_ID = " . $iGroupID;
    RunQuery($sSQL);

    // Check if this group has special properties
    $sSQL = "SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = " . $iGroupID;
    $rsTemp = RunQuery($sSQL);
    $rowTemp = mysql_fetch_row($rsTemp);
    $bHasProp = $rowTemp[0];

    if ($bHasProp == 'true')
    {
        $sSQL = "DELETE FROM `groupprop_" . $iGroupID . "` WHERE `per_ID` = '" . $iPersonID . "'";
        RunQuery($sSQL);
    }

    // Reset any group specific property fields of type "Person from Group" with this person assigned
    $sSQL = "SELECT grp_ID, prop_Field FROM groupprop_master WHERE type_ID = 9 AND prop_Special = " . $iGroupID;
    $result = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($result))
    {
        $sSQL = "UPDATE groupprop_" . $aRow['grp_ID'] . " SET " . $aRow['prop_Field'] . " = NULL WHERE " . $aRow['prop_Field'] . " = " . $iPersonID;
        RunQuery($sSQL);
    }

    // Reset any custom person fields of type "Person from Group" with this person assigned
    $sSQL = "SELECT custom_Field FROM person_custom_master WHERE type_ID = 9 AND custom_Special = " . $iGroupID;
    $result = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($result))
    {
        $sSQL = "UPDATE person_custom SET " . $aRow['custom_Field'] . " = NULL WHERE " . $aRow['custom_Field'] . " = " . $iPersonID;
        RunQuery($sSQL);
    }
}

//
// Adds a volunteer opportunity assignment to a person
//
function AddVolunteerOpportunity($iPersonID, $iVolID)
{
    $sSQL = "INSERT INTO person2volunteeropp_p2vo (p2vo_per_ID, p2vo_vol_ID) VALUES (" . $iPersonID . ", " . $iVolID . ")";
    $result = RunQuery($sSQL,false);
    return $result;
}

function RemoveVolunteerOpportunity($iPersonID, $iVolID)
{
    $sSQL = "DELETE FROM person2volunteeropp_p2vo WHERE p2vo_per_ID = " . $iPersonID . " AND p2vo_vol_ID = " . $iVolID;
    RunQuery($sSQL);
}

function ConvertCartToString($aCartArray)
{
    // Implode the array
    $sCartString = implode(",", $aCartArray);

    // Make sure the comma is chopped off the end
    if (substr($sCartString, strlen($sCartString) - 1, 1) == ",") {
        $sCartString = substr($sCartString, 0, strlen($sCartString) - 1);
    }

    // Make sure there are no duplicate commas
    $sCartString = str_replace(",,", "", $sCartString);

    return $sCartString;
}


/******************************************************************************
 * Returns the proper information to use for a field.
 * Person info overrides Family info if they are different.
 * If using family info and bFormat set, generate HTML tags for text color red.
 * If neither family nor person info is available, return an empty string.
 *****************************************************************************/

function SelectWhichInfo($sPersonInfo, $sFamilyInfo, $bFormat = false)
{
    global $bShowFamilyData;

    if ($bShowFamilyData) {

        if ($bFormat) {
            $sFamilyInfoBegin = "<span style=\"color: red;\">";
            $sFamilyInfoEnd = "</span>";
        }

        if ($sPersonInfo != "") {
            return $sPersonInfo;
        } elseif ($sFamilyInfo != "") {
            if ($bFormat) {
                return $sFamilyInfoBegin . $sFamilyInfo . $sFamilyInfoEnd;
            } else {
                return $sFamilyInfo;
            }
        } else {
            return "";
        }

    } else {
        if ($sPersonInfo != "")
            return $sPersonInfo;
        else
            return "";
    }
}

//
// Returns the correct address to use via the sReturnAddress arguments.
// Function value returns 0 if no info was given, 1 if person info was used, and 2 if family info was used.
// We do address lines 1 and 2 in together because seperately we might end up with half family address and half person address!
//
function SelectWhichAddress(&$sReturnAddress1, &$sReturnAddress2, $sPersonAddress1, $sPersonAddress2, $sFamilyAddress1, $sFamilyAddress2, $bFormat = false)
{
    global $bShowFamilyData;

    if ($bShowFamilyData) {

        if ($bFormat) {
            $sFamilyInfoBegin = "<span style=\"color: red;\">";
            $sFamilyInfoEnd = "</span>";
        }

        if ($sPersonAddress1 || $sPersonAddress2) {
                $sReturnAddress1 = $sPersonAddress1;
                $sReturnAddress2 = $sPersonAddress2;
                return 1;
        } elseif ($sFamilyAddress1 || $sFamilyAddress2) {
            if ($bFormat) {
                if ($sFamilyAddress1)
                    $sReturnAddress1 = $sFamilyInfoBegin . $sFamilyAddress1 . $sFamilyInfoEnd;
                else $sReturnAddress1 = "";
                if ($sFamilyAddress2)
                    $sReturnAddress2 = $sFamilyInfoBegin . $sFamilyAddress2 . $sFamilyInfoEnd;
                else $sReturnAddress2 = "";
                return 2;
            } else {
                $sReturnAddress1 = $sFamilyAddress1;
                $sReturnAddress2 = $sFamilyAddress2;
                return 2;
            }
        } else {
            $sReturnAddress1 = "";
            $sReturnAddress2 = "";
            return 0;
        }

    } else {
        if ($sPersonAddress1 || $sPersonAddress2) {
            $sReturnAddress1 = $sPersonAddress1;
            $sReturnAddress2 = $sPersonAddress2;
            return 1;
        } else {
            $sReturnAddress1 = "";
            $sReturnAddress2 = "";
            return 0;
        }
    }
}

function ChopLastCharacter($sText)
{
    return substr($sText,0,strlen($sText) - 1);
}

function AddToPeopleCart($sID)
{
    // make sure the cart array exists
    if(isset($_SESSION['aPeopleCart']))
    {
        if (!in_array($sID, $_SESSION['aPeopleCart'], false)) 
        {
            $_SESSION['aPeopleCart'][] = $sID;
        }
    }
    else
        $_SESSION['aPeopleCart'][] = $sID;
}

function AddArrayToPeopleCart($aIDs)
{
    if(is_array($aIDs)) // Make sure we were passed an array
    {
        foreach($aIDs as $value) 
        {
            AddToPeopleCart($value);
        }
    }
}


// Add group to cart 
function AddGroupToPeopleCart($iGroupID)
{
    //Get all the members of this group
    $sSQL = "SELECT p2g2r_per_ID FROM person2group2role_p2g2r " .
            "WHERE p2g2r_grp_ID = " . $iGroupID;
    $rsGroupMembers = RunQuery($sSQL);

    //Loop through the recordset
    while ($aRow = mysql_fetch_array($rsGroupMembers))
    {
        extract($aRow);

        //Add each person to the cart
        AddToPeopleCart($p2g2r_per_ID);
    }
}

function IntersectArrayWithPeopleCart($aIDs)
{
    if(isset($_SESSION['aPeopleCart']) && is_array($aIDs)) {
        $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'], $aIDs);
    }
}

function RemoveFromPeopleCart($sID)
{
    // make sure the cart array exists
    // we can't remove anybody if there is no cart
    if(isset($_SESSION['aPeopleCart']))
    {
        unset($aTempArray); // may not need this line, but make sure $aTempArray is empty
        $aTempArray[] = $sID; // the only element in this array is the ID to be removed
        $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'],$aTempArray);
    }
}

function RemoveArrayFromPeopleCart($aIDs)
{
    // make sure the cart array exists
    // we can't remove anybody if there is no cart
    if(isset($_SESSION['aPeopleCart']) && is_array($aIDs))
    {
        $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'],$aIDs);
    }
}

// Remove group from cart
function RemoveGroupFromPeopleCart($iGroupID)
{
    //Get all the members of this group
    $sSQL = "SELECT p2g2r_per_ID FROM person2group2role_p2g2r " . 
            "WHERE p2g2r_grp_ID = " . $iGroupID;
    $rsGroupMembers = RunQuery($sSQL);

    //Loop through the recordset
    while ($aRow = mysql_fetch_array($rsGroupMembers))
    {
        extract($aRow);

        //remove each person from the cart
        RemoveFromPeopleCart($p2g2r_per_ID);
    }
}


// Reinstated by Todd Pillars for Event Listing
// Takes MYSQL DateTime
// bWithtime 1 to be displayed
function FormatDate($dDate, $bWithTime=FALSE)
{
    if ($dDate == '' || $dDate == '0000-00-00 00:00:00' || $dDate == '0000-00-00')
        return ('');
    
    if (strlen($dDate)==10) // If only a date was passed append time
        $dDate = $dDate . ' 12:00:00';  // Use noon to avoid a shift in daylight time causing
                                        // a date change.

    if (strlen($dDate)!=19)
        return ('');

    // Verify it is a valid date
    $sScanString = substr($dDate,0,10); 
    list($iYear, $iMonth, $iDay) = sscanf($sScanString,"%04d-%02d-%02d");

    if ( !checkdate($iMonth,$iDay,$iYear) )
        return ('Unknown');

    // PHP date() function is not used because it is only robust for dates between
    // 1970 and 2038.  This is a problem on systems that are limited to 32 bit integers.  
    // To handle a much wider range of dates use MySQL date functions.

    $sSQL = "SELECT DATE_FORMAT('$dDate', '%b') as mn, "
    .       "DAYOFMONTH('$dDate') as dm, YEAR('$dDate') as y, "
    .       "DATE_FORMAT('$dDate', '%k') as h, "
    .       "DATE_FORMAT('$dDate', ':%i') as m";
    extract(mysql_fetch_array(RunQuery($sSQL)));

    $month = gettext("$mn"); // Allow for translation of 3 character month abbr

    if ($h > 11) {
        $sAMPM = gettext('pm');
        if ($h > 12) {
            $h = $h-12;
        } 
    } else {
        $sAMPM = gettext('am');
        if ($h == 0) {
            $h = 12;
        }
    }

    if ($bWithTime) {
        return ("$month $dm, $y $h$m $sAMPM");
    } else {
        return ("$month $dm, $y");
    }

}

function AlternateRowStyle($sCurrentStyle)
{
    if ($sCurrentStyle == "RowColorA") {
        return "RowColorB";
    } else {
        return "RowColorA";
    }
}

function ConvertToBoolean($sInput)
{
    if (empty($sInput)) {
        return False;
    } else {
        if (is_numeric($sInput)) {
            if ($sInput == 1) {
                return True;
            } else {
                return False;
            }
        }
        else
        {
            $sInput = strtolower($sInput);
            if (in_array($sInput,array("true","yes","si"))) {
                return True;
            } else {
                return False;
            }
        }
    }
}

function ConvertFromBoolean($sInput)
{
    if ($sInput) {
        return 1;
    } else {
        return 0;
    }
}

//
// Collapses a formatted phone number as long as the Country is known
// Eg. for United States:  555-555-1212 Ext. 123 ==> 5555551212e123
//
// Need to add other countries besides the US...
//
function CollapsePhoneNumber($sPhoneNumber,$sPhoneCountry)
{
    switch ($sPhoneCountry) {

    case "United States":
        $sCollapsedPhoneNumber = "";
        $bHasExtension = false;

        // Loop through the input string
        for ($iCount = 0; $iCount <= strlen($sPhoneNumber); $iCount++) {

            // Take one character...
            $sThisCharacter = substr($sPhoneNumber, $iCount, 1);

            // Is it a number?
            if (Ord($sThisCharacter) >= 48 && Ord($sThisCharacter) <= 57) {
                // Yes, add it to the returned value.
                $sCollapsedPhoneNumber .= $sThisCharacter;
            }
            // Is the user trying to add an extension?
            else if (!$bHasExtension && ($sThisCharacter == "e" || $sThisCharacter == "E")) {
                // Yes, add the extension identifier 'e' to the stored string.
                $sCollapsedPhoneNumber .= "e";
                // From now on, ignore other non-digits and process normally
                $bHasExtension = true;
            }
        }
        break;

    default:
        $sCollapsedPhoneNumber = $sPhoneNumber;
        break;
    }

    return $sCollapsedPhoneNumber;
}


//
// Expands a collapsed phone number into the proper format for a known country.
//
// If, during expansion, an unknown format is found, the original will be returned
// and the a boolean flag $bWeird will be set.  Unfortunately, because PHP does not
// allow for pass-by-reference in conjunction with a variable-length argument list,
// a dummy variable will have to be passed even if this functionality is unneeded.
//
// Need to add other countries besides the US...
//
function ExpandPhoneNumber($sPhoneNumber,$sPhoneCountry,&$bWeird)
{
    $bWeird = false;
    $length = strlen($sPhoneNumber);

    switch ($sPhoneCountry) {

    case "United States":

        if ($length == 0)
            return "";

        // 7 digit phone # with extension
        else if (substr($sPhoneNumber,7,1) == "e")
            return substr($sPhoneNumber,0,3) . "-" . substr($sPhoneNumber,3,4) . " Ext." . substr($sPhoneNumber,8,6);

        // 10 digit phone # with extension
        else if (substr($sPhoneNumber,10,1) == "e")
            return substr($sPhoneNumber,0,3) . "-" . substr($sPhoneNumber,3,3) . "-" . substr($sPhoneNumber,6,4) . " Ext." . substr($sPhoneNumber,11,6);

        else if ($length == 7)
            return substr($sPhoneNumber,0,3) . "-" . substr($sPhoneNumber,3,4);

        else if ($length == 10)
            return substr($sPhoneNumber,0,3) . "-" . substr($sPhoneNumber,3,3) . "-" . substr($sPhoneNumber,6,4);

        // Otherwise, there is something weird stored, so just leave it untouched and set the flag
        else
        {
            $bWeird = true;
            return $sPhoneNumber;
        }

    break;

    // If the country is unknown, we don't know how to format it, so leave it untouched
    default:
        return $sPhoneNumber;
    }
}

//
// Prints age in years, or in months if less than one year old
//
function PrintAge($Month,$Day,$Year,$Flags)
{
    echo FormatAge ($Month,$Day,$Year,$Flags);
}

//
// Formats an age string: age in years, or in months if less than one year old
//
function FormatAge($Month,$Day,$Year,$Flags)
{
    if (($Flags & 1) ) //||!$_SESSION['bSeePrivacyData']
    {
        return;
    
    }

    if ($Year > 0)
    {
        if ($Year == date("Y"))
        {
            $monthCount = date("m") - $Month;
            if ($Day > date("d"))
                $monthCount--;
            if ($monthCount == 1)
                return (gettext("1 month old"));
            else
                return ( $monthCount . " " . gettext("months old"));
        }
        elseif ($Year == date("Y")-1)
        {
            $monthCount =  12 - $Month + date("m");
            if ($Day > date("d"))
                $monthCount--;
            if ($monthCount >= 12)
                return ( gettext("1 year old"));
            elseif ($monthCount == 1)
                return ( gettext("1 month old"));
            else
                return ( $monthCount . " " . gettext("months old"));
        }
        elseif ( $Month > date("m") || ($Month == date("m") && $Day > date("d")) )
            return ( date("Y")-1 - $Year . " " . gettext("years old"));
        else
            return ( date("Y") - $Year . " " . gettext("years old"));
    }
    else
        return ( gettext("Unknown"));
}

// Returns a string of a person's full name, formatted as specified by $Style
// $Style = 0  :  "Title FirstName MiddleName LastName, Suffix"
// $Style = 1  :  "Title FirstName MiddleInitial. LastName, Suffix"
// $Style = 2  :  "LastName, Title FirstName MiddleName, Suffix"
// $Style = 3  :  "LastName, Title FirstName MiddleInitial., Suffix"
//
function FormatFullName($Title, $FirstName, $MiddleName, $LastName, $Suffix, $Style)
{
    $nameString = "";

    switch ($Style) {

    case 0:
        if ($Title) $nameString .= $Title . " ";
        $nameString .= $FirstName;
        if ($MiddleName) $nameString .= " " . $MiddleName;
        if ($LastName) $nameString .= " " . $LastName;
        if ($Suffix) $nameString .= ", " . $Suffix;
        break;

    case 1:
        if ($Title) $nameString .= $Title . " ";
        $nameString .= $FirstName;
        if ($MiddleName) $nameString .= " " . strtoupper(mb_substr($MiddleName, 0, 1, 'UTF-8')) . ".";
        if ($LastName) $nameString .= " " . $LastName;
        if ($Suffix) $nameString .= ", " . $Suffix;
        break;

    case 2:
        if ($LastName) $nameString .= $LastName . ", ";
        if ($Title) $nameString .= $Title . " ";
        $nameString .= $FirstName;
        if ($MiddleName) $nameString .= " " . $MiddleName;
        if ($Suffix) $nameString .= ", " . $Suffix;
        break;

    case 3:
        if ($LastName) $nameString .= $LastName . ", ";
        if ($Title) $nameString .= $Title . " ";
        $nameString .= $FirstName;
        if ($MiddleName) $nameString .= " " . strtoupper(mb_substr($MiddleName, 0, 1, 'UTF-8')) . ".";
        if ($Suffix) $nameString .= ", " . $Suffix;
        break;
    }

    return $nameString;
}

// Generate a nicely formatted string for "FamilyName - Address / City, State" with available data
function FormatAddressLine($Address, $City, $State)
{
    $sText = "";

    if ($Address != "" || $City != "" || $State != "") { $sText = " - "; }
    $sText .= $Address;
    if ($Address != "" && ($City != "" || $State != "")) { $sText .= " / "; }
    $sText .= $City;
    if ($City != "" && $State != "") { $sText .= ", "; }
    $sText .= $State;

    return $sText;
}

//
// Formats the data for a custom field for display-only uses
//
function displayCustomField($type, $data, $special)
{
    global $cnInfoCentral;

    switch ($type)
    {
        // Handler for boolean fields
        case 1:
            if ($data == 'true')
                return gettext("Yes");
            elseif ($data == 'false')
                return gettext("No");
            break;

        // Handler for date fields
        case 2:
            return FormatDate($data);
            break;
        // Handler for text fields, years, seasons, numbers, money
        case 3:
        case 4:
        case 6:
        case 8:
        case 10:
            return $data;
            break;


        // Handler for extended text fields (MySQL type TEXT, Max length: 2^16-1)
        case 5:
            /*if (strlen($data) > 100) {
                return substr($data,0,100) . "...";
            }else{
                return $data;
            }
            */
            return $data;
            break;

        // Handler for season.  Capitalize the word for nicer display.
        case 7:
            return ucfirst($data);
            break;

        // Handler for "person from group"
        case 9:
            if ($data > 0) {
                $sSQL = "SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID =" . $data;
                $rsTemp = RunQuery($sSQL);
                extract(mysql_fetch_array($rsTemp));
                return $per_FirstName . " " . $per_LastName;
            }
            else return "";
            break;

        // Handler for phone numbers
        case 11:
            return ExpandPhoneNumber($data,$special,$dummy);
            break;

        // Handler for custom lists
        case 12:
            if ($data > 0) {
                $sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID = $special AND lst_OptionID = $data";
                $rsTemp = RunQuery($sSQL);
                extract(mysql_fetch_array($rsTemp));
                return $lst_OptionName;
            }
            else return "";
            break;

        // Otherwise, display error for debugging.
        default:
            return gettext("Invalid Editor ID!");
            break;
    }
}


//
// Generates an HTML form <input> line for a custom field
//
function formCustomField($type, $fieldname, $data, $special, $bFirstPassFlag)
{
    global $cnInfoCentral;

    switch ($type)
    {
        // Handler for boolean fields
        case 1: 
        	echo "<input type=\"radio\" Name=\"" . $fieldname . "\" value=\"true\""; 
        	if ($data == 'true') { 
        		echo " checked"; 
        	} 
        	echo ">".gettext("Yes"); 
        	echo "<input type=\"radio\" Name=\"" . $fieldname . "\" value=\"false\""; 
        	if ($data == 'false') { 
        		echo " checked"; 
        	} 
        	echo ">".gettext("No"); 
        	echo "<input type=\"radio\" Name=\"" . $fieldname . "\" value=\"\""; 
        	if (strlen($data) == 0) { 
        		echo " checked"; 
        	} 
        	echo ">".gettext("Unknown"); 
        	break; 
        // Handler for date fields
        case 2:
            echo "<input type=\"text\" id=\"" . $fieldname . "\" Name=\"" . $fieldname . "\" maxlength=\"10\" size=\"15\" value=\"" . $data . "\">&nbsp;<input type=\"image\" onclick=\"return showCalendar('$fieldname', 'y-mm-dd');\" src=\"Images/calendar.gif\"> " . gettext("[format: YYYY-MM-DD]");
            break;

        // Handler for 50 character max. text fields
        case 3:
            echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"50\" size=\"50\" value=\"" . htmlentities(stripslashes($data),ENT_NOQUOTES, "UTF-8") . "\">";
            break;

        // Handler for 100 character max. text fields
        case 4:
            echo "<textarea Name=\"" . $fieldname . "\" cols=\"40\" rows=\"2\" onKeyPress=\"LimitTextSize(this,100)\">" . htmlentities(stripslashes($data),ENT_NOQUOTES, "UTF-8") . "</textarea>";
            break;

        // Handler for extended text fields (MySQL type TEXT, Max length: 2^16-1)
        case 5:
            echo "<textarea Name=\"" . $fieldname . "\" cols=\"60\" rows=\"4\" onKeyPress=\"LimitTextSize(this, 65535)\">" . htmlentities(stripslashes($data),ENT_NOQUOTES, "UTF-8") . "</textarea>";
            break;

        // Handler for 4-digit year
        case 6:
            echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"4\" size=\"6\" value=\"" . $data . "\">";
            break;

        // Handler for season (drop-down selection)
        case 7:
            echo "<select name=\"$fieldname\">";
            echo "  <option value=\"none\">" . gettext("Select Season") . "</option>";
            echo "  <option value=\"winter\"";
            if ($data == 'winter') { echo " selected"; }
            echo ">" . gettext("Winter") . "</option>";
            echo "  <option value=\"spring\"";
            if ($data == 'spring') { echo " selected"; }
            echo ">" . gettext("Spring") . "</option>";
            echo "  <option value=\"summer\"";
            if ($data == 'summer') { echo "selected"; }
            echo ">" . gettext("Summer") . "</option>";
            echo "  <option value=\"fall\"";
            if ($data == 'fall') { echo " selected"; }
            echo ">" . gettext("Fall") . "</option>";
            echo "</select>";
            break;

        // Handler for integer numbers
        case 8:
            echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"11\" size=\"15\" value=\"" . $data . "\">";
            break;

        // Handler for "person from group"
        case 9:
            // ... Get First/Last name of everyone in the group, plus their person ID ...
            // In this case, prop_Special is used to store the Group ID for this selection box
            // This allows the group special-property designer to allow selection from a specific group

            $sSQL = "SELECT person_per.per_ID, person_per.per_FirstName, person_per.per_LastName
                        FROM person2group2role_p2g2r
                        LEFT JOIN person_per ON person2group2role_p2g2r.p2g2r_per_ID = person_per.per_ID
                        WHERE p2g2r_grp_ID = " . $special . " ORDER BY per_FirstName";

            $rsGroupPeople = RunQuery($sSQL);

            echo "<select name=\"" . $fieldname . "\">";
                echo "<option value=\"0\"";
                if ($data <= 0) echo " selected";
                echo ">" . gettext("Unassigned") . "</option>";
                echo "<option value=\"0\">-----------------------</option>";

                while ($aRow = mysql_fetch_array($rsGroupPeople))
                {
                    extract($aRow);

                    echo "<option value=\"" . $per_ID . "\"";
                    if ($data == $per_ID) echo " selected";
                    echo ">" . $per_FirstName . "&nbsp;" . $per_LastName . "</option>";
                }

            echo "</select>";
            break;

        // Handler for money amounts
        case 10:
            echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"13\" size=\"16\" value=\"" . $data . "\">";
            break;

        // Handler for phone numbers
        case 11:

            // This is silly. Perhaps ExpandPhoneNumber before this function is called!
            // this business of overloading the special field is really troublesome when trying to follow the code.
            if ($bFirstPassFlag)
                // in this case, $special is the phone country
                $data = ExpandPhoneNumber($data,$special,$bNoFormat_Phone);
            if (isset($_POST[$fieldname . "noformat"]))
                $bNoFormat_Phone = true;

            echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"30\" size=\"30\" value=\"" . htmlentities(stripslashes($data),ENT_NOQUOTES, "UTF-8") . "\">";
            echo "<br><input type=\"checkbox\" name=\"" . $fieldname . "noformat\" value=\"1\"";
            if ($bNoFormat_Phone) echo " checked";
            echo ">" . gettext("Do not auto-format");
            break;

        // Handler for custom lists
        case 12:
            $sSQL = "SELECT * FROM list_lst WHERE lst_ID = $special ORDER BY lst_OptionSequence";
            $rsListOptions = RunQuery($sSQL);

            echo "<select name=\"" . $fieldname . "\">";
                echo "<option value=\"0\" selected>" . gettext("Unassigned") . "</option>";
                echo "<option value=\"0\">-----------------------</option>";

                while ($aRow = mysql_fetch_array($rsListOptions))
                {
                    extract($aRow);
                    echo "<option value=\"" . $lst_OptionID . "\"";
                    if ($data == $lst_OptionID) echo " selected";
                    echo ">" . $lst_OptionName . "</option>";
                }

            echo "</select>";
            break;

        // Otherwise, display error for debugging.
        default:
            echo "<b>" . gettext("Error: Invalid Editor ID!") . "</b>";
            break;
    }
}

function assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut = "future") {
// This function takes a year, month and day from parseAndValidateDate.  On success this
// function returns a string in the form "YYYY-MM-DD".  It returns FALSE on failure.
// The year can be either 2 digit or 4 digit.  If a 2 digit year is passed the $passfut
// indicates whether to return a 4 digit year in the past or the future.  The parameter
// $passfut is not needed for the current year.  If unspecified it assumes the two digit year
// is either this year or one of the next 99 years.


    // Parse the year
    // Take a 2 or 4 digit year and return a 4 digit year.  Use $pasfut to determine if
    // two digit year maps to past or future 4 digit year.
    if (strlen($sYear) == 2) {
        $thisYear = date('Y');
        $twoDigit = substr($thisYear,2,2);
        if ($sYear == $twoDigit) {
            // Assume 2 digit year is this year
            $sYear = substr($thisYear,0,4);
        } elseif ($pasfut == "future") {
            // Assume 2 digit year is in next 99 years
            if ($sYear > $twoDigit) {
                $sYear = substr($thisYear,0,2) . $sYear;
            } else {
                $sNextCentury = $thisYear + 100;
                $sYear = substr($sNextCentury,0,2) . $sYear;
            }
        } else {
            // Assume 2 digit year was is last 99 years
            if ($sYear < $twoDigit) {
                $sYear = substr($thisYear,0,2) . $sYear;
            } else {
                $sLastCentury = $thisYear - 100;
                $sYear = substr($sLastCentury,0,2) . $sYear;
            }
        }
    } elseif (strlen($sYear) == 4) {
        $sYear = $sYear;
    } else {
        return FALSE;
    }

    // Parse the Month
    // Take a one or two character month and return a two character month
    if (strlen($sMonth) == 1) {
        $sMonth = "0" . $sMonth;
    } elseif (strlen($sMonth) == 2) {
        $sMonth = $sMonth;
    } else {
        return FALSE;
    }

    // Parse the Day
    // Take a one or two character day and return a two character day
    if (strlen($sDay) == 1) {
        $sDay = "0" . $sDay;
    } elseif (strlen($sDay) == 2) {
        $sDay = $sDay;
    } else {
        return FALSE;
    }

    $sScanString = $sYear . "-" . $sMonth . "-" . $sDay;
    list($iYear, $iMonth, $iDay) = sscanf($sScanString,"%04d-%02d-%02d");

    if ( checkdate($iMonth,$iDay,$iYear) )  {
        return $sScanString;
    } else {
        return FALSE;
    }       

}

function parseAndValidateDate($data, $locale = "US", $pasfut = "future") {
// This function was written because I had no luck finding a PHP
// function that would reliably parse a human entered date string for 
// dates before 1/1/1970 or after 1/19/2038 on any Operating System.
//
// This function has hooks for US English M/D/Y format as well as D/M/Y.  The
// default is M/D/Y for date.  To change to D/M/Y use anything but "US" for
// $locale.
//
// Y-M-D is allowed if the delimiter is "-" instead of "/"
//
// In order to help this function guess a two digit year a "past" or "future" flag is
// passed to this function.  If no flag is passed the function assumes that two digit
// years are in the future (or the current year).
//
// Month and day may be either 1 character or two characters (leading zeroes are not
// necessary)


    // Determine if the delimiter is "-" or "/".  The delimiter must appear
    // twice or a FALSE will be returned. 

    if (substr_count($data,'-') == 2) { 
        // Assume format is Y-M-D
        $iFirstDelimiter = strpos($data,'-');
        $iSecondDelimiter = strpos($data,'-',$iFirstDelimiter+1);

        // Parse the year.
        $sYear = substr($data, 0, $iFirstDelimiter);        

        // Parse the month
        $sMonth = substr($data, $iFirstDelimiter+1, $iSecondDelimiter-$iFirstDelimiter-1);

        // Parse the day
        $sDay = substr($data, $iSecondDelimiter+1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);

    } elseif ((substr_count($data,'/') == 2) && ($locale == "US")) { 
        // Assume format is M/D/Y
        $iFirstDelimiter = strpos($data,'/');
        $iSecondDelimiter = strpos($data,'/',$iFirstDelimiter+1);

        // Parse the month
        $sMonth = substr($data, 0, $iFirstDelimiter);       

        // Parse the day
        $sDay = substr($data, $iFirstDelimiter+1, $iSecondDelimiter-$iFirstDelimiter-1);

        // Parse the year
        $sYear = substr($data, $iSecondDelimiter+1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);

    } elseif (substr_count($data,'/') == 2) { 
        // Assume format is D/M/Y
        $iFirstDelimiter = strpos($data,'/');
        $iSecondDelimiter = strpos($data,'/',$iFirstDelimiter+1);

        // Parse the day
        $sDay = substr($data, 0, $iFirstDelimiter);     

        // Parse the month
        $sMonth = substr($data, $iFirstDelimiter+1, $iSecondDelimiter-$iFirstDelimiter-1);

        // Parse the year
        $sYear = substr($data, $iSecondDelimiter+1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);

    }

    // If we made it this far it means the above logic was unable to parse the date.
    // Now try to parse using the function strtotime().  The strtotime() function does 
    // not gracefully handle dates outside the range 1/1/1970 to 1/19/2038.  For this
    // reason consider strtotime() as a function of last resort.
    $timeStamp = strtotime($data);
    if ($timeStamp == FALSE || $timeStamp <= 0) {
        // Some Operating Sytems and older versions of PHP do not gracefully handle 
        // negative timestamps.  Bail if the timestamp is negative.
        return FALSE;
    }

    // Now use the date() function to convert timestamp into YYYY-MM-DD
    $dateString = date("Y-m-d", $timeStamp);
    
    if (strlen($dateString) != 10) {
        // Common sense says we have a 10 charater string.  If not, something is wrong
        // and it's time to bail.
        return FALSE;
    }

    if ($dateString > "1970-01-01" && $dateString < "2038-01-19") {
        // Success!
        return $dateString;
    }

    // Should not have made it this far.  Something is wrong so bail.
    return FALSE;
}

// Processes and Validates custom field data based on its type.
//
// Returns false if the data is not valid, true otherwise.
//
function validateCustomField($type, &$data, $col_Name, &$aErrors)
{
    global $aLocaleInfo;
    $bErrorFlag = false;
    $aErrors[$col_Name] = "";
    
    switch ($type)
    {
        // Validate a date field
        case 2:
            if (strlen($data) > 0)
            {
                $dateString = parseAndValidateDate($data);
                if ( $dateString === FALSE ) {
                    $aErrors[$col_Name] = gettext("Not a valid date");
                    $bErrorFlag = true;
                } else {
                    $data = $dateString;
                }
            }
            break;

        // Handler for 4-digit year
        case 6:
            if (strlen($data) != 0)
            {
                if (!is_numeric($data) || strlen($data) != 4)
                {
                    $aErrors[$col_Name] = gettext("Invalid Year");
                    $bErrorFlag = True;
                }
                elseif ($data > 2155 || $data < 1901)
                {
                    $aErrors[$col_Name] = gettext("Out of range: Allowable values are 1901 to 2155");
                    $bErrorFlag = True;
                }
            }
            break;

        // Handler for integer numbers
        case 8:
            if (strlen($data) != 0)
            {
                if ($aLocalInfo["thousands_sep"]) {
                    $data = preg_replace('/'.$aLocaleInfo["thousands_sep"].'/i', "", $data);  // remove any thousands separators
                }
                if (!is_numeric($data))
                {
                    $aErrors[$col_Name] = gettext("Invalid Number");
                    $bErrorFlag = True;
                }
                elseif ($data < -2147483648 || $data > 2147483647)
                {
                    $aErrors[$col_Name] = gettext("Number too large. Must be between -2147483648 and 2147483647");
                    $bErrorFlag = True;
                }
            }
            break;

        // Handler for money amounts
        case 10:
            if (strlen($data) != 0)
            {   if ($aLocaleInfo["mon_thousands_sep"]) {
                    $data = preg_replace('/'.$aLocaleInfo["mon_thousands_sep"].'/i', "", $data);
                }
                if (!is_numeric($data))
                {
                    $aErrors[$col_Name] = gettext("Invalid Number");
                    $bErrorFlag = True;
                }
                elseif ($data > 999999999.99)
                {
                    $aErrors[$col_Name] = gettext("Money amount too large. Maximum is $999999999.99");
                    $bErrorFlag = True;
                }
            }
            break;

        // Otherwise ignore.. some types do not need validation or filtering
        default:
            break;
    }
    return !$bErrorFlag;
}

// Generates SQL for custom field update
//
// $special is currently only used for the phone country and the list ID for custom drop-down choices.
//
function sqlCustomField(&$sSQL, $type, $data, $col_Name, $special)
{
    switch($type)
    {
        // boolean
        case 1:
            switch ($data) {
                case "false":
                    $data = "'false'";
                    break;
                case "true":
                    $data = "'true'";
                    break;
                default:
                    $data = "NULL";
                    break;
            }

            $sSQL .= $col_Name . " = " . $data . ", ";
            break;

        // date
        case 2:
            if (strlen($data) > 0) {
                $sSQL .= $col_Name . " = \"" . $data . "\", ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        // year
        case 6:
            if (strlen($data) > 0) {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        // season
        case 7:
            if ($data != 'none') {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        // integer, money
        case 8:
        case 10:
            if (strlen($data) > 0) {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        // list selects
        case 9:
        case 12:
            if ($data != 0) {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        // strings
        case 3:
        case 4:
        case 5:
            if (strlen($data) > 0) {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        // phone
        case 11:
            if (strlen($data) > 0) {
                if (!isset($_POST[$col_Name . "noformat"]))
                    $sSQL .= $col_Name . " = '" . CollapsePhoneNumber($data,$special) . "', ";
                else
                    $sSQL .= $col_Name . " = '" . $data . "', ";
            }
            else {
                $sSQL .= $col_Name . " = NULL, ";
            }
            break;

        default:
            $sSQL .= $col_Name . " = '" . $data . "', ";
            break;
    }
}

// Runs the ToolTips
// By default ToolTips are diplayed, unless turned off in the user settings.
function addToolTip($ToolTip)
{
    global $bToolTipsOn;
    if ($bToolTipsOn)
    {
        $ToolTipText = "onmouseover=\"domTT_activate(this, event, 'content', '" . $ToolTip . "');\"";
        echo $ToolTipText;
    }
}

// Wrapper for number_format that uses the locale information
// There are three modes: money, integer, and intmoney (whole number money)
function formatNumber($iNumber,$sMode = 'integer')
{
    global $aLocaleInfo;

    switch ($sMode) {
        case 'money':
            return $aLocaleInfo["currency_symbol"] . ' ' . number_format($iNumber,$aLocaleInfo["frac_digits"],$aLocaleInfo["mon_decimal_point"],$aLocaleInfo["mon_thousands_sep"]);
            break;

        case 'intmoney':
            return $aLocaleInfo["currency_symbol"] . ' ' . number_format($iNumber,0,'',$aLocaleInfo["mon_thousands_sep"]);
            break;

        case 'float':
            $iDecimals = 2; // need to calculate # decimals in original number
            return number_format($iNumber,$iDecimals,$aLocaleInfo["mon_decimal_point"],$aLocaleInfo["mon_thousands_sep"]);
            break;

        case 'integer':
        default:
            return number_format($iNumber,0,'',$aLocaleInfo["mon_thousands_sep"]);
            break;
    }
}

// Format a BirthDate
// Optionally, the separator may be specified.  Default is YEAR-MN-DY
function FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, $sSeparator = "-", $bFlags)
{
    if ($bFlags == 1 || $per_BirthYear == "" )  //Person Would Like their Age Hidden or BirthYear is not known.
    {
        $birthYear = "1000";
    }
    else 
    {
        $birthYear = $per_BirthYear;
    }

    if ($per_BirthMonth > 0 && $per_BirthDay > 0)
    {
        if ($per_BirthMonth < 10)
            $dBirthMonth = "0" . $per_BirthMonth;
        else
            $dBirthMonth = $per_BirthMonth;
        if ($per_BirthDay < 10)
            $dBirthDay = "0" . $per_BirthDay;
        else
            $dBirthDay = $per_BirthDay;

        $dBirthDate = $dBirthMonth . $sSeparator . $dBirthDay;
        if (is_numeric($birthYear))
        {
            $dBirthDate = $birthYear . $sSeparator . $dBirthDate;
            if (checkdate($dBirthMonth,$dBirthDay,$birthYear))
            {
               $dBirthDate = FormatDate($dBirthDate);
                    if (substr($dBirthDate, -6, 6) == ", 1000")
                    {
                        $dBirthDate = str_replace(", 1000", "", $dBirthDate);
                    }
            }
        }
    }
    elseif (is_numeric($birthYear) && $birthYear != 1000 )  //Person Would Like Their Age Hidden 
    {
        $dBirthDate = $birthYear;
    }
    else
    {
        $dBirthDate = "";
    }

    return $dBirthDate;
}

function FilenameToFontname($filename, $family)
{
    if($filename==$family)
    {
        return ucfirst($family);
    }
    else
    {
        if(strlen($filename) - strlen($family) == 2)
        {
            return ucfirst($family).gettext(" Bold Italic");
        }
        else
        {
            if(substr($filename, strlen($filename) - 1) == "i")
                return ucfirst($family).gettext(" Italic");
            else
                return ucfirst($family).gettext(" Bold");
        }
    }
}

function FontFromName($fontname)
{
    $fontinfo = explode(" ", $fontname);
    switch (count($fontinfo)) {
        case 1:
            return array($fontinfo[0], '');
        case 2:
            return array($fontinfo[0], substr($fontinfo[1], 0, 1));
        case 3:
            return array($fontinfo[0], substr($fontinfo[1], 0, 1).substr($fontinfo[2], 0, 1));
    }
}

// Added for AddEvent.php
function createTimeDropdown($start,$stop,$mininc,$hoursel,$minsel)
{
    for ($hour = $start; $hour <= $stop; $hour++)
    {
        if ($hour == '0')
        {
            $disphour = '12';
            $ampm = 'AM';
        }
        elseif ($hour == '12')
        {
            $disphour = '12';
            $ampm = 'PM';
        }
        else if ($hour >= '13' && $hour <= '21')
        {
            $test = $hour - 12;
            $disphour = ' ' . $test;
            $ampm = 'PM';
        }
        else if ($hour >= '22' && $hour <= '23')
        {
            $disphour = $hour - 12;
            $ampm = 'PM';
        }
        else
        {
            $disphour = $hour;
            $ampm = 'AM';
        }

        for ($min = 0; $min <= 59; $min += $mininc)
        {
            if ($hour >= '1' && $hour <= '9')
            {
                if($min >= '0' && $min <= '9')
                {
                    if ($hour == $hoursel && $min == $minsel)
                    {
                        echo '<option value="0'.$hour.':0'.$min.':00" selected> '.$disphour.':0'.$min.' '.$ampm.'</option>'."\n";
                    }
                    else
                    {
                        echo '<option value="0'.$hour.':0'.$min.':00"> '.$disphour.':0'.$min.' '.$ampm.'</option>'."\n";
                    }
                }
                else
                {
                    if ($hour == $hoursel && $min == $minsel)
                    {
                        echo '<option value="0'.$hour.":".$min.':00" selected> '.$disphour.':'.$min.' '.$ampm.'</option>'."\n";
                    }
                    else
                    {
                        echo '<option value="0'.$hour.":".$min.':00"> '.$disphour.':'.$min.' '.$ampm.'</option>'."\n";
                    }
                }
            }
            else
            {
                if ($min >= '0' && $min <= '9')
                {
                    if ($hour == $hoursel && $min == $minsel)
                    {
                        echo '<option value="'.$hour.':0'.$min.':00" selected>'.$disphour.':0'.$min.' '.$ampm.'</option>'."\n";
                    }
                    else
                    {
                        echo '<option value="'.$hour.':0'.$min.':00">'.$disphour.':0'.$min.' '.$ampm.'</option>'."\n";
                    }
                }
                else
                {
                    if ($hour == $hoursel && $min == $minsel)
                    {
                        echo '<option value="'.$hour.":".$min.':00" selected>'.$disphour.':'.$min.' '.$ampm.'</option>'."\n";
                    }
                    else
                    {
                        echo '<option value="'.$hour.":".$min.':00">'.$disphour.':'.$min.' '.$ampm.'</option>'."\n";
                    }
                }
            }
        }
    }
}

// Figure out the class ID for "Member", should be one (1) unless they have been playing with the 
// classification manager.
function FindMemberClassID ()
{
    //Get Classifications
    $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
    $rsClassifications = RunQuery($sSQL);

    while ($aRow = mysql_fetch_array($rsClassifications))
    {
        extract($aRow);
        if ($lst_OptionName == gettext ("Member"))
            return ($lst_OptionID);
    }
    return (1); // Should not get here, but if we do get here use the default value.
}

// Prepare data for entry into MySQL database.
// This function solves the problem of inserting a NULL value into MySQL since
// MySQL will not accept 'NULL'.  One drawback is that it is not possible
// to insert the character string "NULL" because it will be inserted as a MySQL NULL!
// This will produce a database error if NULL's are not allowed!  Do not use this
// function if you intend to insert the character string "NULL" into a field.
function MySQLquote ($sfield)
{
    $sfield = trim($sfield);

    if ($sfield == "NULL")
        return "NULL";
    elseif ($sfield == "'NULL'")
        return "NULL";
    elseif ($sfield == "")
        return "NULL";
    elseif ($sfield == "''")
        return "NULL";
    else {
        if ((substr($sfield, 0, 1) == "'") && (substr($sfield, strlen($sfield)-1, 1)) == "'")
            return $sfield;
        else 
            return "'" . $sfield . "'";
    }
}

//Function to check email
//From http://www.tienhuis.nl/php-email-address-validation-with-verify-probe
//Functions checkndsrr and getmxrr are not enabled on windows platforms & therefore are disabled
//Future use may be to enable a Admin option to enable these options
//domainCheck verifies domain is valid using dns, verify uses SMTP to verify actual account exists on server

function checkEmail($email, $domainCheck = false, $verify = false, $return_errors=false) {
    global $checkEmailDebug;
    if($checkEmailDebug) {echo "<pre>";}
    # Check syntax with regex
    if (preg_match('/^([a-zA-Z0-9\._\+-]+)\@((\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,7}|[0-9]{1,3})(\]?))$/', $email, $matches)) {
        $user = $matches[1];
        $domain = $matches[2];
        # Check availability of DNS MX records
        if ($domainCheck && function_exists('checkdnsrr')) {
            # Construct array of available mailservers
            if(getmxrr($domain, $mxhosts, $mxweight)) {
                for($i=0;$i<count($mxhosts);$i++){
                    $mxs[$mxhosts[$i]] = $mxweight[$i];
                }
                asort($mxs);
                $mailers = array_keys($mxs);
            } elseif(checkdnsrr($domain, 'A')) {
                $mailers[0] = gethostbyname($domain);
            } else {
                $mailers=array();
            }
            $total = count($mailers);
            # Query each mailserver
            if($total > 0 && $verify) {
                # Check if mailers accept mail
                for($n=0; $n < $total; $n++) {
                    # Check if socket can be opened
                    if($checkEmailDebug) { echo "Checking server $mailers[$n]...\n";}
                    $connect_timeout = 2;
                    $errno = 0;
                    $errstr = 0;
                    $probe_address = $sToEmailAddress;
                    # Try to open up socket
                    if($sock = @fsockopen($mailers[$n], 25, $errno , $errstr, $connect_timeout)) {
                        $response = fgets($sock);
                        if($checkEmailDebug) {echo "Opening up socket to $mailers[$n]... Succes!\n";}
                        stream_set_timeout($sock, 5);
                        $meta = stream_get_meta_data($sock);
                        if($checkEmailDebug) { echo "$mailers[$n] replied: $response\n";}
                        $cmds = array(
                            "HELO $sSMTPHost",  # Be sure to set this correctly!
                            "MAIL FROM: <$probe_address>",
                            "RCPT TO: <$email>",
                            "QUIT",
                        );
                        # Hard error on connect -> break out
                        if(!$meta['timed_out'] && !preg_match('/^2\d\d[ -]/', $response)) {
                            $error = "Error: $mailers[$n] said: $response\n";
                            break;
                        }
                        foreach($cmds as $cmd) {
                            $before = microtime(true);
                            fputs($sock, "$cmd\r\n");
                            $response = fgets($sock, 4096);
                            $t = 1000*(microtime(true)-$before);
                            if($checkEmailDebug) {echo htmlentities("$cmd\n$response") . "(" . sprintf('%.2f', $t) . " ms)\n";}
                            if(!$meta['timed_out'] && preg_match('/^5\d\d[ -]/', $response)) {
                                $error = "Unverified address: $mailers[$n] said: $response";
                                break 2;
                            }
                        }
                        fclose($sock);
                        if($checkEmailDebug) { echo "Succesful communication with $mailers[$n], no hard errors, assuming OK";}
                        break;
                    } elseif($n == $total-1) {
                        $error = "None of the mailservers listed for $domain could be contacted";
                    }
                }
            } elseif($total <= 0) {
                $error = "No usable DNS records found for domain '$domain'";
            }
        }
    } else {
        $error = 'Address syntax not correct';
    }
    if($checkEmailDebug) { echo "</pre>";}
    #echo "</pre>";
    if($return_errors) {
        # Give back details about the error(s).
        # Return FALSE if there are no errors.
        # Keep this in mind when using it like:
        # if(checkEmail($addr)) {
        # Because of this strange behaviour this
        # is not default ;-)
        if(isset($error)) return htmlentities($error); else return false;
    } else {
        # 'Old' behaviour, simple to understand
        if(isset($error)) return false; else return true;
    }
}

function getFamilyList($sDirRoleHead, $sDirRoleSpouse, $classification = 0, $sSearchTerm = 0) {

    if ($classification) {
	if($sSearchTerm) {
		$whereClause = " WHERE per_cls_ID='" . $classification . "' AND fam_Name LIKE '%".$sSearchTerm."%' ";
	} else {
		$whereClause = " WHERE per_cls_ID='" . $classification . "' ";
	}
        $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam LEFT JOIN person_per ON fam_ID = per_fam_ID $whereClause ORDER BY fam_Name";
    } else {
	if($sSearchTerm) {
		$whereClause = " WHERE fam_Name LIKE '%".$sSearchTerm."%' ";
	} else {
		$whereClause = "";
	}
        $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam $whereClause ORDER BY fam_Name";
    }

    $rsFamilies = RunQuery($sSQL);

    // Build Criteria for Head of Household
    if (!$sDirRoleHead)
        $sDirRoleHead = "1";
    $head_criteria = " per_fmr_ID = " . $sDirRoleHead;
    // If more than one role assigned to Head of Household, add OR
    $head_criteria = str_replace(",", " OR per_fmr_ID = ", $head_criteria);
    // Add Spouse to criteria
    if (intval($sDirRoleSpouse) > 0)
        $head_criteria .= " OR per_fmr_ID = $sDirRoleSpouse";
    // Build array of Head of Households and Spouses with fam_ID as the key
    $sSQL = "SELECT per_FirstName, per_fam_ID FROM person_per WHERE per_fam_ID > 0 AND (" . $head_criteria . ") ORDER BY per_fam_ID";
    $rs_head = RunQuery($sSQL);
    $aHead = array ();
    while (list ($head_firstname, $head_famid) = mysql_fetch_row($rs_head)) {
        if ($head_firstname && isset ($aHead[$head_famid])) {
            $aHead[$head_famid] .= " & " . $head_firstname;
        } elseif ($head_firstname) {
            $aHead[$head_famid] = $head_firstname;
        }
    }
    $familyArray = array();
    while ($aRow = mysql_fetch_array($rsFamilies)) {
        extract($aRow);
        $name = $fam_Name;
        if (isset ($aHead[$fam_ID])) {
            $name .= ", " . $aHead[$fam_ID];
        }
        $name .= " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);

        $familyArray[$fam_ID] = $name;
    }

    return $familyArray;
}

function buildFamilySelect($iFamily, $sDirRoleHead, $sDirRoleSpouse) {
    //Get Families for the drop-down
    $familyArray = getFamilyList($sDirRoleHead, $sDirRoleSpouse);
    foreach ($familyArray as $fam_ID => $fam_Data) {
        $html .= "<option value=\"" . $fam_ID . "\"";
        if ($iFamily == $fam_ID) {
            $html .= " selected";
        }
        $html .= ">" . $fam_Data;
    }
    return $html;
}

function genGroupKey($methodSpecificID, $famID, $fundIDs, $date) {
    $uniqueNum = 0;
    while (1) {
        $GroupKey = $methodSpecificID . "|" . $uniqueNum . "|" . $famID . "|" . $fundIDs . "|" . $date;
        $sSQL = "SELECT COUNT(plg_GroupKey) FROM pledge_plg WHERE plg_PledgeOrPayment='Payment' AND plg_GroupKey='" . $GroupKey . "'";
        $rsResults = RunQuery($sSQL);
        list($numGroupKeys) = mysql_fetch_row($rsResults);
        if ($numGroupKeys) {
            ++$uniqueNum;
        } else {
            return $GroupKey;
        }
    }
}

?>
