<?php
/*******************************************************************************
*
*  filename    : Reports/ClassList.php
*  last change : 2003-08-30
*  description : Creates a PDF for a Sunday School Class List
*
*  ChurchInfo is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
******************************************************************************/

require "../Include/Config.php";
require "../Include/Functions.php";
require "../Include/ReportFunctions.php";
require "../Include/ReportConfig.php";
require "../Include/GetGroupArray.php";

$iGroupID = FilterInput($_GET["GroupID"],'int');
$iFYID = FilterInput($_GET["FYID"],'int');
$dFirstSunday = FilterInput($_GET["FirstSunday"]);
$dLastSunday = FilterInput($_GET["LastSunday"]);

class PDF_ClassList extends ChurchInfoReport {

	// Constructor
	function PDF_ClassList() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetMargins(0,0);
		$this->Open();
		$this->SetFont("Times",'',14);
		$this->SetAutoPageBreak(false);
		$this->AddPage ();
	}
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ClassList();

//Get the data on this group
$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
$aGroupData = mysql_fetch_array(RunQuery($sSQL));
extract($aGroupData);

$nameX = 20;
$birthdayX = 60;
$parentsX = 85;
$phoneX = 170;

$yTitle = 10;
$yTeachers = 16;
$yStartStudents = 32;
$yIncrement = 4;

$pdf->SetFont("Times",'B',16);

$pdf->WriteAt ($nameX, $yTitle, ($grp_Name . " - " . $grp_Description));

$FYString = (1995 + $iFYID) . "-" . substr ((1995 + $iFYID + 1), 2, 2);
$pdf->WriteAt ($phoneX, $yTitle, $FYString);

$pdf->SetLineWidth (0.5);
$pdf->Line ($nameX, $yTeachers -0.75, 195, $yTeachers -0.75);

$ga = GetGroupArray ($iGroupID);
$numMembers = count ($ga);

$teacherString = "";
$bFirstTeacher = true;
for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Teacher")) {
      if (! $bFirstTeacher)
         $teacherString .= ", ";
      $phone = $pdf->StripPhone ($fam_HomePhone);
      $teacherString .= $per_FirstName . " " . $per_LastName . " " . $phone;
      $bFirstTeacher = false;
   }
}

for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Liaison")) {
      $teacherString .= "  " . gettext ("Liaison") . ":" . $per_FirstName . " " . $per_LastName . " " . $fam_HomePhone . " ";
   }
}

$pdf->SetFont("Times",'B',10);
$pdf->WriteAt ($nameX, $yTeachers, $teacherString);

$pdf->SetFont("Times",'',12);

$y = $yStartStudents;

for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);

   if ($lst_OptionName == gettext ("Student")) {
      $studentName = ($per_LastName . ", " . $per_FirstName);

      if ($studentName != $prevStudentName) {
         $pdf->WriteAt ($nameX, $y, $studentName);

         $birthdayStr = $per_BirthMonth . "-" . $per_BirthDay . "-" . $per_BirthYear;
         $pdf->WriteAt ($birthdayX, $y, $birthdayStr);
      }

      $parentsStr = $pdf->MakeSalutation ($fam_ID);
      $pdf->WriteAt ($parentsX, $y, $parentsStr);

      $pdf->WriteAt ($phoneX, $y, $pdf->StripPhone ($fam_HomePhone));
      $y += $yIncrement;

      $addrStr = $fam_Address1;
      if ($fam_Address2 != "")
         $addrStr .= " " .  fam_Address2;
      $addrStr .= ", " . $fam_City . ", " . $fam_State . "  " . $fam_Zip;
      $pdf->WriteAt ($parentsX, $y, $addrStr);

      $prevStudentName = $studentName;
      $y += 2 * $yIncrement;
   }
}

$pdf->SetFont("Times",'B',12);
$pdf->WriteAt ($phoneX, $y, date("d-M-Y"));

if ($iPDFOutputType == 1)
	$pdf->Output("ClassList" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
