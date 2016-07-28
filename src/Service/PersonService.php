<?php

// require_once (dirname(__FILE__).DIRECTORY_SEPARATOR."/../vendor/autoload.php");

// require_once "../orm/model/ChurchCRM/members/PersonQuery.php";

// use ChurchCRM\members\PersonQuery as PersonQuery;

class PersonService
{
  private $baseURL;

  public function __construct()
  {
    $this->baseURL = $_SESSION['sRootPath'];
  }

  function get($id)
  {
    //return $this->personQuery->findPK($id);
    $sSQL = 'SELECT per_ID, per_FirstName, per_LastName, per_Gender, per_Email FROM person_per WHERE per_ID =' . $id;
    $person = RunQuery($sSQL);
    extract(mysql_fetch_array($person));
    return "{id: $id, fName: $per_FirstName}";
  }

  function getBirthDays()
  {
    //return $this->personQuery->findPK($id);
    $sSQL = 'SELECT per_ID, per_FirstName, per_LastName, per_BirthMonth, per_BirthDay FROM person_per';
    $result = mysql_query($sSQL);

    $return = array();
    while ($row = mysql_fetch_array($result)) {
      $values['id'] = $row['per_ID'];
      $values['firstName'] = $row['per_FirstName'];
      $values['lastName'] = $row['per_LastName'];
      $values['birthDay'] = $row['per_BirthDay'];
      $values['birthMonth'] = $row['per_BirthMonth'];
      $values['uri'] = $this->getViewURI($row['per_ID']);

      array_push($return, $values);
    }

    return $return;
  }

  function getPhoto($id)
  {
    global $sEnableGravatarPhotos;
    if ( $id != "" ) {
      $photoFile = $this->getUploadedPhoto($id);
      if ( $photoFile == "" ) {
        $sSQL = 'SELECT per_ID, per_FirstName, per_LastName, per_Gender, per_Email, fmr.lst_OptionName AS sFamRole
                 FROM person_per per
                 LEFT JOIN list_lst fmr ON per.per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
                 WHERE per_ID =' . $id;
        $person = RunQuery($sSQL);
        extract(mysql_fetch_array($person));
        if ( $per_Email != "" && $sEnableGravatarPhotos)
        {
          $photoFile = $this->getGravatar($per_Email);
        }

        if ( $photoFile == "" ) {
          $photoFile = $this->getDefaultPhoto($per_Gender, $sFamRole);
        }
      }
       return $photoFile;
    }

    return $this->baseURL . "/Images/x.gif";
  }

  function deleteUploadedPhoto($id)
  {
    requireUserGroupMembership("bEditRecords");
    $validExtensions = array("jpeg", "jpg", "png");
    $finalFileName = "Images/Person/" . $id;
    $finalFileNameThumb = "Images/Person/thumbnails/" . $id;
    $deleted = false;
    while (list(, $ext) = each($validExtensions)) {
      $tmpFile = $finalFileName . "." . $ext;
      if (file_exists($tmpFile)) {
        unlink($tmpFile);
        $deleted = true;
      }
      $tmpFile = $finalFileNameThumb . "." . $ext;
      if (file_exists($tmpFile)) {
        unlink($tmpFile);
        $deleted = true;
      }
    }
    return $deleted;
  }

  function getUploadedPhoto($personId)
  {
    $validextensions = array("jpeg", "jpg", "png");
    $hasFile = false;
    while (list(, $ext) = each($validextensions)) {
      $photoFile = dirname(__FILE__) . "/../Images/Person/thumbnails/" . $personId . "." . $ext;
      if (file_exists($photoFile)) {
        $hasFile = true;
        $photoFile = $this->baseURL . "/Images/Person/thumbnails/" . $personId . "." . $ext;
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
    $url = 'http://www.gravatar.com/avatar/';
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
    return $this->baseURL . "/PersonView.php?PersonID=" . $Id;
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

    return $return;
  }

  function getPersonByID($per_ID)
  {
    $fetch = "SELECT per_ID, per_FirstName, LEFT(per_MiddleName,1) AS per_MiddleName, per_LastName, per_Title, per_Suffix, per_Address1, per_Address2, per_City, per_State, per_Zip, per_CellPhone, per_Country, per_Email, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_CellPhone, fam_Email
            FROM person_per
            LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
        WHERE per_ID = " . $per_ID;
    $result = mysql_query($fetch);
    $row = mysql_fetch_assoc($result);
    $row['photo'] = $this->getPhoto($per_ID);
    $row['displayName'] = $row['per_FirstName'] . " " . $row['per_LastName'];

    return $row;
  }

  function getPersonsJSON($persons)
  {
    if ($persons) {
      return '{"persons": ' . json_encode($persons) . '}';
    } else {
      return false;
    }
  }

  private
  function getDefaultPhoto($gender, $famRole)
  {
    $photoFile = $this->baseURL . "/Images/Person/man-128.png";
    if ($gender == 1 && $famRole == "Child") {
      $photoFile = $this->baseURL . "/Images/Person/kid_boy-128.png";
    } else if ($gender == 2 && $famRole != "Child") {
      $photoFile = $this->baseURL . "/Images/Person/woman-128.png";
    } else if ($gender == 2 && $famRole == "Child") {
      $photoFile = $this->baseURL . "/Images/Person/kid_girl-128.png";
    }

    return $photoFile;
  }

  function insertPerson($user)
  {
    requireUserGroupMembership("bAddRecords");
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
      FilterInput($user->name->last) . "',NULL,'";
    if (FilterInput($user->gender) == "male") {
      $sSQL .= "1";
    } else {
      $sSQL .= "2";
    }
    $sSQL .= FilterInput($user->gender) . "','" .
      FilterInput($user->location->street) . "',\"\",'" .
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
    return $iPersonID;

  }

  function getPeopleEmailsAndGroups()
  {
    $sSQL = "SELECT per_FirstName, per_LastName, per_Email, per_ID, group_grp.grp_Name, lst_OptionName
	            from person_per
    		        left JOIN person2group2role_p2g2r on
                  person2group2role_p2g2r.p2g2r_per_ID = person_per.per_id

                left JOIN group_grp ON
                  person2group2role_p2g2r.p2g2r_grp_ID = group_grp.grp_ID

                left JOIN list_lst ON
                  group_grp.grp_RoleListID = list_lst.lst_ID AND
                  person2group2role_p2g2r.p2g2r_rle_ID =  list_lst.lst_OptionID

              where per_email != ''

              order by per_id;";
    $rsPeopleWithEmails = RunQuery($sSQL);
    $people = array();
    $lastPersonId = 0;
    $person = array();
    while ($row = mysql_fetch_array($rsPeopleWithEmails)) {
      if ($lastPersonId != $row["per_ID"]) {
        if ($lastPersonId != 0) {
          array_push($people, $person);
        }
        $person = array();
        $person["id"] = $row["per_ID"];
        $person["email"] = $row["per_Email"];
        $person["firstName"] = $row["per_FirstName"];
        $person["lastName"] = $row["per_LastName"];
      }

      $person[$row["grp_Name"]] = $row["lst_OptionName"];

      if ($lastPersonId != $row["per_ID"]) {
        $lastPersonId = $row["per_ID"];
      }
    }
    array_push($people, $person);
    return $people;
  }

}

?>
