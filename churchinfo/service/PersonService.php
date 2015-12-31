<?php

class PersonService
{

    function photo($id)
    {
        $sSQL = 'SELECT per_ID, per_FirstName, per_LastName, per_Gender, per_Email FROM person_per WHERE per_ID =' . $id;
        $person = RunQuery($sSQL);
        extract(mysql_fetch_array($person));

        if ($per_ID != "") {

            $photoFile = $this->getUploadedPhoto($per_ID);

            if ($photoFile == "" && $per_Email != "") {
                $photoFile = $this->getGravatar($per_Email);
            }

            if ($photoFile == "") {
                $photoFile = $this->getDefaultPhoto();
            }

            echo $photoFile;
        } else {
            echo "{ error: person not found for id ".$id. "}";
        }

    }

    private
    function getUploadedPhoto($personId)
    {
        $validextensions = array("jpeg", "jpg", "png");
        $hasFile = false;
        while (list(, $ext) = each($validextensions)) {
            $photoFile = "../Images/Person/thumbnails/" . $personId . "." . $ext;
            if (file_exists($photoFile)) {
                $hasFile = true;
                $photoFile = "Images/Person/thumbnails/" . $personId . "." . $ext;
                break;
            }
        }

        if ($hasFile) {
            return $photoFile;
        } else {
            return "";
        }
    }

    private
    function getGravatar($email, $s = 60, $d = '404', $r = 'g', $img = false, $atts = array())
    {
        $url = '//www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";

        $headers = @get_headers($url);
        if (strpos($headers[0], '404') === false) {
            return $url;
        } else {
            return "";
        }
    }
    
    function getViewURI($Id)
    {
        return $sURLPath."/PersonView.php?PersonID=".$Id;
    }

    function search($searchTerm)
    {
        $fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM person_per WHERE per_FirstName LIKE \'%' . $searchTerm . '%\' OR per_LastName LIKE \'%' . $searchTerm . '%\' OR per_Email LIKE \'%' . $searchTerm . '%\' OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%' . $searchTerm . '%\' order by per_FirstName LIMIT 15';
        $result = mysql_query($fetch);

        $return = array();
        while ($row = mysql_fetch_array($result)) {
            $values['id'] = $row['per_ID'];
            $values['familyID'] = $row['per_fam_ID'];
            $values['firstName'] = $row['per_FirstName'];
            $values['lastName'] = $row['per_LastName'];
            $values['displayName'] = $row['per_FirstName'] . " " . $row['per_LastName'];
            $values['uri'] = $this->getViewURI($row['per_ID']);

            array_push($return, $values);
        }

        return '{"persons": ' . json_encode($return) . '}';
    }

    private function getDefaultPhoto($gender, $famRole)
    {
        $photoFile = "../Images/Person/man-128.png";
        if ($gender == 1 && $famRole == "Child") {
            $photoFile = "../Images/Person/kid_boy-128.png";
        } else if ($gender == 2 && $famRole  != "Child") {
            $photoFile = "../Images/Person/woman-128.png";
        } else if ($gender == 2 && $famRole  == "Child") {
            $photoFile = "../Images/Person/kid_girl-128.png";
        }

        return $photoFile;
    }
	
	public function insertPerson($user)
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
           FilterInput($user->name->title) . "','" .
           FilterInput($user->name->first) . "',NULL,'" .
           FilterInput($user->name->last) . "',NULL,'" .
           FilterInput($user->gender) . "','" .
           FilterInput($user->location->street) . "',NULL,'" .
           FilterInput($user->location->city) . "','" .
           FilterInput($user->location->state) . "','" .
           FilterInput($user->location->zip) . "','USA','" .
           FilterInput($user->phone) . "',NULL,'" .
           FilterInput($user->cell) . "','" .
           FilterInput($user->email) . "',NULL," .
            date('m', $user->dob) . "," .
            date('d', $user->dob) . "," .
            date('Y', $user->dob) . ",NULL,'" .
           FilterInput($user->famID) . "'," .
           FilterInput($user->per_fmr_id) . "," . "\"" .
            date('Y-m-d', $user->registered) .
            "\"" . ",1,'" .
            date("YmdHis") .
            "'," .
           FilterInput($_SESSION['iUserID']) . ",";

        if (isset($dFriendDate) && strlen($dFriendDate) > 0)
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

}

?>