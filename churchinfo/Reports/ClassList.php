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
$birthdayX = 70;
$parentsX = 95;
$phoneX = 170;

$yTitle = 20;
$yTeachers = 26;
$yOffsetStartStudents = 6;
$yIncrement = 4;

$pdf->SetFont("Times",'B',16);

$pdf->WriteAt ($nameX, $yTitle, ($grp_Name . " - " . $grp_Description));

$FYString = (1995 + $iFYID) . "-" . substr ((1995 + $iFYID + 1), 2, 2);
$pdf->WriteAt ($phoneX, $yTitle, $FYString);

$pdf->SetLineWidth (0.5);
$pdf->Line ($nameX, $yTeachers -0.75, 195, $yTeachers -0.75);

$ga = GetGroupArray ($iGroupID);
$numMembers = count ($ga);

$teacherString1 = "";
$teacherString2 = "";
$teacherCount = 0;
$teachersThatFit = 4;

$bFirstTeacher1 = true;
$bFirstTeacher2 = true;
for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Teacher")) {
      $phone = $pdf->StripPhone ($fam_HomePhone);
	  if ($teacherCount >= $teachersThatFit) {
		  if (! $bFirstTeacher2)
			 $teacherString2 .= ", ";
	      $teacherString2 .= $per_FirstName . " " . $per_LastName . " " . $phone;
	      $bFirstTeacher2 = false;
	  } else {
		  if (! $bFirstTeacher1)
			 $teacherString1 .= ", ";
	      $teacherString1 .= $per_FirstName . " " . $per_LastName . " " . $phone;
	      $bFirstTeacher1 = false;
	  }
	  ++$teacherCount;
   }
}

$liaisonString = "";
for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Liaison")) {
      $liaisonString .= gettext ("Liaison") . ":" . $per_FirstName . " " . $per_LastName . " " . $fam_HomePhone . " ";
   }
}

$pdf->SetFont("Times",'B',10);

$y = $yTeachers;

$pdf->WriteAt ($nameX, $y, $teacherString1);
$y += $yIncrement;

if ($teacherCount > $teachersThatFit) {
	$pdf->WriteAt ($nameX, $y, $teacherString2);
	$y += $yIncrement;
}

$pdf->WriteAt ($nameX, $y, $liaisonString);
$y += $yOffsetStartStudents;

$pdf->SetFont("Times",'',12);

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
      $y += 1.5 * $yIncrement;

	  if ($y > 250) {
			$pdf->AddPage ();
			$y = 20;
	  }
   }
}

$pdf->SetFont("Times",'B',12);
$pdf->WriteAt ($phoneX, $y, date("d-M-Y"));

if ($iPDFOutputType == 1)
	$pdf->Output("ClassList" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
