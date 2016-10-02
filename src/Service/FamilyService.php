<?php

namespace ChurchCRM\Service;

class FamilyService
{

  private $baseURL;

  public function __construct()
  {
    $this->baseURL = $_SESSION['sRootPath'];
  }

  function getViewURI($Id)
  {
    return $this->baseURL . "/FamilyView.php?FamilyID=" . $Id;
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

}

?>
