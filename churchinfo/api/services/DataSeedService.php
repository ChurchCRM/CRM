<?php

class DataSeedService
{

    function getPerson($rs, &$personPointer)
    {
        $user = $rs[$personPointer]->user;
        $personPointer += 1;
        return $user;
    }

    function sanitize($str)
    {
        return str_replace("'", "", $str);
    }

    function insertPerson($user)
    {
        $sSQL = "INSERT INTO person_per
	(per_Title,
	per_FirstName,
	per_MiddleName,
	per_LastName,
	per_Suffix,
	per_Gender,
	per_Address1,
	per_Address2,
	per_City,
	per_State,
	per_Zip,
	per_Country,
	per_HomePhone,
	per_WorkPhone,
	per_CellPhone,
	per_Email,
	per_WorkEmail,
	per_BirthMonth,
	per_BirthDay,
	per_BirthYear,
	per_Envelope,
	per_fam_ID,
	per_fmr_ID,
	per_MembershipDate,
	per_cls_ID,
	per_DateEntered,
	per_EnteredBy,
	per_FriendDate,
	per_Flags )
	VALUES ('" .
            $this->sanitize($user->name->title) . "','" .
            $this->sanitize($user->name->first) . "',NULL,'" .
            $this->sanitize($user->name->last) . "',NULL,'" .
            $this->sanitize($user->gender) . "','" .
            $this->sanitize($user->location->street) . "',NULL,'" .
            $this->sanitize($user->location->city) . "','" .
            $this->sanitize($user->location->state) . "','" .
            $this->sanitize($user->location->zip) . "','USA','" .
            $this->sanitize($user->phone) . "',NULL,'" .
            $this->sanitize($user->cell) . "','" .
            $this->sanitize($user->email) . "',NULL," .
            date('m', $user->dob) . "," .
            date('d', $user->dob) . "," .
            date('Y', $user->dob) . ",NULL,'" .
            $this->sanitize($user->famID) . "'," .
            $this->sanitize($user->per_fmr_id) . "," . "\"" .
            date('Y-m-d', $user->registered) .
            "\"" . ",1,'" .
            date("YmdHis") .
            "'," .
            $this->sanitize($_SESSION['iUserID']) . ",";

        if (strlen($dFriendDate) > 0)
            $sSQL .= "\"" . $dFriendDate . "\"";
        else
            $sSQL .= "NULL";
        $sSQL .= ", 0";
        $sSQL .= ")";
        $bGetKeyBack = True;
        RunQuery($sSQL);
        // If this is a new person, get the key back and insert a blank row into the person_custom table
        if ($bGetKeyBack) {
            $sSQL = "SELECT MAX(per_ID) AS iPersonID FROM person_per";
            $rsPersonID = RunQuery($sSQL);
            extract(mysql_fetch_array($rsPersonID));
            $sSQL = "INSERT INTO `person_custom` (`per_ID`) VALUES ('" . $iPersonID . "')";
            RunQuery($sSQL);
        }

    }

    function insertFamily($user)
    {
        $dWeddingDate = "NULL";
        $iCanvasser = 0;
        $nLatitude = 0;
        $nLongitude = 0;
        $nEnvelope = 0;
        $sSQL = "INSERT INTO family_fam (
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
						fam_WeddingDate,
						fam_DateEntered,
						fam_EnteredBy,
						fam_SendNewsLetter,
						fam_OkToCanvass,
						fam_Canvasser,
						fam_Latitude,
						fam_Longitude,
						fam_Envelope)
					VALUES ('" .
            $user->name->last . "','" .
            $user->location->street . "','" .
            $sAddress2 . "','" .
            $user->location->city . "','" .
            $user->location->state . "','" .
            $user->location->zip . "','" .
            $sCountry . "','" .
            $sHomePhone . "','" .
            $sWorkPhone . "','" .
            $sCellPhone . "','" .
            $sEmail . "'," .
            $dWeddingDate . ",'" .
            date("YmdHis") . "'," .
            $_SESSION['iUserID'] . "," .
            "FALSE," .
            "FALSE,'" .
            $iCanvasser . "'," .
            $nLatitude . "," .
            $nLongitude . "," .
            $nEnvelope . ")";
        RunQuery($sSQL);
        $sSQL = "SELECT MAX(fam_ID) AS iFamilyID FROM family_fam";

        $rsLastEntry = RunQuery($sSQL);
        extract(mysql_fetch_array($rsLastEntry));
        return $iFamilyID;

    }

    function generateFamilies($families)
    {
        $kidsPerFamily = 3;
        $kidsdev = 3;
        $personPointer = 1;
        $count = $families * ($kidsPerFamily + $kidsdev + 2);
        $response = file_get_contents("http://api.randomuser.me/?nat=US&results=" . $count);
        $data = json_decode($response);
        $rs = $data->results;
        $rTotalHoh = 0;
        $rTotalSpouse = 0;
        $rTotalChildren = 0;

        for ($i = 0; $i < $families; $i++) {
			
            $hoh = $this->getPerson($rs, $personPointer);
			
            $FamilyID = $this->insertFamily($hoh);
            $familyName = $hoh->name->last;
            $hoh->famID = $FamilyID;
            $hoh->per_fmr_id = 1;

            $spouse = $this->getPerson($rs, $personPointer);
            $spouse->name->last = $familyName;
            $spouse->famID = $FamilyID;
            $spouse->per_fmr_id = 2;
			
            $this->insertPerson($hoh);
            $rTotalHoh += 1;
            $this->insertPerson($spouse);
            $rTotalSpouse += 1;

            #$thisFamChildren = stats_rand_gen_normal ($kidsPerFamily, $stddev);
            $thisFamChildren = rand($kidsPerFamily - $kidsdev, $kidsPerFamily + $kidsdev);

            for ($y = 0; $y < $thisFamChildren; $y++) {
                $child = $this->getPerson($rs, $personPointer);
                $child->name->last = $familyName;
                $child->famID = $FamilyID;
                $child->per_fmr_id = 3;
                $this->insertPerson($child);
                $rTotalChildren += 1;
            }

        }
        echo '{"families created": ' . $families . ',"heads of household created": ' . $rTotalHoh . ', "spouses created":' . $rTotalSpouse . ', "children created":' . $rTotalChildren . ',"random.me response":' . $response . '}';

    }

    function generateSundaySchoolClasses($classes, $childrenPerTeacher)
    {

        echo '{"status":"Sunday School Seed Data Not Implemented"}';

    }

    function generateEvents($events, $averageAttendance)
    {

        echo '{"status":"Events Seed Data Not Implemented"}';

    }

    function generateDeposits($deposits, $averagedepositvalue)
    {
        echo '{"status":"Deposits Seed Data Not Implemented"}';
    }

    function generateFundRaisers($fundraisers, $averageItems, $averageItemPrice)
    {
        echo '{"status":"Fundraisers Seed Data Not Implemented"}';
    }

}
