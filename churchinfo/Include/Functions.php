<?php
/*******************************************************************************
 *
 *  filename    : /Include/Functions.php
 *  last change : 2003-01-07
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Initialization common to all InfoCentral scripts

// Set error reporting
if ($debug == true)
        Error_reporting ( E_ALL ^ E_NOTICE);
else
        error_reporting(0);

// Establish the database connection
$cnInfoCentral = mysql_connect($sSERVERNAME,$sUSER,$sPASSWORD);
mysql_select_db($sDATABASE);

// Initialize the session
session_start();

//
// Basic security checks:
//
if (!$bSuppressSessionTests)  // This is used for the login page only.
{
        // Basic security: If the UserID isn't set (no session), redirect to the login page
        if (!isset($_SESSION['iUserID']))
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
}
// End of basic security checks

// If Magic Quotes is turned off, do the same thing manually..
if (!$_SESSION['bHasMagicQuotes'])
{
        foreach ($_REQUEST as $key=>$value) $value = addslashes($value);
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

// Are they adding anything to the People Cart?
if (isset($_GET["AddToPeopleCart"])) {
        AddToPeopleCart(FilterInput($_GET["AddToPeopleCart"],'int'));
        $sGlobalMessage = gettext("Selected record successfully added to the Cart.");
}

// Are they removing anything from the People Cart?
if (isset($_GET["RemoveFromPeopleCart"])) {
        RemoveFromPeopleCart(FilterInput($_GET["RemoveFromPeopleCart"],'int'));
        $sGlobalMessage = gettext("Selected record successfully removed from the Cart.");
}

// Are they emptying their cart?
if ($_GET["Action"] == "EmptyCart") {
        unset($_SESSION['aPeopleCart']);
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

// Convert a relative URL into an absolute URL and redirect the browser there.
function Redirect($sRelativeURL)
{
        global $sRootPath;

        if (!$_SESSION['bSecureServer'])
        {
                $sProtocol = "http://";
                if ($_SESSION['iServerPort'] != 80)
                        $sPort = ":" . $_SESSION['iServerPort'];
                else
                        $sPort = "";
        }
        else
        {
                $sProtocol = "https://";
                if ($_SESSION['iServerPort'] != 443)
                        $sPort = ":" . $_SESSION['iServerPort'];
                else
                        $sPort = "";
        }

        header("Location: " . $sProtocol . $_SERVER['HTTP_HOST'] . $sPort . $sRootPath . "/" . $sRelativeURL);
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
}

// Sanitizes user input as a security measure
// Optionally, a filtering type and size may be specified.  By default, strip any tags from a string.
function FilterInput($sInput,$type = 'string',$size = 1)
{
        if (strlen($sInput) > 0)
        {
                switch($type) {
                        case 'string':
                                // or use htmlspecialchars( stripslashes( ))
                                return strip_tags(trim($sInput));
                        case 'htmltext':
                                return strip_tags(trim($sInput),'<a><b><i><u>');
                        case 'char':
                                return substr(trim($sInput),0,$size);
                        case 'int':
                                return (int) trim($sInput);
                        case 'float':
                                return (float) trim($sInput);
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

function ConvertMySQLDate($datestr)
{
        if (strlen($datestr)) {
                list($year,$month,$day,$hour,$minute,$second) = split("([^0-9])",$datestr);
                return date("U",mktime($hour,$minute,$second,$month,$day,$year));
        } else {
                return "";
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
                if (!in_array($sID, $_SESSION['aPeopleCart'], false)) {
                        $_SESSION['aPeopleCart'][] = $sID;
                }
        }
        else
                $_SESSION['aPeopleCart'][] = $sID;
}

function RemoveFromPeopleCart($sID)
{
        // make sure the cart array exists
        if(isset($_SESSION['aPeopleCart']))
        {
                while ($element = each($_SESSION['aPeopleCart'])) {
                        if ( $element[value] == $sID ) {
                                unset( $_SESSION['aPeopleCart'][$element[key]] );
                                break;
                        }
                }
        }
}

// Reinstated by Todd Pillars for Event Listing
// Takes MYSQL DateTime
// bWithtime 1 to be displayed
function FormatDate($dDate, $bWithTime)
{
    $arr = explode(" ", $dDate);
    $arr0 = explode("-", $arr[0]);
    $arr1 = explode(":", $arr[1]);

    if ($bWithTime)
    {
        return date("M d Y  g:i a", mktime($arr1[0], $arr1[1], $arr1[2], $arr0[1], $arr0[2], $arr0[0]));
    }
    else
    {
        return date("M d Y", mktime($arr1[0], $arr1[1], $arr1[2], $arr0[1], $arr0[2], $arr0[0]));
    }
        return $dDate;
}

// this might be cruft
function mysql_to_epoch($datestr)
{
        list($year, $month, $day, $hour, $minute, $second) = split("([^0-9])", $datestr);
        return date("U", mktime($hour, $minute, $second, $month, $day, $year));
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
                                return true;
                        } else {
                                return false;
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
        if ($Flags & 1) {
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
                if ($MiddleName) $nameString .= " " . strtoupper($MiddleName{0}) . ".";
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
                if ($MiddleName) $nameString .= " " . strtoupper($MiddleName{0}) . ".";
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

                // Handler for date fields, text fields, years, seasons, numbers, money
                case 2:
                case 3:
                case 4:
                case 6:
                case 8:
                case 10:
                        return $data;
                        break;


                // Handler for extended text fields (MySQL type TEXT, Max length: 2^16-1)
                case 5:
                        if (strlen($data) > 100)
                                return substr($data,0,100) . "...";
                        else
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
                                if ($data == 'true') { echo " checked"; }
                                echo ">Yes";
                        echo "<input type=\"radio\" Name=\"" . $fieldname . "\" value=\"false\"";
                                if ($data == 'false') { echo " checked"; }
                                echo ">No";
                        echo "<input type=\"radio\" Name=\"" . $fieldname . "\" value=\"\"";
                                if (strlen($data) == 0) { echo " checked"; }
                                echo ">Unknown";
                        break;

                // Handler for date fields
                case 2:
                        echo "<input type=\"text\" id=\"" . $fieldname . "\" Name=\"" . $fieldname . "\" maxlength=\"10\" size=\"15\" value=\"" . $data . "\">&nbsp;<input type=\"image\" onclick=\"return showCalendar('$fieldname', 'y-mm-dd');\" src=\"Images/calendar.gif\"> " . gettext("[format: YYYY-MM-DD]");
                        break;

                // Handler for 50 character max. text fields
                case 3:
                        echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"50\" size=\"50\" value=\"" . htmlentities(stripslashes($data)) . "\">";
                        break;

                // Handler for 100 character max. text fields
                case 4:
                        echo "<textarea Name=\"" . $fieldname . "\" cols=\"40\" rows=\"2\" onKeyPress=\"LimitTextSize(this,100)\">" . htmlentities(stripslashes($data)) . "</textarea>";
                        break;

                // Handler for extended text fields (MySQL type TEXT, Max length: 2^16-1)
                case 5:
                        echo "<textarea Name=\"" . $fieldname . "\" cols=\"60\" rows=\"4\" onKeyPress=\"LimitTextSize(this, 65535)\">" . htmlentities(stripslashes($data)) . "</textarea>";
                        break;

                // Handler for 4-digit year
                case 6:
                        echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"4\" size=\"6\" value=\"" . $data . "\">";
                        break;

                // Handler for season (drop-down selection)
                case 7:
                        ?>
                        <select name="<?php echo $fieldname; ?>">
                                <option value="none"><?php echo gettext("Select Season"); ?></option>
                                <option value="winter" <?php if ($data == 'winter') { echo "selected"; } ?>><?php echo gettext("Winter"); ?></option>
                                <option value="spring" <?php if ($data == 'spring') { echo "selected"; } ?>><?php echo gettext("Spring"); ?></option>
                                <option value="summer" <?php if ($data == 'summer') { echo "selected"; } ?>><?php echo gettext("Summer"); ?></option>
                                <option value="fall" <?php if ($data == 'fall') { echo "selected"; } ?>><?php echo gettext("Fall"); ?></option>
                        </select>
                        <?php
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
                        if ($bFirstPassFlag)
                                // in this case, $special is the phone country
                                $data = ExpandPhoneNumber($data,$special,$bNoFormat_Phone);
                        if (isset($_POST[$fieldname . "noformat"]))
                                $bNoFormat_Phone = true;

                        echo "<input type=\"text\" Name=\"" . $fieldname . "\" maxlength=\"30\" size=\"30\" value=\"" . htmlentities(stripslashes($data)) . "\">";
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
                                        if ($data == $lst_OptionID)     echo " selected";
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

// Processes and Validates custom field data based on its type.
//
// Returns false if the data is not valid, true otherwise.
//
function validateCustomField($type, &$data, $col_Name, &$aErrors)
{
        global $aLocaleInfo;
        $bErrorFlag = false;

        switch ($type)
        {
                // Validate a date field
                case 2:
                        if (strlen($data) > 0)
                        {
                                list($iYear, $iMonth, $iDay) = sscanf($data,"%04d-%02d-%02d");
                                if ( !checkdate($iMonth,$iDay,$iYear) )
                                {
                                        $aErrors[$col_Name] = gettext("Not a valid date");
                                        $bErrorFlag = true;
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
                                $data = eregi_replace($aLocaleInfo["thousands_sep"], "", $data);  // remove any thousands separators
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
                        {
                                $data = eregi_replace($aLocaleInfo["mon_thousands_sep"], "", $data);
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
// $special is currently only used for the phone country
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
function FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, $sSeparator = "-")
{
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
                if (is_numeric($per_BirthYear))
                {
                        $dBirthDate = $per_BirthYear . $sSeparator . $dBirthDate;
                }
        }
        elseif (is_numeric($per_BirthYear))
        {
                $dBirthDate = $per_BirthYear;
        }
        else
        {
                $dBirthDate = "";
        }

        return $dBirthDate;
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

?>
