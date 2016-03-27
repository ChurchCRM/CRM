<?php
/*******************************************************************************
 *
 *  filename    : CSVImport.php
 *  last change : 2003-10-02
 *  description : Tool for importing CSV person data into InfoCentral
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Chris Gebhardt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
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

/**
  Class to store family data so we can assign roles once we have all members.
  A monogamous society is assumed, however  it can be patriarchal or matriarchal
**/
class Family
{
    var $Members;       // array for member data
    var $MemberCount;   // obious
    var $WeddingDate;   // one per family
    var $Phone;         // one per family
    var $Envelope;      // one per family
    var $_nAdultMale;   // if one adult male
    var $_nAdultFemale; // and 1 adult female we assume spouses
    var $_type;         // 0=patriarch, 1=martriarch

    // constructor, initialize variables
    function Family($famtype)
    {
        $this->_type = $famtype;
        $this->MemberCount = 0;
        $this->Envelope = 0;
        $this->_nAdultMale = 0;
        $this->_nAdultFemale = 0;
        $this->Members = array();
        $this->WeddingDate = "0000-00-00";
        $this->Phone = "";
    }

    /** Add what we need to know about members for role assignment later **/
    function AddMember($PersonID, $Gender, $Age, $Wedding, $Phone, $Envelope)
    {
        // add member with un-assigned role
        $this->Members[] = array('personid'=>$PersonID,
                                 'age'=>$Age,
                                 'gender'=>$Gender,
                                 'role'=> 0,
                                 'phone'=> $Phone,
                                 'envelope'=>$Envelope);
        if($Wedding != "0000-00-00")
            $this->WeddingDate = $Wedding;
        if($Envelope != 0)
            $this->Envelope = $Envelope;
        $this->MemberCount++;
        if($Age > 18)
        {
            $Gender==1 ? $this->_nAdultMale++ : $this->_nAdultFemale++ ;
        }
    }

    /** Assigning of roles to be called after all members added **/
    function AssignRoles()
    {
        // only one meber, must be "head"
        if($this->MemberCount == 1)
        {
            $this->Members[0]['role'] = 1;
            $this->Phone = $this->Members[0]['phone'];
        }
        else
        {
            for($m=0;$m<$this->MemberCount;$m++)
            {
                if($this->Members[$m]['age'] >= 0) // -1 if unknown age
                {
                    // child
                    if($this->Members[$m]['age'] <= 18)
                    {
                        $this->Members[$m]['role'] = 3;
                    }
                    else
                    {
                        // if one adult male and 1 adult female we assume spouses
                        if($this->_nAdultMale == 1 && $this->_nAdultFemale == 1)
                        {
                            // find head / spouse
                            if(($this->Members[$m]['gender'] == 1 && $this->_type == 0) || ($this->Members[$m]['gender'] == 2 && $this->_type == 1))
                            {
                                $this->Members[$m]['role'] = 1;
                                if($this->Members[$m]['phone'] != "") $this->Phone = $this->Members[$m]['phone'];
                            }
                            else
                            {
                                $this->Members[$m]['role'] = 2;
                            }
                        }
                    }
                }
            }
        }
    }
}

// Set the page title and include HTML header
$sPageTitle = "CSV Import";
require "Include/Header.php"; ?>

<div class="box">
<div class="box-header">
<h3 class="box-title">Import Data</h3>
</div>
<div class="box-body">

<?php

