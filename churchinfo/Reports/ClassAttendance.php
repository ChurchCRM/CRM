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
$tNoSchool5 = FilterInput($_GET["NoSchool5"]);
$tNoSchool6 = FilterInput($_GET["NoSchool6"]);
$tNoSchool7 = FilterInput($_GET["NoSchool7"]);
$tNoSchool8 = FilterInput($_GET["NoSchool8"]);

$iExtraStudents = FilterInput($_GET["ExtraStudents"], 'int');
$iExtraTeachers = FilterInput($_GET["ExtraTeachers"], 'int');

$dFirstSunday = strtotime ($tFirstSunday);
$dLastSunday = strtotime ($tLastSunday);

$dNoSchool1 = strtotime ($tNoSchool1);
$dNoSchool2 = strtotime ($tNoSchool2);
$dNoSchool3 = strtotime ($tNoSchool3);
$dNoSchool4 = strtotime ($tNoSchool4);
$dNoSchool5 = strtotime ($tNoSchool5);
$dNoSchool6 = strtotime ($tNoSchool6);
$dNoSchool7 = strtotime ($tNoSchool7);
$dNoSchool8 = strtotime ($tNoSchool8);

// Reformat the dates to get standardized text representation
$tFirstSunday = date ("Y-m-d", $dFirstSunday);
$tLastSunday = date ("Y-m-d", $dLastSunday);

$tNoSchool1 = date ("Y-m-d", $dNoSchool1);
$tNoSchool2 = date ("Y-m-d", $dNoSchool2);
$tNoSchool3 = date ("Y-m-d", $dNoSchool3);
$tNoSchool4 = date ("Y-m-d", $dNoSchool4);
$tNoSchool5 = date ("Y-m-d", $dNoSchool5);
$tNoSchool6 = date ("Y-m-d", $dNoSchool6);
$tNoSchool7 = date ("Y-m-d", $dNoSchool7);
$tNoSchool8 = date ("Y-m-d", $dNoSchool8);

class PDF_Attendance extends ChurchInfoReport {

	// Constructor
	function PDF_Attendance() {
		parent::FPDF("P", "mm", $this->paperFormat);

		$this->incrementY = 6;
		$this->SetMargins(0,0);
		$this->Open();
		$this->SetFont("Times",'',14);
		$this->SetAutoPageBreak(false);
		$this->AddPage ();
	}

