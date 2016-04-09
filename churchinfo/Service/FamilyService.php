<?php

class FamilyService
{

  function getViewURI($Id)
  {
    return $_SESSION['sRootPath'] . "/FamilyView.php?FamilyID=" . $Id;
  }

  function search($searchTerm)
  {
    $fetch = 'SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE family_fam.fam_Name LIKE \'%' . $searchTerm . '%\' LIMIT 15';
    $result = mysql_query($fetch);
    $families = array();
    while ($row = mysql_fetch_array($result)) {
      $row_array['id'] = $row['fam_ID'];
      $row_array['familyName'] = $row['fam_Name'];
      $row_array['street'] = $row['fam_Address1'];
      $row_array['city'] = $row['fam_City'];
      $familyDisplayNameArray = array();
      array_push($familyDisplayNameArray, $row['fam_Name']);
      if ($row['fam_Address1'] != "") {
        array_push($familyDisplayNameArray, $row['fam_Address1']);
      }
      array_push($familyDisplayNameArray, $row['fam_City']);
      $row_array['displayName'] = join(" - ", array_filter($familyDisplayNameArray));
      $row_array['uri'] = $this->getViewURI($row['fam_ID']);
      array_push($families, $row_array);
    }
    return $families;
  }


  function lastEdited()
  {
    $sSQL = "select * from family_fam order by fam_DateLastEdited desc  LIMIT 10;";
    $rsLastFamilies = RunQuery($sSQL);
    $families = array();
    while ($row = mysql_fetch_array($rsLastFamilies)) {
      $row_array['id'] = $row['fam_ID'];
      $row_array['name'] = $row['fam_Name'];
      $row_array['address'] = $row['fam_Address1'];
      $row_array['city'] = $row['fam_City'];
      array_push($families, $row_array);
    }
    $this->returnFamilies($families);
  }

  function getFamiliesJSON($families)
  {
    if ($families) {
      return '{"families": ' . json_encode($families) . '}';
    } else {
      return false;
    }
  }

  function getFamilyPhoto($iFamilyID)
  {
    $photoFile = $this->getUploadedPhoto($iFamilyID);
    if ($photoFile != "") {
      return $photoFile;
    }
    return "Images/Family/family-128.png";
  }

  function getUploadedPhoto($iFamilyID)
  {
    $validExtensions = array("jpeg", "jpg", "png");
    while (list(, $ext) = each($validExtensions)) {
      $photoFile = "Images/Family/thumbnails/" . $iFamilyID . "." . $ext;
      if (file_exists($photoFile)) {
        return $photoFile;
      }
    }
    return "";
  }

  function getFamilyName($famID)
  {
    $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE fam_ID=" . $famID;
    $rsFamilies = RunQuery($sSQL);
    $aRow = mysql_fetch_array($rsFamilies);
    try {
      extract($aRow);
      $name = $fam_Name;
      if (isset ($aHead[$fam_ID])) {
        $name .= ", " . $aHead[$fam_ID];
      }
      $name .= " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
    } catch (Exception $e) {
      $name = "";
    }
    return $name;

  }

  function getFamilyStringByEnvelope($iEnvelope)
  {
    $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE fam_Envelope=" . $iEnvelope;
    $rsFamilies = RunQuery($sSQL);
    $familyArray = array();
    while ($aRow = mysql_fetch_array($rsFamilies)) {
      extract($aRow);
      $name = $this->getFamilyName($fam_ID);
      $familyArray = array("fam_ID" => $fam_ID, "Name" => $name);
    }
    return json_encode($familyArray);
  }

  function getFamilyStringByID($fam_ID)
  {
    $sSQL = "SELECT fam_ID, fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE fam_ID=" . $fam_ID;
    $rsFamilies = RunQuery($sSQL);
    $familyArray = array();
    while ($aRow = mysql_fetch_array($rsFamilies)) {
      extract($aRow);
      $name = $fam_Name;
      if (isset ($aHead[$fam_ID])) {
        $name .= ", " . $aHead[$fam_ID];
      }
      $name .= " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);

      $familyArray = array("fam_ID" => $fam_ID, "Name" => $name);
    }
    return json_encode($familyArray);
  }


  function setFamilyCheckingAccountDetails($tScanString, $iFamily)
  {
    requireUserGroupMembership("bFinance");
    //Set the Routing and Account Number for a family
    $routeAndAccount = $micrObj->FindRouteAndAccount($tScanString); // use routing and account number for matching
    $sSQL = "UPDATE family_fam SET fam_scanCheck=\"" . $routeAndAccount . "\" WHERE fam_ID = " . $iFamily;
    RunQuery($sSQL);
  }

  function insertFamily($user)
  {
    requireUserGroupMembership("bAddRecords");
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
      FilterInput($user->name->last) . "','" .
      FilterInput($user->location->street) . "','" .
      "\"\"','" .
      FilterInput($user->location->city) . "','" .
      FilterInput($user->location->state) . "','" .
      FilterInput($user->location->zip) . "','" .
      "USA','" .
      FilterInput($user->phone) . "','" .
      "NULL','" .
      FilterInput($user->cell) . "','" .
      FilterInput($user->email) . "'," .
      date('Y-m-d', $user->registered) . ",'" .
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
}

?>