$iStage = 1;
$csvError = "";

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
        $csvTempFile = "import.csv";
        $system_temp = ini_get("session.save_path");
        if (strlen ($system_temp)>0)
            $csvTempFile = $system_temp . "/" . $csvTempFile;
        move_uploaded_file($_FILES['CSVfile']['tmp_name'], $csvTempFile);

        // create the file pointer
        $pFile = fopen ($csvTempFile, "r");

        // count # lines in the file
        $iNumRows = 0;
        while ($tmp = fgets($pFile,2048)) $iNumRows++;
        rewind($pFile);

        // create the form
        ?>
        <form method="post" action="CSVImport.php">

        <?php
        echo gettext("Total number of rows in the CSV file:") . $iNumRows;
        echo "<br><br>";
        echo "<table class=\"table horizontal-scroll\" id=\"importTable\">";

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

        $sPerCustomFieldList = "";
        while ($aRow = mysql_fetch_array($rsCustomFields))
        {
            extract($aRow);
            // No easy way to import person-from-group or custom-list types
            if ($type_ID != 9 && $type_ID != 12)
            {
                 $sPerCustomFieldList .= "<option value=\"" . $custom_Field . "\">" . $custom_Name . "</option>\n";
            }
        }

        $sSQL = "SELECT * FROM family_custom_master ORDER BY fam_custom_Order";
        $rsfamCustomFields = RunQuery($sSQL);

        $sFamCustomFieldList = "";
        while ($aRow = mysql_fetch_array($rsfamCustomFields))
        {
            extract($aRow);
            if ($type_ID != 9 && $type_ID != 12)
            {
                $sFamCustomFieldList .= "<option value=\"f" . $fam_custom_Field . "\">" . $fam_custom_Name . "</option>\n";
            }
        }

        // Get Field Security List Matrix
        $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence";
        $rsSecurityGrp = RunQuery($sSQL);

        while ($aRow = mysql_fetch_array($rsSecurityGrp))
        {
            extract ($aRow);
            $aSecurityType[$lst_OptionID] = $lst_OptionName;
        }


        // add select boxes for import destination mapping
        for ($col = 0; $col < $numCol; $col++)
        {
        ?>
            <td>
            <select name="<?= "col" . $col ?>" class="columns">
                <option value="0"><?= gettext("Ignore this Field") ?></option>
                <option value="1"><?= gettext("Title") ?></option>
                <option value="2"><?= gettext("First Name") ?></option>
                <option value="3"><?= gettext("Middle Name") ?></option>
                <option value="4"><?= gettext("Last Name") ?></option>
                <option value="5"><?= gettext("Suffix") ?></option>
                <option value="6"><?= gettext("Gender") ?></option>
                <option value="7"><?= gettext("Donation Envelope") ?></option>
                <option value="8"><?= gettext("Address1") ?></option>
                <option value="9"><?= gettext("Address2") ?></option>
                <option value="10"><?= gettext("City") ?></option>
                <option value="11"><?= gettext("State") ?></option>
                <option value="12"><?= gettext("Zip") ?></option>
                <option value="13"><?= gettext("Country") ?></option>
                <option value="14"><?= gettext("Home Phone") ?></option>
                <option value="15"><?= gettext("Work Phone") ?></option>
                <option value="16"><?= gettext("Mobile Phone") ?></option>
                <option value="17"><?= gettext("Email") ?></option>
                <option value="18"><?= gettext("Work / Other Email") ?></option>
                <option value="19"><?= gettext("Birth Date") ?></option>
                <option value="20"><?= gettext("Membership Date") ?></option>
                <option value="21"><?= gettext("Wedding Date") ?></option>
                <?= $sPerCustomFieldList.$sFamCustomFieldList ?>
            </select>
            </td>
        <?php
        }

        echo "</table>";
        ?>
        <BR>
        <input type="checkbox" value="1" name="IgnoreFirstRow"><?= gettext("Ignore first CSV row (to exclude a header)") ?>
        <BR><BR>
        <BR>
        <input type="checkbox" value="1" name="MakeFamilyRecords" checked="true">
        <select name="MakeFamilyRecordsMode">
            <option value="0"><?= gettext("Make Family records based on last name and address") ?></option>
            <?= $sPerCustomFieldList.$sFamCustomFieldList ?>
        </select>

        <BR><BR>
        <select name="FamilyMode">
            <option value="0"><?= gettext("Patriarch") ?></option>
            <option value="1"><?= gettext("Matriarch") ?></option>
        </select>
        <?= gettext("Family Type: used with Make Family records... option above") ?>
        <BR><BR>
        <select name="DateMode">
            <option value="1">YYYY-MM-DD</option>
            <option value="2">MM-DD-YYYY</option>
            <option value="3">DD-MM-YYYY</option>
        </select>
        <?= gettext("NOTE: Separators (dashes, etc.) or lack thereof do not matter") ?>
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
            <option value="0"><?= gettext("Unassigned") ?></option>
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
        <?= gettext("Classification") ?>
        <BR><BR>
        <input type="submit" class="btn btn-primary" value="<?= gettext("Perform Import") ?>" name="DoImport">
        </form>

        <?php
        $iStage = 2;
    }
}


