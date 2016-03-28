<?php

/*******************************************************************************
 *
 *  filename    : Reports/ConfirmReportEmail.php
 *  last change : 2014-11-28
 *  description : Creates a email with all the confirmation letters asking member
 *                families to verify the information in the database.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";
require "../Include/phpmailer/class.phpmailer.php";

class EmailPDF_ConfirmReport extends ChurchInfoReport
{

  // Constructor
  function EmailPDF_ConfirmReport()
  {
    parent::FPDF("P", "mm", $this->paperFormat);
    $this->leftX = 10;
    $this->SetFont("Times", '', 10);
    $this->SetMargins(10, 20);
    $this->Open();
    $this->SetAutoPageBreak(false);
  }

  function StartNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country)
  {
    $curY = $this->StartLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    $curY += 2 * $this->incrementY;
    $blurb = $this->sConfirm1;
    $this->WriteAt($this->leftX, $curY, $blurb);
    $curY += 2 * $this->incrementY;
    return ($curY);
  }

  function FinishPage($curY)
  {
    $curY += 2 * $this->incrementY;
    $this->WriteAt($this->leftX, $curY, $this->sConfirm2);

    $curY += 3 * $this->incrementY;
    $this->WriteAt($this->leftX, $curY, $this->sConfirm3);
    $curY += 2 * $this->incrementY;
    $this->WriteAt($this->leftX, $curY, $this->sConfirm4);

    if ($this->sConfirm5 != "")
    {
      $curY += 2 * $this->incrementY;
      $this->WriteAt($this->leftX, $curY, $this->sConfirm5);
      $curY += 2 * $this->incrementY;
    }
    if ($this->sConfirm6 != "")
    {
      $curY += 2 * $this->incrementY;
      $this->WriteAt($this->leftX, $curY, $this->sConfirm6);
    }

    $curY += 4 * $this->incrementY;

    $this->WriteAt($this->leftX, $curY, "Sincerely,");
    $curY += 4 * $this->incrementY;
    $this->WriteAt($this->leftX, $curY, $this->sConfirmSigner);
  }

  function getEmailConnection()
  {

    $mail = new PHPMailer();
    $mail->IsSMTP();
    // $mail->SMTPDebug  = 2; 
    $mail->SMTPAuth = true;
    $mail->Port = 2525;
    $mail->Host = $this->sSMTPHost;
    $mail->Username = $this->sSMTPUser;
    $mail->Password = $this->sSMTPPass;

    return $mail;
  }

}

$familyEmailSent = false;
$familiesEmailed = 0;

// Get the list of custom person fields
$sSQL = "SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysql_num_rows($rsCustomFields);

if ($numCustomFields > 0)
{
  $iFieldNum = 0;
  while ($rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_ASSOC))
  {
    extract($rowCustomField);
    $sCustomFieldName[$iFieldNum] = $custom_Name;
    $iFieldNum += 1;
  }
}

$sSubQuery = "";
if (FilterInput($_GET["familyId"], "int"))
{
  $sSubQuery = " and fam_id in (" . $_GET["familyId"] . ") ";
}

// Get all the families
$sSQL = "SELECT * from family_fam fam, person_per per where fam.fam_id = per.per_fam_id and per.per_email is not null and per.per_email != '' " . $sSubQuery . " group by fam_ID ORDER BY fam_Name";
$rsFamilies = RunQuery($sSQL);

$dataCol = 55;
$dataWid = 65;

// Loop through families
while ($aFam = mysql_fetch_array($rsFamilies))
{
  // Instantiate the directory class and build the report.
  $pdf = new EmailPDF_ConfirmReport();

  // Read in report settings from database
  $rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg");
  if ($rsConfig)
  {
    while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig))
    {
      $pdf->$cfg_name = $cfg_value;
    }
  }

  $mail = $pdf->getEmailConnection();
  $mail->SetFrom($pdf->sChurchEmail, $pdf->sChurchName);
  extract($aFam);

  $emaillist = "";

  $curY = $pdf->StartNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
  $curY += $pdf->incrementY;

  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Family name"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, $fam_Name); $curY += $pdf->incrementY;
  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Address 1"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, $fam_Address1); $curY += $pdf->incrementY;
  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Address 2"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, $fam_Address2); $curY += $pdf->incrementY;
  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("City, State, Zip"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, ($fam_City . ", " . $fam_State . "  " . $fam_Zip)); $curY += $pdf->incrementY;
  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Home phone"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, $fam_HomePhone); $curY += $pdf->incrementY;
  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Send Newsletter"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, $fam_SendNewsLetter); $curY += $pdf->incrementY;

// Missing the following information from the Family record:
// Wedding date (if present) - need to figure how to do this with sensitivity
// Family e-mail address

  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Anniversary Date"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, FormatDate($fam_WeddingDate));
  $curY += $pdf->incrementY;

  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($pdf->leftX, $curY, $dataCol - $pdf->leftX, gettext("Family E-Mail"));
  $pdf->SetFont("Times", '', 10);
  $pdf->WriteAtCell($dataCol, $curY, $dataWid, $fam_Email);
  if ($fam_Email != "")
  {
    $emaillist = $fam_Email;
  }

  $curY += $pdf->incrementY;
  $curY += $pdf->incrementY;

  $sSQL = "SELECT *, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole FROM person_per 
				LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
				WHERE per_fam_ID = " . $fam_ID . " ORDER BY per_fmr_ID";
  $rsFamilyMembers = RunQuery($sSQL);

  $XName = 10;
  $XGender = 50;
  $XRole = 60;
  $XEmail = 90;
  $XBirthday = 135;
  $XCellPhone = 155;
  $XClassification = 180;
  $XWorkPhone = 155;
  $XRight = 208;

  $pdf->SetFont("Times", 'B', 10);
  $pdf->WriteAtCell($XName, $curY, $XGender - $XName, gettext("Member Name"));
  $pdf->WriteAtCell($XGender, $curY, $XRole - $XGender, gettext("M/F"));
  $pdf->WriteAtCell($XRole, $curY, $XEmail - $XRole, gettext("Adult/Child"));
  $pdf->WriteAtCell($XEmail, $curY, $XBirthday - $XEmail, gettext("Email"));
  $pdf->WriteAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, gettext("Birthday"));
  $pdf->WriteAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, gettext("Cell phone"));
  $pdf->WriteAtCell($XClassification, $curY, $XRight - $XClassification, gettext("Member/Friend"));
  $pdf->SetFont("Times", '', 10);
  $curY += $pdf->incrementY;

  $numFamilyMembers = 0;
  while ($aMember = mysql_fetch_array($rsFamilyMembers))
  {
    $numFamilyMembers++; // add one to the people count
    extract($aMember);
    // Make sure the person data will display with adequate room for the trailer and group information
    if (($curY + $numCustomFields * $pdf->incrementY) > 260)
    {
      $curY = $pdf->StartLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
      $pdf->SetFont("Times", 'B', 10);
      $pdf->WriteAtCell($XName, $curY, $XGender - $XName, gettext("Member Name"));
      $pdf->WriteAtCell($XGender, $curY, $XRole - $XGender, gettext("M/F"));
      $pdf->WriteAtCell($XRole, $curY, $XEmail - $XRole, gettext("Adult/Child"));
      $pdf->WriteAtCell($XEmail, $curY, $XBirthday - $XEmail, gettext("Email"));
      $pdf->WriteAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, gettext("Birthday"));
      $pdf->WriteAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, gettext("Cell phone"));
      $pdf->WriteAtCell($XClassification, $curY, $XRight - $XClassification, gettext("Member/Friend"));
      $pdf->SetFont("Times", '', 10);
      $curY += $pdf->incrementY;
    }
    $iPersonID = $per_ID;
    $pdf->SetFont("Times", 'B', 10);
    $pdf->WriteAtCell($XName, $curY, $XGender - $XName, $per_FirstName . " " . $per_MiddleName . " " . $per_LastName);
    $pdf->SetFont("Times", '', 10);
    $genderStr = ($per_Gender == 1 ? "M" : "F");
    $pdf->WriteAtCell($XGender, $curY, $XRole - $XGender, $genderStr);
    $pdf->WriteAtCell($XRole, $curY, $XEmail - $XRole, $sFamRole);
    $pdf->WriteAtCell($XEmail, $curY, $XBirthday - $XEmail, $per_Email);
    if ($per_Email != "")
    {
      if ($emaillist == "")
      {
        $emaillist = $per_Email;
      }
      else
      {
        $emaillist = $emaillist . "," . $per_Email;
      }
    }
    if ($per_BirthYear)
      $birthdayStr = $per_BirthMonth . "/" . $per_BirthDay . "/" . $per_BirthYear;
    else
      $birthdayStr = "";
    $pdf->WriteAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, $birthdayStr);
    $pdf->WriteAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, $per_CellPhone);
    $pdf->WriteAtCell($XClassification, $curY, $XRight - $XClassification, $sClassName);
    $curY += $pdf->incrementY;
// Missing the following information for the personal record: ??? Is this the place to put this data ???
// Work Phone
    $pdf->WriteAtCell($XWorkPhone, $curY, $XRight - $XWorkPhone, "Work Phone:" . $per_WorkPhone);
    $curY += $pdf->incrementY;
    $curY += $pdf->incrementY;

// *** All custom fields ***
// Get the list of custom person fields

    $xSize = 40;
    $numCustomFields = mysql_num_rows($rsCustomFields);
    if ($numCustomFields > 0)
    {
      extract($aMember);
      $sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $per_ID;
      $rsCustomData = RunQuery($sSQL);
      $aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);
      $numCustomData = mysql_num_rows($rsCustomData);
      mysql_data_seek($rsCustomFields, 0);
      $OutStr = "";
      $xInc = $XName; // Set the starting column for Custom fields
      // Here is where we determine if space is available on the current page to
      // display the custom data and still get the ending on the page
      // Calculations (without groups) show 84 mm is needed.
      // For the Letter size of 279 mm, this says that curY can be no bigger than 195 mm.
      // Leaving 12 mm for a bottom margin yields 183 mm.
      $numWide = 0; // starting value for columns	
      while ($rowCustomField = mysql_fetch_array($rsCustomFields, MYSQL_BOTH))
      {
        extract($rowCustomField);
        if ($sCustomFieldName[$custom_Order - 1])
        {
          $currentFieldData = trim($aCustomData[$custom_Field]);


          $OutStr = $sCustomFieldName[$custom_Order - 1] . " : " . $currentFieldData . "    ";
          $pdf->WriteAtCell($xInc, $curY, $xSize, $sCustomFieldName[$custom_Order - 1]);
          if ($currentFieldData == "")
          {
            $pdf->SetFont("Times", 'B', 6);
            $pdf->WriteAtCell($xInc + $xSize, $curY, $xSize, "");
            $pdf->SetFont("Times", '', 10);
          }
          else
          {
            $pdf->WriteAtCell($xInc + $xSize, $curY, $xSize, $currentFieldData);
          }
          $numWide += 1; // increment the number of columns done
          $xInc += (2 * $xSize); // Increment the X position by about 1/2 page width
          if (($numWide % 2) == 0) // 2 columns
          {
            $xInc = $XName; // Reset margin
            $curY += $pdf->incrementY;
          }
        }
      }
      //$pdf->WriteAt($XName,$curY,$OutStr);
      //$curY += (2 * $pdf->incrementY);
    }
    $curY += 2 * $pdf->incrementY;
  }
//


  $curY += $pdf->incrementY;

  if (($curY + 2 * $numFamilyMembers * $pdf->incrementY) >= 260)
  {
    $curY = $pdf->StartLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
  }
  $sSQL = "SELECT * FROM person_per WHERE per_fam_ID = " . $fam_ID . " ORDER BY per_fmr_ID";
  $rsFamilyMembers = RunQuery($sSQL);
  while ($aMember = mysql_fetch_array($rsFamilyMembers))
  {
    extract($aMember);

    // Get the Groups this Person is assigned to
    $sSQL = "SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
				FROM group_grp
				LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
				LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
				WHERE person2group2role_p2g2r.p2g2r_per_ID = " . $per_ID . "
				ORDER BY grp_Name";
    $rsAssignedGroups = RunQuery($sSQL);
    if (mysql_num_rows($rsAssignedGroups) > 0)
    {
      $groupStr = "Assigned groups for " . $per_FirstName . " " . $per_LastName . ": ";

      while ($aGroup = mysql_fetch_array($rsAssignedGroups))
      {
        extract($aGroup);
        $groupStr .= $grp_Name . " (" . $roleName . ") ";
      }

      $pdf->WriteAt($pdf->leftX, $curY, $groupStr);
      $curY += 2 * $pdf->incrementY;
    }
  }

  if ($curY > 183) // This insures the trailer information fits continuously on the page (3 inches of "footer"
  {
    $curY = $pdf->StartLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
  }
  $pdf->FinishPage($curY);

  if ($emaillist != "")
  {

    header('Pragma: public');  // Needed for IE when using a shared SSL certificate

    $doc = $pdf->Output("ConfirmReportEmail-" . $fam_ID . "-" . date("Ymd") . ".pdf", "S");

    $subject = $fam_Name . ' Family Information Review';

    if ($_GET["updated"])
    {
      $subject = $subject . " ** Updated **";
    }

    $message = "Dear " . $fam_Name . " Family <p>" . $pdf->sConfirm1 . "</p>Sincerely, <br/>" . $pdf->sConfirmSigner;

    $mail->Subject = $subject;
    $mail->MsgHTML($message);
    $mail->isHTML(true);
    $filename = "ConfirmReportEmail-" . $fam_Name . "-" . date("Ymd") . ".pdf";
    $mail->AddStringAttachment($doc, $filename);
    foreach ($myArray = explode(',', $emaillist) as $address)
    {
      $mail->AddAddress($address);
    }
    $familyEmailSent = $mail->Send();
    if ($familyEmailSent)
    {
      $familiesEmailed = $familiesEmailed + 1;
    }
  }
}

if ($_GET["familyId"]) {
  Redirect("FamilyView.php?FamilyID=" . $_GET["familyId"]."&PDFEmailed=".$familyEmailSent);
} else {
  Redirect("FamilyList.php?AllPDFsEmailed=".$familiesEmailed);
}
?>
