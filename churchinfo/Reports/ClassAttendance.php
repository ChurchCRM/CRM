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

// Reformat the dates to get standardized text representation
$tFirstSunday = date ("Y-m-d", $dFirstSunday);
$tLastSunday = date ("Y-m-d", $dLastSunday);

$tNoSchool1 = date ("Y-m-d", $dNoSchool1);
$tNoSchool2 = date ("Y-m-d", $dNoSchool2);
$tNoSchool3 = date ("Y-m-d", $dNoSchool3);
$tNoSchool4 = date ("Y-m-d", $dNoSchool4);

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

   function DrawAttendanceCalendar ($nameX, $yTop, $aNames, $tTitle, $extraLines, 
                                    $tFirstSunday, $tLastSunday,
                                    $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4)
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

      $bottomY = $y + $yIncrement + $tweakLineY;

      // Paint the calendar grid
      $dayCounter = 0;
      $monthCounter = 0;
      $dayX = $startMonthX;
      $monthX = $startMonthX;
      $noSchoolCnt = 0;
      $heavyVerticalXCnt = 0;
      $lightVerticalXCnt = 0;

      $tWhichSunday = $tFirstSunday;
      $dWhichSunday = strtotime ($tWhichSunday);

      $dWhichMonthDate = $dWhichSunday;
      $whichMonth = date ("n", $dWhichMonthDate);

      $doneFlag = false;

      while (! $doneFlag) {
         $dayListX[$dayCounter] = $dayX;
         $dayListNum[$dayCounter] = date ("d", $dWhichSunday);

         if ($tWhichSunday == $tNoSchool1)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == $tNoSchool2)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == $tNoSchool3)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == $tNoSchool4)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;

         if (date ("n", $dWhichSunday) != $whichMonth) { // Finish the previous month
            $this->WriteAt ($monthX, $yMonths, date ("F", $dWhichMonthDate));
            $aHeavyVerticalX[$heavyVerticalXCnt++] = $monthX;
            $whichMonth = date ("n", $dWhichSunday);
            $dWhichMonthDate = $dWhichSunday;
            $monthX = $dayX;
         } else {
            $aLightVerticalX[$lightVerticalXCnt++] = $dayX;
         }
         $dayX += $dayWid;
         ++$dayCounter;

         if ($tWhichSunday == $tLastSunday)
            $doneFlag = true;

         // Increment the date by one week
         $sundayDay = date ("d", $dWhichSunday);
         $sundayMonth = date ("m", $dWhichSunday);
         $sundayYear = date ("Y", $dWhichSunday);
         $dWhichSunday = mktime (0,0,0,$sundayMonth,$sundayDay+7,$sundayYear);
         $tWhichSunday = date ("Y-m-d", $dWhichSunday);
      }
      $this->WriteAt ($monthX, $yMonths, date ("F", $dWhichMonthDate));

      $rightEdgeX = $dayX;

      // Draw vertical lines now that we know how far down the list goes

      // Draw the left-most vertical line heavy, through the month row
      $this->SetLineWidth (0.5);
      $this->Line ($nameX, $yMonths + $tweakLineY, $nameX, $bottomY);

      // Draw the left-most line between the people and the calendar
      $lineTopY = $yMonths + $tweakLineY;
      $this->Line ($startMonthX, $lineTopY, $startMonthX, $bottomY);

      // Draw the vertical lines in the grid based on X coords stored above
      $this->SetLineWidth (0.5);
      for ($i = 0; $i < $heavyVerticalXCnt; $i++) {
         $this->Line ($aHeavyVerticalX[$i], $lineTopY, $aHeavyVerticalX[$i], $bottomY);
      }

      $lineTopY = $yDays + $tweakLineY;
      $this->SetLineWidth (0.25);
      for ($i = 0; $i < $lightVerticalXCnt; $i++) {
         $this->Line ($aLightVerticalX[$i], $lineTopY, $aLightVerticalX[$i], $bottomY);
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

      for ($i = 0; $i < $dayCounter; $i++)
         $this->WriteAt ($dayListX[$i], $yDays, $dayListNum[$i]);

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
                                   $tFirstSunday, $tLastSunday, 
                                   $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4);
$pdf->DrawAttendanceCalendar ($nameX, $y + 12, $aTeachers, "Teachers", $iExtraTeachers, 
                              $tFirstSunday, $tLastSunday,
                              $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4);

if ($iPDFOutputType == 1)
	$pdf->Output("NewsLetterLabels" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
