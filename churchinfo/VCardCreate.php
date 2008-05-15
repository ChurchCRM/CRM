<?php
/*******************************************************************************
 *
 *  filename    : VCardCreate.php
 *  last change : 2003-09-17
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2003 Chris Gebhardt
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
require "PEAR.php";
require "Include/Contact_Vcard_Build.php";

$iPersonID = FilterInput($_GET["PersonID"],'int');


// Fetch and then format all needed data for this person.
$sSQL = "SELECT * FROM person_per
			LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
			WHERE per_ID = " . $iPersonID;
$rsPerson = RunQuery($sSQL);
extract(mysql_fetch_array($rsPerson));

$sBirthDate = FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay,"-",$per_Flags);

// Assign the values locally, after selecting whether to display the family or person information
SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, False);
$sCity = SelectWhichInfo($per_City, $fam_City, False);
$sState = SelectWhichInfo($per_State, $fam_State, False);
$sZip = SelectWhichInfo($per_Zip, $fam_Zip, False);
$sCountry = SelectWhichInfo($per_Country, $fam_Country, False);
$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sCountry,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), False);
$sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$sCountry,$dummy), ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), False);
$sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sCountry,$dummy), ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), False);
$sEmail = SelectWhichInfo($per_Email, $fam_Email, False);

// Instantiate the vCard class and then build the card.
if ($bOldVCardVersion)
	$vcard = new Contact_Vcard_Build(2.1);
else
	$vcard = new Contact_Vcard_Build();
// set a formatted name
$vcard->setFormattedName(iconv("UTF-8","ISO-8859-1",FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0)));

// set the structured name parts
$vcard->setName(iconv("UTF-8","ISO-8859-1",$per_LastName), iconv("UTF-8","ISO-8859-1",$per_FirstName), iconv("UTF-8","ISO-8859-1",$per_MiddleName), iconv("UTF-8","ISO-8859-1",$per_Title), iconv("UTF-8","ISO-8859-1",$per_Suffix));

$vcard->addEmail(iconv("UTF-8","ISO-8859-1",$sEmail));
$vcard->addParam('TYPE', 'HOME');
$vcard->addParam('TYPE', 'PREF');

$vcard->addEmail(iconv("UTF-8","ISO-8859-1",$per_WorkEmail));
$vcard->addParam('TYPE', 'WORK');

if ($bPalmVCard) {
	$vcard->addAddress(';', iconv("UTF-8","ISO-8859-1",$sAddress1), iconv("UTF-8","ISO-8859-1",$sAddress2), iconv("UTF-8","ISO-8859-1",$sCity), iconv("UTF-8","ISO-8859-1",$sState), iconv("UTF-8","ISO-8859-1",$sZip), iconv("UTF-8","ISO-8859-1",$sCountry));
} else {
	$vcard->addAddress('', iconv("UTF-8","ISO-8859-1",$sAddress1), iconv("UTF-8","ISO-8859-1",$sAddress2), iconv("UTF-8","ISO-8859-1",$sCity), iconv("UTF-8","ISO-8859-1",$sState), iconv("UTF-8","ISO-8859-1",$sZip), iconv("UTF-8","ISO-8859-1",$sCountry));
}

$vcard->addParam('TYPE', 'HOME');
$vcard->addParam('TYPE', 'PREF');
	
$vcard->addTelephone(iconv("UTF-8","ISO-8859-1",$sHomePhone));
$vcard->addParam('TYPE', 'HOME');

$vcard->addTelephone(iconv("UTF-8","ISO-8859-1",$sWorkPhone));
$vcard->addParam('TYPE', 'WORK');

$vcard->addTelephone(iconv("UTF-8","ISO-8859-1",$sCellPhone));
$vcard->addParam('TYPE', 'CELL');

$vcard->setBirthDay(iconv("UTF-8","ISO-8859-1",$sBirthDate));

$sVCard = $vcard->fetch();

header("Content-type: text/x-vcard");
header("Content-Disposition: attachment; filename=" . iconv("UTF-8","ISO-8859-1",$per_FirstName) . "_" . iconv("UTF-8","ISO-8859-1",$per_LastName) . ".vcf");

echo $sVCard;

?>