   function DrawAttendanceCalendar ($nameX, $yTop, $aNames, $tTitle, $extraLines, 
                                    $tFirstSunday, $tLastSunday,
                                    $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4,
												$tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8)
   {
      $startMonthX = 60;
      $dayWid = 7;

      $yIncrement = 6;
      $yMonths = $yTop;
      $yDays = $yTop + $yIncrement;

      $y = $yDays + $yIncrement;

      $this->SetFont("Times",'B',12);
      $this->WriteAt ($nameX, $yDays, $tTitle);

      $this->SetFont("Times",'',12);

      $numMembers = count ($aNames);
      $phantomMembers = 0;
      for ($row = 0; $row < $numMembers; $row++)
      {
         extract ($aNames[$row]);

         $thisName = ($per_LastName . ", " . $per_FirstName);

         // Special handling for person listed twice- only show once in the Attendance Calendar
         // This happens when a child is listed in two different families (parents divorced and
         // both active in the church)
         if ($thisName != $prevThisName) {
            $this->WriteAt ($nameX, $y, $thisName);
            $y += $yIncrement;
         } else {
            $phantomMembers++;
         }
         $prevThisName = $thisName;
      }
      $y += $extraLines * $yIncrement;

      $this->SetFont("Times",'B',12);
      $this->WriteAt ($nameX, $y, gettext ("Totals"));
      $this->SetFont("Times",'',12);

      $bottomY = $y + $yIncrement;

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
         if ($tWhichSunday == $tNoSchool5)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == $tNoSchool6)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == $tNoSchool7)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;
         if ($tWhichSunday == $tNoSchool8)
            $aNoSchoolX[$noSchoolCnt++] = $dayX;

         if (date ("n", $dWhichSunday) != $whichMonth) { // Finish the previous month
				$this->PrintCenteredCell ($monthX, $yMonths, $dayX - $monthX, date ("F", $dWhichMonthDate));
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
      $aHeavyVerticalX[$heavyVerticalXCnt++] = $monthX;
      $this->WriteAt ($monthX, $yMonths, date ("F", $dWhichMonthDate));

      $rightEdgeX = $dayX;

      // Draw vertical lines now that we know how far down the list goes

      // Draw the left-most vertical line heavy, through the month row
      $this->SetLineWidth (0.5);
      $this->Line ($nameX, $yMonths, $nameX, $bottomY);

      // Draw the left-most line between the people and the calendar
      $lineTopY = $yMonths;
      $this->Line ($startMonthX, $lineTopY, $startMonthX, $bottomY);

      // Draw the vertical lines in the grid based on X coords stored above
      $this->SetLineWidth (0.5);
      for ($i = 0; $i < $heavyVerticalXCnt; $i++) {
         $this->Line ($aHeavyVerticalX[$i], $lineTopY, $aHeavyVerticalX[$i], $bottomY);
      }

      $lineTopY = $yDays;
      $this->SetLineWidth (0.25);
      for ($i = 0; $i < $lightVerticalXCnt; $i++) {
         $this->Line ($aLightVerticalX[$i], $lineTopY, $aLightVerticalX[$i], $bottomY);
      }

      // Draw the right-most vertical line heavy, through the month row
      $this->SetLineWidth (0.5);
      $this->Line ($dayX, $yMonths, $dayX, $bottomY);

      // Fill the no-school days
      $this->SetFillColor (200,200,200);
      $this->SetLineWidth (0.25);
      for ($i = 0; $i < count ($aNoSchoolX); $i++) {
         $this->Rect ($aNoSchoolX[$i], $yDays, $dayWid, $bottomY - $yDays, 'FD');
      }

      for ($i = 0; $i < $dayCounter; $i++)
         $this->WriteAt ($dayListX[$i], $yDays, $dayListNum[$i]);

      // Draw heavy lines to delimit the Months and totals
      $this->SetLineWidth (0.5);
      $this->Line ($nameX, $yMonths, $rightEdgeX, $yMonths);
      $this->Line ($nameX, $yMonths + $yIncrement, $rightEdgeX, $yMonths + $yIncrement);
      $this->Line ($nameX, $yMonths + 2 * $yIncrement, $rightEdgeX, $yMonths + 2 * $yIncrement);
      $yBottom = $yMonths + (($numMembers - $phantomMembers + $extraLines + 2) * $yIncrement);
      $this->Line ($nameX, $yBottom, $rightEdgeX, $yBottom);
      $this->Line ($nameX, $yBottom + $yIncrement, $rightEdgeX, $yBottom + $yIncrement);

      // Draw lines between the students
      $y = $yTop;
      $this->SetLineWidth (0.25);
      for ($studentInd = 0; $studentInd < $numMembers - $phantomMembers + $extraLines + 2; $studentInd++) {
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
$yTitle = 20;
$yTeachers = $yTitle + 6;

$nameX = 10;
$yearX = 170;

$pdf->SetFont("Times",'B',16);

$pdf->WriteAt ($nameX, $yTitle, ($grp_Name . " - " . $grp_Description));

$FYString = MakeFYString ($iFYID);
$pdf->WriteAt ($yearX, $yTitle, $FYString);

$pdf->SetLineWidth (0.5);
$pdf->Line ($nameX, $yTeachers -0.75, 195, $yTeachers -0.75);

$ga = GetGroupArray ($iGroupID);
$numMembers = count ($ga);

// Build the teacher string- first teachers, then the liaison
$teacherString = "Teachers: ";
$bFirstTeacher = true;
$iTeacherCnt = 0;
$iMaxTeachersFit = 4;
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

$liaisonString = "";
for ($row = 0; $row < $numMembers; $row++)
{
   extract ($ga[$row]);
   if ($lst_OptionName == gettext ("Liaison")) {
      $liaisonString .= gettext ("Liaison") . ":" . $per_FirstName . " " . $per_LastName . " " . $pdf->StripPhone ($fam_HomePhone) . " ";
   }
}

if ($iTeacherCnt < $iMaxTeachersFit)
	$teacherString .= "  " . $liaisonString;

$pdf->SetFont("Times",'B',12);

$y = $yTeachers;
$pdf->WriteAt ($nameX, $y, $teacherString);
$y += 4;

if ($iTeacherCnt >= $iMaxTeachersFit) {
	$pdf->WriteAt ($nameX, $y, $liaisonString);
	$y += 4;
}

$y = $pdf->DrawAttendanceCalendar ($nameX, $y + 6, $aStudents, "Students", $iExtraStudents, 
                                   $tFirstSunday, $tLastSunday, 
                                   $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4,
											  $tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8);
$pdf->DrawAttendanceCalendar ($nameX, $y + 12, $aTeachers, "Teachers", $iExtraTeachers, 
                              $tFirstSunday, $tLastSunday,
                              $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4,
										$tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8);

if ($iPDFOutputType == 1)
	$pdf->Output("ClassAttendance" . date("Ymd") . ".pdf", true);
else
	$pdf->Output();	
?>
