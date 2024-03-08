<?php

/*******************************************************************************
*
*  filename    : ConvertIndividualToFamily.php
*  website     : https://churchcrm.io
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

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\RedirectUtils;

// Security
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isAdmin());

if ($_GET['all'] == 'true') {
    $bDoAll = true;
}

//Set the page title
$sPageTitle = gettext('Convert Individuals to Families');

require 'Include/Header.php';

$curUserId = AuthenticationManager::getCurrentUser()->getId();

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

    $family = new Family();
    $family
        ->setName($per_LastName)
        ->setAddress1($per_Address1)
        ->setAddress2($per_Address2)
        ->setCity($per_City)
        ->setState($per_State)
        ->setZip($per_Zip)
        ->setCountry($per_Country)
        ->setHomePhone($per_HomePhone)
        ->setDateEntered(new DateTimeImmutable())
        ->setEnteredBy($curUserId);
    $family->save();

    echo '<br>' . $sSQL;
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
    $person = PersonQuery::create()->findOneById($per_ID);
    $person
        ->setFamId($iFamilyID)
        ->setAddress1(null)
        ->setAddress2(null)
        ->setCity(null)
        ->setState(null)
        ->setZip(null)
        ->setCountry(null)
        ->setHomePhone(null)
        ->setDateLastEdited(new \DateTimeImmutable())
        ->setEditedBy($curUserId);
    $person->save();

    echo '<br><br><br>';
    echo "$per_FirstName $per_LastName (per_ID = $per_ID) is now part of the ";
    echo "$per_LastName Family (fam_ID = $iFamilyID)<br>";
    echo '*****************************************';

    if (!$bDoAll) {
        break;
    }
}
echo '<br><br>';

echo '<a href="ConvertIndividualToFamily.php">' . gettext('Convert Next') . '</a><br><br>';
echo '<a href="ConvertIndividualToFamily.php?all=true">' . gettext('Convert All') . '</a><br>';

require 'Include/Footer.php';
