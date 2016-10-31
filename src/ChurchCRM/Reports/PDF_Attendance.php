<?php
namespace ChurchCRM\Reports;
class PDF_Attendance extends ChurchInfoReport {

/////////////////////////////////////////////////////////////////////////////
//
// function modified by S. Shaffer 3/2006 to change the following
// (1) handle the case where teh list of names covers more than one page
// (2) rearranged some of the code to make it clearer for multi-page
//
//    for information contact Steve Shaffer at stephen@shaffers4christ.com
//
/////////////////////////////////////////////////////////////////////////////

    // Constructor
    function __construct() {
		parent::__construct("P", "mm", $this->paperFormat);

		$this->incrementY = 6;
		$this->SetMargins(0,0);
		
		$this->SetFont("Times",'',14);
		$this->SetAutoPageBreak(false);
		$this->AddPage ();
    	}

   function DrawAttendanceCalendar ($nameX, $yTop, $aNames, $tTitle, $extraLines, 
                                    $tFirstSunday, $tLastSunday,
                                    $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4,
				    $tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8,$rptHeader)
   {
	$startMonthX = 60;
      	$dayWid = 7;
      	$MaxLinesPerPage = 36;
      	$yIncrement = 6;
	$yTitle = 20;
	$yTeachers = $yTitle + 6;
	$nameX = 10;
	unset($NameList);
	$numMembers = 0;
	$aNameCount=0;
	
	$aNoSchoolX = array ();
	$noSchoolCnt = 0;
//
//  determine how many pages will be includes in this report
//

//
// First cull the input names array to remove duplicates, then extend the array to include the requested 
// number of blank lines
//
	$prevThisName = "";
      	$aNameCount = 0;
      	for ($row = 0; $row < count($aNames); $row++)
      	{
		extract ($aNames[$row]);
    		$thisName = ($per_LastName . ", " . $per_FirstName);

	         // Special handling for person listed twice- only show once in the Attendance Calendar
	         // This happens when a child is listed in two different families (parents divorced and
	         // both active in the church)
		if ($thisName != $prevThisName){
			$NameList[$aNameCount++] = $thisName;
//			echo "adding {$thisName} to NameList at {$aNameCount}\n\r";
		}
		$prevThisName = $thisName;
      	}
//
// add extra blank lines to the array 
//
	for ($i=0; $i<$extraLines; $i++) $NameList[]="   ";

	$numMembers = count($NameList);
	$nPages = ceil($numMembers / $MaxLinesPerPage);
//	echo "nPages = {$nPages} \n\r";
//
// Main loop which draws each page
//
  	for($p=0; $p < $nPages; $p++) {
//
// 	Paint the title section- class name and year on the top, then teachers/liaison
//
		if($p > 0) $this->AddPage();
		$this->SetFont("Times",'B',16);
		$this->WriteAt ($nameX, $yTitle, $rptHeader);
		$this->SetLineWidth (0.5);
		$this->Line ($nameX, $yTeachers -0.75, 195, $yTeachers -0.75);
      		$yMonths = $yTop;
      		$yDays = $yTop + $yIncrement;
      		$y = $yDays + $yIncrement;
//
//	put title on the page
//
      		$this->SetFont("Times",'B',12);
      		$this->WriteAt ($nameX, $yDays + 1, $tTitle);
      		$this->SetFont("Times",'',12);

//
// calculate the starting and ending rows for the page
//
      		$pRowStart = $p * $MaxLinesPerPage;
		$pRowEnd = Min(((($p+1) * $MaxLinesPerPage)),$numMembers);
//		echo "pRowStart = {$pRowStart} and pRowEnd= {$pRowEnd}\n\r";
//
// Write the names down the page and draw lines between
//

      		$this->SetLineWidth (0.25);
		for ($row = $pRowStart; $row < $pRowEnd; $row++)
      		{
                	$this->WriteAt ($nameX, $y + 1, $NameList[$row]);
         		$y += $yIncrement;
      		}
//
// write a totals text at the bottom
//
      		$this->SetFont("Times",'B',12);
      		$this->WriteAt ($nameX, $y + 1, gettext ("Totals"));
      		$this->SetFont("Times",'',12);

      		$bottomY = $y + $yIncrement;
//
// Paint the calendar grid
//
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

         		if ($tWhichSunday == $tNoSchool1) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool2) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool3) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool4) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool5) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool6) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool7) $aNoSchoolX[$noSchoolCnt++] = $dayX;
         		if ($tWhichSunday == $tNoSchool8) $aNoSchoolX[$noSchoolCnt++] = $dayX;

         		if (date ("n", $dWhichSunday) != $whichMonth) { // Finish the previous month
			    	$this->WriteAt ($monthX, $yMonths + 1, date ("F", $dWhichMonthDate));
            			$aHeavyVerticalX[$heavyVerticalXCnt++] = $monthX;
            			$whichMonth = date ("n", $dWhichSunday);
            			$dWhichMonthDate = $dWhichSunday;
            			$monthX = $dayX;
         		} else {
            			$aLightVerticalX[$lightVerticalXCnt++] = $dayX;
         		}
         		$dayX += $dayWid;
         		++$dayCounter;

//         		if ($tWhichSunday == $tLastSunday) $doneFlag = true;
//
//			replaced this conditional to correct a problem where begin and end dates were not the same
//			day of week
//
			if(strtotime($tWhichSunday) >= strtotime($tLastSunday)) $doneFlag = true;

// 	Increment the date by one week
//
         		$sundayDay = date ("d", $dWhichSunday);
         		$sundayMonth = date ("m", $dWhichSunday);
         		$sundayYear = date ("Y", $dWhichSunday);
         		$dWhichSunday = mktime (0,0,0,$sundayMonth,$sundayDay+7,$sundayYear);
         		$tWhichSunday = date ("Y-m-d", $dWhichSunday);
      		}
      		$aHeavyVerticalX[$heavyVerticalXCnt++] = $monthX;
      		$this->WriteAt ($monthX, $yMonths + 1, date ("F", $dWhichMonthDate));

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

      		for ($i = 0; $i < $dayCounter; $i++) $this->WriteAt ($dayListX[$i], $yDays + 1, $dayListNum[$i]);

      		// Draw heavy lines to delimit the Months and totals
      		$this->SetLineWidth (0.5);
      		$this->Line ($nameX, $yMonths, $rightEdgeX, $yMonths);
      		$this->Line ($nameX, $yMonths + $yIncrement, $rightEdgeX, $yMonths + $yIncrement);
      		$this->Line ($nameX, $yMonths + 2 * $yIncrement, $rightEdgeX, $yMonths + 2 * $yIncrement);
      		$yBottom = $yMonths + (($numMembers + $extraLines + 2) * $yIncrement);
      		$this->Line ($nameX, $yBottom, $rightEdgeX, $yBottom);
      		$this->Line ($nameX, $yBottom + $yIncrement, $rightEdgeX, $yBottom + $yIncrement);
//
//	add in horizontal lines between names
//
	      	$y = $yTop;
		for ($s = $pRowStart; $s < $pRowEnd+4; $s++){
         		$this->Line ($nameX, $y, $rightEdgeX, $y);
         		$y += $yIncrement;
      		}

//		$this->AddPage();
	}
    	return ($bottomY);
    }
}

?>