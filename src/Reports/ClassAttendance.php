<?php
/*******************************************************************************
*
*  filename    : Reports/ClassAttendance.php
*  last change : 2013-02-22
*  description : Creates a PDF for a Sunday School Class Attendance List
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
use ChurchCRM\Reports\PDF_Attendance;
require "../Include/GetGroupArray.php";

$iGroupID = FilterInput($_GET["GroupID"]);
$aGrp=explode(",",$iGroupID);
$nGrps = count($aGrp);
//echo $iGroupID;

$iFYID = FilterInput($_GET["FYID"],'int');

$tFirstSunday = FilterInput($_GET["FirstSunday"]);
$tLastSunday = FilterInput($_GET["LastSunday"]);
$tAllRoles = FilterInput($_GET["AllRoles"],'int');
//echo "all roles ={$tAllRoles}";

$tNoSchool1 = FilterInputArr($_GET,"NoSchool1");
$tNoSchool2 = FilterInputArr($_GET,"NoSchool2");
$tNoSchool3 = FilterInputArr($_GET,"NoSchool3");
$tNoSchool4 = FilterInputArr($_GET,"NoSchool4");
$tNoSchool5 = FilterInputArr($_GET,"NoSchool5");
$tNoSchool6 = FilterInputArr($_GET,"NoSchool6");
$tNoSchool7 = FilterInputArr($_GET,"NoSchool7");
$tNoSchool8 = FilterInputArr($_GET,"NoSchool8");

$iExtraStudents = FilterInputArr($_GET,"ExtraStudents", 'int');
$iExtraTeachers = FilterInputArr($_GET,"ExtraTeachers", 'int');

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


// Instantiate the class and build the report.
$yTitle = 20;
$yTeachers = $yTitle + 6;
$nameX = 10;
$epd = 3;

$pdf = new PDF_Attendance();

for($i=0; $i<$nGrps; $i++) {
	$iGroupID = $aGrp[$i];
//	uset($aStudents);
	if($i>0) $pdf->AddPage();
//Get the data on this group
	$sSQL = "SELECT * FROM group_grp WHERE grp_ID = " . $iGroupID;
	$aGroupData = mysql_fetch_array(RunQuery($sSQL));
	extract($aGroupData);
	$FYString = MakeFYString ($iFYID);
	$reportHeader = str_pad($grp_Name,95).$FYString;

	$ga = GetGroupArray ($iGroupID);
	$numMembers = count ($ga);

// Build the teacher string- first teachers, then the liaison
	$teacherString = "Teachers: ";
	$bFirstTeacher = true;
	$iTeacherCnt = 0;
	$iMaxTeachersFit = 4;
	$iStudentCnt = 0;

	if($tAllRoles <> 1) {
		for ($row = 0; $row < $numMembers; $row++)
		{
   			extract ($ga[$row]);
 	   		if ($lst_OptionName == gettext ("Teacher")) {
	      			$aTeachers[$iTeacherCnt++] = $ga[$row]; // Make an array of teachers while we're here
	      			if (! $bFirstTeacher) $teacherString .= ", ";
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
				$tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8,$reportHeader);
		$pdf->DrawAttendanceCalendar ($nameX, $y + 12, $aTeachers, "Teachers", $iExtraTeachers, 
                              $tFirstSunday, $tLastSunday,
                              $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4,
				$tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8,"");
	} ELSE {
//
// print all roles on the attendance sheet
//
		$iStudentCnt=0;
		unset($aStudents);
		for ($row = 0; $row < $numMembers; $row++)
		{
	   		extract ($ga[$row]);
        		$aStudents[$iStudentCnt++] = $ga[$row];
        	}

		$pdf->SetFont("Times",'B',12);

		$y = $yTeachers;

		$y=$pdf->DrawAttendanceCalendar ($nameX, $y+6, $aStudents, "All Members", $iExtraStudents, 
                                   $tFirstSunday, $tLastSunday, 
                                   $tNoSchool1, $tNoSchool2, $tNoSchool3, $tNoSchool4,
				$tNoSchool5, $tNoSchool6, $tNoSchool7, $tNoSchool8,$reportHeader);

	}

}
header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1)
	$pdf->Output("ClassAttendance" . date("Ymd") . ".pdf", "D");
else
	$pdf->Output();	

?>
