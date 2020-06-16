<?php
/*******************************************************************************
*
*  filename    : ConvertIndividualToFamily.php
*  website     : http://www.churchcrm.io
*  description : utility to convert individuals to families
*
*  Must be run manually by an administrator.  Type this URL.
*    http://www.mydomain.com/churchcrm/ConvertIndividualToFamily.php
*
*  By default this script does one at a time.  To do all entries
*  at once use this URL
*    http://www.mydomain.com/churchcrm/ConvertIndividualToFamily.php?all=true
*
*  Your URL may vary.  Replace "churchcrm" with $sRootPath
*
*  Contributors:
*  2007 Ed Davis

******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Authentication\AuthenticationManager;

// Security
if (!AuthenticationManager::GetCurrentUser()->isAdmin()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

if ($_GET['all'] == 'true') {
    $bDoAll = true;
}

//Set the page title
$sPageTitle = gettext('Convert Individuals to Families');

require 'Include/Header.php';

$curUserId = AuthenticationManager::GetCurrentUser()->getId();

// find the family ID so we can associate to person record
$sSQL = 'SELECT MAX(fam_ID) AS iFamilyID FROM family_fam';
$rsLastEntry = RunQuery($sSQL);
extract(mysqli_fetch_array($rsLastEntry));

// Get list of people that are not assigned to a family
$sSQL = "SELECT * FROM person_per WHERE per_fam_ID='0' ORDER BY per_LastName, per_FirstName";
$rsList = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($rsList)) {
    extract($aRow);

    echo '<br><br><br>';
    echo '*****************************************';

    $per_LastName = mysqli_real_escape_string($cnInfoCentral, $per_LastName);
    $per_Address1 = mysqli_real_escape_string($cnInfoCentral, $per_Address1);
    $per_Address2 = mysqli_real_escape_string($cnInfoCentral, $per_Address2);
    $per_City = mysqli_real_escape_string($cnInfoCentral, $per_City);
    $per_State = mysqli_real_escape_string($cnInfoCentral, $per_State);
    $per_Zip = mysqli_real_escape_string($cnInfoCentral, $per_Zip);
    $per_Country = mysqli_real_escape_string($cnInfoCentral, $per_Country);
    $per_HomePhone = mysqli_real_escape_string($cnInfoCentral, $per_HomePhone);

    $sSQL = "INSERT INTO family_fam (
                fam_Name,
                fam_Address1,
                fam_Address2,
                fam_City,
                fam_State,
                fam_Zip,
                fam_Country,
                fam_HomePhone,
                fam_DateEntered,
                fam_EnteredBy)
            VALUES ('".
                $per_LastName."','".
                $per_Address1."','".
                $per_Address2."','".
                $per_City."','".
                $per_State."','".
                $per_Zip."','".
                $per_Country."','".
                $per_HomePhone."',".
                'NOW()'.",'".
                $curUserId."')";

    echo '<br>'.$sSQL;
    // RunQuery to add family record
    RunQuery($sSQL);
    $iFamilyID++; // increment family ID

    //Get the key back
    $sSQL = 'SELECT MAX(fam_ID) AS iNewFamilyID FROM family_fam';
    $rsLastEntry = RunQuery($sSQL);
    extract(mysqli_fetch_array($rsLastEntry));

    if ($iNewFamilyID != $iFamilyID) {
        echo '<br><br>Error with family ID';

        break;
    }

    echo '<br><br>';

    // Now update person record
    $sSQL = 'UPDATE person_per '.
            "SET per_fam_ID='$iFamilyID',".
            '    per_Address1=NULL,'.
            '    per_Address2=NULL,'.
            '    per_City=NULL,'.
            '    per_State=NULL,'.
            '    per_Zip=NULL,'.
            '    per_Country=NULL,'.
            '    per_HomePhone=NULL,'.
            '    per_DateLastEdited=NOW(),'.
            "    per_EditedBy='$curUserId' ".
            "WHERE per_ID='$per_ID'";

    echo '<br>'.$sSQL;
    RunQuery($sSQL);

    echo '<br><br><br>';
    echo "$per_FirstName $per_LastName (per_ID = $per_ID) is now part of the ";
    echo "$per_LastName Family (fam_ID = $iFamilyID)<br>";
    echo '*****************************************';

    if (!$bDoAll) {
        break;
    }
}
echo '<br><br>';

echo '<a href="ConvertIndividualToFamily.php">'.gettext('Convert Next').'</a><br><br>';
echo '<a href="ConvertIndividualToFamily.php?all=true">'.gettext('Convert All').'</a><br>';

require 'Include/Footer.php';