// Has the import form been submitted yet?
if (isset($_POST["DoImport"]))
{
    $aColumnCustom = array();
    $aFamColumnCustom = array();
    $bHasCustom = false;
    $bHasFamCustom = false;

    $csvTempFile = "import.csv";
    $system_temp = ini_get("session.save_path");
    if (strlen ($system_temp)>0)
        $csvTempFile = $system_temp . "/" . $csvTempFile;

    $Families = array();

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
                $aFamColumnCustom[$col] = 0;
                $bHasCustom = true;
            }
            else
            {
                $aColumnCustom[$col] = 0;
                if (substr($_POST["col" . $col],0,2) == "fc")
                {
                    $aFamColumnCustom[$col] = 1;
                    $bHasFamCustom = true;
                }
                else
                {
                    $aFamColumnCustom[$col] = 0;
                }
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

            $sSQL = "SELECT * FROM family_custom_master";
            $rsfamCustomFields = RunQuery($sSQL);

            while ($aRow = mysql_fetch_array($rsfamCustomFields))
            {
                extract($aRow);
                $afamCustomTypes[$fam_custom_Field] = $type_ID;
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
                19=>"per_BirthYear, per_BirthMonth, per_BirthDay", 20=>"per_MembershipDate",
                21=>"fam_WeddingDate"
        );

        $importCount = 0;

        while ($aData = fgetcsv($pFile, 2048, ","))
        {
            $iBirthYear = 0; $iBirthMonth = 0; $iBirthDay = 0; $iGender = 0; $dWedding = "0000-00-00";
            $sAddress1 = ""; $sAddress2 = ""; $sCity = ""; $sState = ""; $sZip = "";
            // Use the default country from the mapping form in case we don't find one otherwise
            $sCountry = $sDefaultCountry;
            $iEnvelope = 0;

            $sSQLpersonFields = "INSERT INTO person_per (";
            $sSQLpersonData = " VALUES (";
            $sSQLcustom = "UPDATE person_custom SET ";

            // Build the person_per SQL first.
            // We do this in case we can get a country, which will allow phone number parsing later
            for ($col = 0; $col < $numCol; $col++)
            {
                // Is it not a custom field?
                if (!$aColumnCustom[$col] && !$aFamColumnCustom[$col])
                {
                    $currentType = $aColumnID[$col];

                    // handler for each of the 20 person_per table column possibilities
                    switch($currentType)
                    {
                        // Address goes with family record if creating families
                        case 8: case 9: case 10: case 11: case 12:
                            // if not making family records, add to person
                            if (!isset($_POST["MakeFamilyRecords"]))
                            {
                                $sSQLpersonData .= "'" . addslashes($aData[$col]) . "',";
                            }
                            else
                            {
                                switch($currentType)
                                {
                                    case 8:
                                        $sAddress1 = addslashes($aData[$col]);
                                        break;
                                    case 9:
                                        $sAddress2 = addslashes($aData[$col]);
                                        break;
                                    case 10:
                                        $sCity = addslashes($aData[$col]);
                                        break;
                                    case 11:
                                        $sState = addslashes($aData[$col]);
                                        break;
                                    case 12:
                                        $sZip = addslashes($aData[$col]);
                                }
                            }
                            break;

                        // Simple strings.. no special processing
                        case 1: case 2: case 3: case 4: case 5:
                        case 17: case 18:
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
                                      $iGender = 1;
                                    break;
                                case 'female': case 'f': case 'girl': case 'woman':
                                    $sSQLpersonData .= "2, ";
                                      $iGender = 2;
                                    break;
                                default:
                                    $sSQLpersonData .= "0, ";
                                    break;
                            }
                            break;

                        // Donation envelope.. make sure it's available!
                        case 7:
                            $iEnv = FilterInput($aData[$col],'int');
                            if($iEnv == "")
                            {
                                $iEnvelope = 0;
                            }
                            else
                            {
                                $sSQL = "SELECT '' FROM person_per WHERE per_Envelope = " . $iEnv;
                                $rsTemp = RunQuery($sSQL);
                                if (mysql_num_rows($rsTemp) == 0)
                                    $iEnvelope = $iEnv;
                                else
                                    $iEnvelope = 0;
                            }
                            break;

                        // Birth date.. parse multiple date standards.. then split into day,month,year
                        case 19:
                            $sDate = $aData[$col];
                            $aDate = ParseDate($sDate,$iDateMode);
                            $sSQLpersonData .= $aDate[0] . "," . $aDate[1] . "," . $aDate[2] . ",";
                            // Save these for role calculation
                            $iBirthYear = $aDate[0];
                            $iBirthMonth = $aDate[1];
                            $iBirthDay = $aDate[2];
                            break;

                        // Membership date.. parse multiple date standards
                        case 20:
                            $sDate = $aData[$col];
                            $aDate = ParseDate($sDate,$iDateMode);
                            if ($aDate[0] == 'NULL' || $aDate[1] == 'NULL' || $aDate[2] == 'NULL'){
                                $sSQLpersonData .= "NULL,";
                            } else {
                                $sSQLpersonData .= "\"" . $aDate[0] . "-" . $aDate[1] . "-" . $aDate[2] . "\",";
                            }
                            break;

                        // Wedding date.. parse multiple date standards
                        case 21:
                            $sDate = $aData[$col];
                            $aDate = ParseDate($sDate,$iDateMode);
                            if ($aDate[0] == 'NULL' || $aDate[1] == 'NULL' || $aDate[2] == 'NULL'){
                                $dWedding = "NULL";
                            } else {
                                $dWedding = $aDate[0] . "-" . $aDate[1] . "-" . $aDate[2];
                            }
                            break;

                        // Ignore field option
                        case 0:

                        // Phone numbers.. uh oh.. don't know country yet.. wait to do a second pass!
                        case 14: case 15: case 16:
                        default:
                            break;

                    }

                    switch($currentType)
                    {
                        case 0: case 7: case 13: case 14: case 15: case 16: case 21:
                            break;
                        case 8: case 9: case 10: case 11: case 12:
                            // if not making family records, add to person
                            if (!isset($_POST["MakeFamilyRecords"]))
                                $sSQLpersonFields .= $aPersonTableFields[$currentType] . ", ";
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
                if (!$aColumnCustom[$col] && !$aFamColumnCustom[$col])
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
            $sSQLpersonData .= "'" . date("YmdHis") . "'," . $_SESSION['iUserID'];
            $sSQLpersonData .= ")";

            $sSQLpersonFields .= "per_cls_ID, per_Country, per_DateEntered, per_EnteredBy";
            $sSQLpersonFields .= ")";
            $sSQLperson = $sSQLpersonFields . $sSQLpersonData;

            RunQuery($sSQLperson);

            // Make a one-person family if requested
            if (isset($_POST["MakeFamilyRecords"])) {
                $sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
                $rsPersonID = RunQuery($sSQL);
                extract(mysql_fetch_array($rsPersonID));
                $sSQL = "SELECT * FROM person_per WHERE per_ID = " . $iPersonID;
                $rsNewPerson = RunQuery($sSQL);
                extract(mysql_fetch_array($rsNewPerson));

                // see if there is a family...
                if (!isset($_POST["MakeFamilyRecordsMode"]) || $_POST["MakeFamilyRecordsMode"] == "0")
                {
                    // ...with same last name and address
                    $sSQL = "SELECT fam_ID
                             FROM family_fam where fam_Name = '".addslashes($per_LastName)."'
                             AND fam_Address1 = '".$sAddress1."'"; // slashes added already
                } else {
                    // ...with the same custom field values
                    $field = $_POST["MakeFamilyRecordsMode"];
                    $field_value = '';
                    for ($col = 0; $col < $numCol; $col++)
                    {
                        if ($aFamColumnCustom[$col] && $field == $aColumnID[$col])
                        {
                            $field_value = trim($aData[$col]);
                            break;
                        }
                    }
                    $sSQL = "SELECT f.fam_ID FROM family_fam f, family_custom c
                             WHERE f.fam_ID = c.fam_ID AND c.".addslashes(substr($field,1))." = '".addslashes($field_value)."'";
                }
                $rsExistingFamily = RunQuery($sSQL);
                $famid = 0;
                if(mysql_num_rows($rsExistingFamily) > 0)
                {
                    extract(mysql_fetch_array($rsExistingFamily));
                    $famid = $fam_ID;
                    if(array_key_exists($famid, $Families))
                        $Families[$famid]->AddMember($per_ID,
                                                     $iGender,
                                                     GetAge($iBirthMonth, $iBirthDay, $iBirthYear),
                                                     $dWedding,
                                                     $per_HomePhone,
                                                     $iEnvelope);
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
                                     "\"" . $sAddress1 . "\", " .
                                     "\"" . $sAddress2 . "\", " .
                                     "\"" . $sCity . "\", " .
                                     "\"" . $sState . "\", " .
                                     "\"" . $sZip . "\", " .
                                     "\"" . $per_Country . "\", " .
                                     "\"" . $per_HomePhone . "\", " .
                                     "\"" . $per_WorkPhone . "\", " .
                                     "\"" . $per_CellPhone . "\", " .
                                     "\"" . $per_Email . "\"," .
                                     "\"" . date("YmdHis") . "\"," .
                                     "\"" . $_SESSION['iUserID'] . "\");";
                    RunQuery($sSQL);
                    $sSQL = "SELECT LAST_INSERT_ID()";
                    $rsFid = RunQuery($sSQL);
                    $aFid = mysql_fetch_array($rsFid);
                    $famid =  $aFid[0];

                    $sSQL = "INSERT INTO `family_custom` (`fam_ID`) VALUES ('" . $famid . "')";
                    RunQuery($sSQL);

                    $fFamily = new Family(FilterInput($_POST["FamilyMode"],'int'));
                    $fFamily->AddMember($per_ID,
                                        $iGender,
                                        GetAge($iBirthMonth, $iBirthDay, $iBirthYear),
                                        $dWedding,
                                        $per_HomePhone,
                                        $iEnvelope);
                    $Families[$famid] = $fFamily;
                }
                $sSQL = "UPDATE person_per SET per_fam_ID = " . $famid . " WHERE per_ID = " . $per_ID;
                RunQuery($sSQL);

                if ($bHasFamCustom)
                {
                    // Check if family_custom record exists
                    $sSQL = "SELECT fam_id FROM family_custom WHERE fam_id = $famid";
                    $rsFamCustomID = RunQuery($sSQL);
                    if (mysql_num_rows($rsFamCustomID) == 0)
                    {
                        $sSQL = "INSERT INTO `family_custom` (`fam_ID`) VALUES ('" . $famid . "')";
                        RunQuery($sSQL);
                    }

                    // Build the family_custom SQL
                    $sSQLFamCustom = "UPDATE family_custom SET ";
                    for ($col = 0; $col < $numCol; $col++)
                    {
                        // Is it a custom field?
                        if ($aFamColumnCustom[$col])
                        {
                            $colID = substr($aColumnID[$col],1);
                            $currentType = $afamCustomTypes[$colID];
                            $currentFieldData = trim($aData[$col]);

                            // If date, first parse it to the standard format..
                            if ($currentType == 2)
                            {
                                $aDate = ParseDate($currentFieldData,$iDateMode);
                                if ($aDate[0] == 'NULL' || $aDate[1] == 'NULL' || $aDate[2] == 'NULL'){
                                    $currentFieldData = "";
                                } else {
                                    $currentFieldData = implode("-",$aDate);
                                }
                            }
                            // If boolean, convert to the expected values for custom field
                            elseif ($currentType == 1)
                            {
                                if (strlen($currentFieldData))
                                    $currentFieldData = ConvertToBoolean($currentFieldData);
                            }
                            else
                                $currentFieldData = addslashes($currentFieldData);

                            // aColumnID is the custom table column name
                            sqlCustomField($sSQLFamCustom, $currentType, $currentFieldData, $colID, $sCountry);
                        }
                    }

                    // Finalize and run the update for the person_custom table.
                    $sSQLFamCustom = substr($sSQLFamCustom,0,-2);
                    $sSQLFamCustom .= " WHERE fam_ID = " . $famid;
                    RunQuery($sSQLFamCustom);
                }
            }

            if ($bHasCustom)
            {
                // Get the last inserted person ID and insert a dummy row in the person_custom table
                $sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
                $rsPersonID = RunQuery($sSQL);
                extract(mysql_fetch_array($rsPersonID));
                $sSQL = "INSERT INTO `person_custom` (`per_ID`) VALUES ('" . $iPersonID . "')";
                RunQuery($sSQL);

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
                            if ($aDate[0] == 'NULL' || $aDate[1] == 'NULL' || $aDate[2] == 'NULL'){
                                $currentFieldData = "";
                            } else {
                                $currentFieldData = implode("-",$aDate);
                            }
                        }
                        // If boolean, convert to the expected values for custom field
                        elseif ($currentType == 1)
                        {
                            if (strlen($currentFieldData))
                            {
                            $currentFieldData = ConvertToBoolean($currentFieldData);
                            }
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
            }

            $importCount++;
        }

        fclose($pFile);

        // delete the temp file
        unlink($csvTempFile);

        // role assignments from config
        $aDirRoleHead = explode(",",$sDirRoleHead);
        $aDirRoleSpouse = explode(",",$sDirRoleSpouse);
        $aDirRoleChild = explode(",",$sDirRoleChild);

        // update roles now that we have complete family data.
        foreach($Families as $fid=>$family)
        {
            $family->AssignRoles();
            foreach($family->Members as $member)
            {
                switch($member['role'])
                {
                    case 1:
                        $iRole = $aDirRoleHead[0];
                        break;
                    case 2:
                        $iRole = $aDirRoleSpouse[0];
                        break;
                    case 3:
                        $iRole = $aDirRoleChild[0];
                        break;
                    default:
                        $iRole = 0;
                }
                $sSQL = "UPDATE person_per SET per_fmr_ID = " . $iRole . " WHERE per_ID = " . $member['personid'];
                RunQuery($sSQL);
            }

            $sSQL = "UPDATE family_fam SET fam_WeddingDate = " . "'" . $family->WeddingDate. "'";

            if($family->Phone != "")
                $sSQL.= ", fam_HomePhone =" . "'" . $family->Phone . "'";

            if($family->Envelope != 0)
                $sSQL.= ", fam_Envelope  = " . $family->Envelope;

            $sSQL.=  " WHERE fam_ID = " . $fid;
            RunQuery($sSQL);
        }

        $iStage = 3;
    }
    else
        echo gettext("ERROR: the uploaded CSV file no longer exists!");
}

// clear person and families if not happy with previous import.
$sClear = "";
if(isset($_POST["Clear"]))
{
    if(isset($_POST["chkClear"]))
    {
        $sSQL = "TRUNCATE `family_fam`;";
        RunQuery($sSQL);
        $sSQL = "TRUNCATE `person_per`;";
        RunQuery($sSQL);
        $sSQL = "TRUNCATE `person_custom`;";
        RunQuery($sSQL);
        $sSQL = "TRUNCATE `family_custom`;";
        RunQuery($sSQL);
        $sSQL = "INSERT INTO person_per VALUES (1,NULL,'ChurchInfo',NULL,'Admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,0000,NULL,0,0,0,0,NULL,NULL,'2004-08-25 18:00:00',0,0,NULL,0);";
        RunQuery($sSQL);
        $sClear = gettext("Data Cleared Successfully!");
    }
    else
    {
        $sClear = gettext("Please select the confirmation checkbox");
    }
}

if ($iStage == 1)
{
    // Display the select file form
    ?>
        <p style="color: red"> <?= $csvError ?></p>
        <form method="post" action="CSVImport.php" enctype="multipart/form-data">
        <input class="icTinyButton" type="file" name="CSVfile"><br/>
        <input type="submit" class="btn" value=" <?= gettext("Upload CSV File") ?> "
        name="UploadCSV">
        </form>
        </div>
        </div>
        <div class="box">
        <div class="box-header">
        <h3 class="box-title">Clear Data</h3>
        </div>
        <div class="box-body">
        <form method="post" action="CSVImport.php" enctype="multipart/form-data">
        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#clearPersons"><?= gettext("Clear Persons and Families") ?></button>
        <!-- Modal -->
        <div class="modal fade" id="clearPersons" tabindex="-1" role="dialog" aria-labelledby="clearPersons" aria-hidden="true">
            <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="upload-Image-label"><?= gettext("Clear Persons and Families") ?></h4>
                        </div>
                        <div class="modal-body">
                        <span style="color: red">
                            <?php
                            echo gettext("Warning!  Do not select this option if you plan to add to an existing database.<br/>");
                            echo gettext("Use only if unsatisfied with initial import.  All person and member data will be destroyed!");
                            ?><br><br>
                            <span style="color:black">I Understand &nbsp;<input type="checkbox" name="chkClear"></span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            <button name="Clear" type="submit" class="btn btn-danger"><?= gettext("Clear Persons and Families") ?></button>
                        </div>
                    </div>
            </div>
        </div>
    </p></form>
    <?php
    echo $sClear;
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
    if( (0 + $aDate[0]) < 1901 || (0 + $aDate[0]) > 2155 ) {
        $aDate[0] = "NULL";
    }
    if( (0 + $aDate[1]) < 0  || (0 + $aDate[1]) > 12 ) {
        $aDate[1] = "NULL";
    }
    if( (0 + $aDate[2]) < 0  || (0 + $aDate[2]) > 31 ) {
        $aDate[2] = "NULL";
    }
    return $aDate;
}

function GetAge($Month,$Day,$Year)
{
    if ($Year > 0)
    {
        if ($Year == date("Y"))
        {
            return (0);
        }
        elseif ($Year == date("Y")-1)
        {
            $monthCount =  12 - $Month + date("m");
            if ($Day > date("d"))
                $monthCount--;
            if ($monthCount >= 12)
                return (1);
            else
                return (0);
        }
        elseif ( $Month > date("m") || ($Month == date("m") && $Day > date("d")) )
            return ( date("Y")-1 - $Year);
        else
            return ( date("Y") - $Year);
    }
    else
        return (-1);
}
?>
</div>
</div>

<script>
$(".columns").select2();
</script>

<?php
require "Include/Footer.php";
?>
