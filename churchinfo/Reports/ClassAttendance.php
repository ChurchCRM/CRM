<?php
/*******************************************************************************
*
*  filename    : Reports/ClassAttendance.php
*  last change : 2003-08-30
*  description : Creates a PDF for a Sunday School Class Attendance List
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

$tFirstSunday = FilterInput($_GET["FirstSunday"]);
$tLastSunday = FilterInput($_GET["LastSunday"]);

$tNoSchool1 = FilterInput($_GET["NoSchool1"]);
$tNoSchool2 = FilterInput($_GET["NoSchool2"]);
$tNoSchool3 = FilterInput($_GET["NoSchool3"]);
$tNoSchool4 = FilterInput($_GET["NoSchool4"]);

$iExtraStudents = FilterInput($_GET["ExtraStudents"], 'int');
$iExtraTeachers = FilterInput($_GET["ExtraTeachers"], 'int');

$dFirstSunday = strtotime ($tFirstSunday);
$dLastSunday = strtotime ($tLastSunday);

$dNoSchool1 = strtotime ($tNoSchool1);
$dNoSchool2 = strtotime ($tNoSchool2);
$dNoSchool3 = strtotime ($tNoSchool3);
$dNoSchool4 = strtotime ($tNoSchool4);

class PDF_Attendance extends ChurchInfoReport {

	// Constructor
	function PDF_Attendance() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->SetMargins(0,0);
		$this->Open();
		$this->SetFont("Times",'',14);
		$this->SetAutoPageBreak(false);
		$this->AddPage ();
	}

   function DrawAttendanceCalendar ($nameX, $yTop, $aNames, $tTitle, $extraLines, $dFirstSunday, $dLastSunday,
                                    $dNoSchool1, $dNoSchool2, $dNoSchool3, $dNoSchool4)
   {
      $startMonthX = 60;
      $dayWid = 7;

      $yIncrement = 6;
      $yMonths = $yTop;
      $yDays = $yTop + $yIncrement;

      $y = $yDays + $yIncrement;

      $tweakLineY = -.85; // Offset the lines to line things up

      $this->SetFont("Times",'B',12);
      $this->WriteAt ($nameX, $yDays, $tTitle);

      $this->SetFont("Times",'',12);

      $numMembers = count ($aNames);
      for ($row = 0; $row < $numMembers; $row++)
      {
         extract ($aNames[$row]);

         $this->WriteAt ($nameX, $y, ($per_LastName . ", " . $per_FirstName));
         $y += $yIncrement;
      }
      $y += $extraLines * $yIncrement;
      $this->SetFont("Times",'B',12);
      $this->WriteAt ($nameX, $y, gettext ("Totals"));
      $this->SetFont("Times",'',12);

      // Paint the calendar grid
      $monthCounter = 0;
      $whichMonth = date ("n", $dFirstSunday);
      $dayX = $startMonthX;
      $monthX = $startMonthX;
      $weekIncrement = strtotime('+7 days') - strtotime('+0 days');
      $whichMonthDate = $dFirstSunday;
      $noSchoolCnt = 0;

      for ($whichSunday = $dFirstSunday; $whichSunday < $dLastSunday + $weekIncrement; $whichSunday+=$weekIncrement) {
// Write the days last in case they get blocked out by no-school rectangles
//         $this->WriteAt ($dayX, $yDays, date ("d", $whichSunday));

         $tWhichSunday = date ("Y-m-d", $whichSunday);
         if ($tWhichSunday == date ("Y-m-d", $dNoSchool1))
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == date ("Y-m-d", $dNoSchool2))
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == date ("Y-m-d", $dNoSchool3))
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == date ("Y-m-d", $dNoSchool4))
            $aNoSchoolX[$noSchoolCnt++] = $dayX;

         if (date ("n", $whichSunday) != $whichMonth) { // Finish the previous month
            $this->WriteAt ($monthX, $yMonths, date ("F", $whichMonthDate));
            $whichMonth = date ("n", $whichSunday);
            $whichMonthDate = $whichSunday;
            $monthX = $dayX;
         }
         $dayX += $dayWid;
      }
      $whichMonth = date ("n", $whichSunday);
      $this->WriteAt ($monthX, $yMonths, date ("F", $whichMonthDate));

      $rightEdgeX = $dayX;

      // Draw heavy lines to delimit the Months and totals
      $this->SetLineWidth (0.5);
      $this->Line ($nameX, $yMonths + $tweakLineY, $rightEdgeX, $yMonths + $tweakLineY);
      $this->Line ($nameX, $yMonths + $yIncrement + $tweakLineY, $rightEdgeX, $yMonths + $yIncrement + $tweakLineY);
      $this->Line ($nameX, $yMonths + 2 * $yIncrement + $tweakLineY, $rightEdgeX, $yMonths + 2 * $yIncrement + $tweakLineY);
      $yBottom = $yMonths + (($numMembers + $extraLines + 2) * $yIncrement) + $tweakLineY;
      $this->Line ($nameX, $yBottom, $rightEdgeX, $yBottom);
      $this->Line ($nameX, $yBottom + $yIncrement, $rightEdgeX, $yBottom + $yIncrement);

      // Draw lines between the students
      $y = $yTop + $tweakLineY;
      $this->SetLineWidth (0.25);
      for ($studentInd = 0; $studentInd < $numMembers + $extraLines + 2; $studentInd++) {
         $this->Line ($nameX, $y, $rightEdgeX, $y);
         $y += $yIncrement;
      }
      $y += $yIncrement; // Make room for the title row
      $bottomY = $y;

      // Draw vertical lines now that we know how far down the list goes

      // Draw the left-most vertical line heavy, through the month row
      $this->SetLineWidth (0.5);
      $this->Line ($nameX, $yMonths + $tweakLineY, $nameX, $bottomY);

      $dayX = $startMonthX;
      $monthX = $startMonthX;
      for ($whichSunday = $dFirstSunday; $whichSunday < $dLastSunday + $weekIncrement; $whichSunday+=$weekIncrement) {
         $lineTopY = $yDays + $tweakLineY;
         $this->SetLineWidth (0.25);
         if (date ("n", $whichSunday) != $whichMonth) { // This is a month line
            $this->SetLineWidth (0.5);
            $whichMonth = date ("n", $whichSunday);
            $lineTopY = $yMonths + $tweakLineY;
         }
         $this->Line ($dayX, $lineTopY, $dayX, $bottomY);
         $dayX += $dayWid;
      }
      // Draw the right-most vertical line heavy, through the month row
      $this->SetLineWidth (0.5);
      $this->Line ($dayX, $yMonths + $tweakLineY, $dayX, $bottomY);

      // Fill the no-school days
      $this->SetFillColor (200,200,200);
      $this->SetLineWidth (0.25);
      for ($i = 0; $i < count ($aNoSchoolX); $i++) {
         $this->Rect ($aNoSchoolX[$i], $yDays + $tweakLineY, $dayWid, $bottomY - $yDays - $tweakLineY, 'FD');
      }

      // Write the days last in case they got blocked out by the no-school rectangles
      $dayX = $startMonthX;
      for ($whichSunday = $dFirstSunday; $whichSunday < $dLastSunday + $weekIncrement; $whichSunday+=$weekIncrement) {
         $this->WriteAt ($dayX, $yDays, date ("d", $whichSunday));
         $dayX += $dayWid;
      }
      return ($bottomY);
   }
}

// Instantiate the class and build the report.
$pdf = new PDF_Attendance();

//Get the data on this group
$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
$aGroupData = mysql_fetch_array(RunQuery($sSQL));
extract($aGroupData);

// Paint the title section- class name and year on the top, then teachers/liaison
$yTitle = 10;
$yTeachers = $yTitle + 6;

$nameX = 20;
$yearX = 170;

$pdf->SetFont("Times",'B',16);

$pdf->WriteAt ($nameX, $yTitle, ($grp_Name . " - " . $grp_Description));

$FYString = (1995 + $iFYID) . "-" . substr ((1995 + $iFYID + 1), 2, 2);
$pdf->WriteAt ($yearX, $yTitle, $FYString);

$pdf->SetLineWidth (0.5);
$pdf->Line ($nameX, $yTeachers -0.75, 195, $yTeachers -0.75);

$ga = GetGroupArray ($iGroupID);
$numMembers = count ($ga);

// Build the teacher string- first teachers, then the liaison
$teacherString = "Teachers: ";
$bFirstTeacher = true;
$iTeacherCnt = 0;
$iStudentCnt = 0;
for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Teacher")) {
      $aTeachers[$iTeacherCnt++] = $ga[$row]; // Make an array of teachers while we're here
      if (! $bFirstTeacher)
         $teacherString .= ", ";
      $teacherString .= $per_FirstName . " " . $per_LastName;
      $bFirstTeacher = false;
   } else if ($lst_OptionName == gettext ("Student")) {
      $aStudents[$iStudentCnt++] = $ga[$row];
   }
}

for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Liaison")) {
      $teacherString .= "  " . gettext ("Liaison") . ":" . $per_FirstName . " " . $per_LastName . " " . $fam_HomePhone . " ";
   }
}

$pdf->SetFont("Times",'B',12);
$pdf->WriteAt ($nameX, $yTeachers, $teacherString);
$y = $pdf->DrawAttendanceCalendar ($nameX, $yTeachers = $yTitle + 20, $aStudents, "Students", $iExtraStudents, 
                                   $dFirstSunday, $dLastSunday, 
                                   $dNoSchool1, $dNoSchool2, $dNoSchool3, $dNoSchool4);
$pdf->DrawAttendanceCalendar ($nameX, $y + 12, $aTeachers, "Teachers", $iExtraTeachers, 
                              $dFirstSunday, $dLastSunday,
                              $dNoSchool1, $dNoSchool2, $dNoSchool3, $dNoSchool4);

if ($iPDFOutputType == 1)
	$pdf->Output("NewsLetterLabels" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
