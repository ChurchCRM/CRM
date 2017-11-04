<?php
/*******************************************************************************
*
*  filename    : Reports/PhotoBook.php
*  last change : 2017-11-04 Philippe Logel
*  description : Creates a PDF for a Sunday School Class List
*
******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';

use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\Map\PersonTableMap;
use Propel\Runtime\ActiveQuery\Criteria;

$iGroupID = InputUtils::LegacyFilterInput($_GET['GroupID']);
$aGrp = explode(',', $iGroupID);
$nGrps = count($aGrp);

$iFYID = InputUtils::LegacyFilterInput($_GET['FYID'], 'int');
$dFirstSunday = InputUtils::LegacyFilterInput($_GET['FirstSunday']);
$dLastSunday = InputUtils::LegacyFilterInput($_GET['LastSunday']);
$withPictures = InputUtils::LegacyFilterInput($_GET['pictures']);

class PDF_ClassList extends ChurchInfoReport
{
    // Constructor
    public function PDF_ClassList()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->SetMargins(0, 0);

        $this->SetFont('Times', '', 14);
        $this->SetAutoPageBreak(false);
        $this->AddPage();
    }
}

// Instantiate the directory class and build the report.
$pdf = new PDF_ClassList();

for ($i = 0; $i < $nGrps; $i++) {
	$iGroupID = $aGrp[$i];
	
	if ($i > 0) {
     $pdf->AddPage();
  }
    
	$group = GroupQuery::Create()->findOneById($iGroupID);

	$nameX = 20;
	$birthdayX = 70;
	$parentsX = 95;
	$phoneX = 170;

	$yTitle = 20;
	$yTeachers = 26;
	$yOffsetStartStudents = 6;
	$yIncrement = 4;

	$pdf->SetFont('Times', 'B', 16);

	$pdf->WriteAt($nameX, $yTitle, (gettext("PhotoBook").' - '.$group->getName().' - '.gettext("Students")));

	$FYString = MakeFYString($iFYID);
	$pdf->WriteAt($phoneX, $yTitle, $FYString);

	$pdf->SetLineWidth(0.5);
	$pdf->Line($nameX, $yTeachers - 0.75, 195, $yTeachers - 0.75);

	$groupRoleMemberships = ChurchCRM\Person2group2roleP2g2rQuery::create()
							->joinWithPerson()
							->orderBy(PersonTableMap::COL_PER_LASTNAME)
							->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME) // I've try to reproduce per_LastName, per_FirstName
							->findByGroupId($iGroupID);

	$teacherCount = 0;
	$studentCount = 0;

	$students = [];
	$teachers = [];

	foreach ($groupRoleMemberships as $groupRoleMembership) {		
			$person = $groupRoleMembership->getPerson();					

			$groupRole = ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($groupRoleMembership->getRoleId())->findOne();				
			$lst_OptionName = $groupRole->getOptionName();
		
			if ($lst_OptionName == 'Teacher') {
					$elt = ['perID' => $groupRoleMembership->getPersonId()];
					array_push($teachers,$elt);
					++$teacherCount;
			} else if ($lst_OptionName == 'Student') { 
					 $elt = ['perID' => $groupRoleMembership->getPersonId()];								 
					 array_push($students,$elt);
					 ++$studentCount;
			}
	}

	$y = $yTeachers;
	$y += $yIncrement;
	$y += $yOffsetStartStudents;

	$pdf->SetFont('Times', '', 9);
	$prevStudentName = '';
	
	$withStudentName = 40;
	$imageHeight=30;

	$numMembers = count ($students);
	
	$nameX = 0;

	for ($row = 0; $row < $numMembers; $row++){	
			$student = $students[$row];
		
			$person = PersonQuery::create()->findPk($student['perID']);		
			$studentName = $person->getFullName();
		
			if ($studentName != $prevStudentName) {
					$pdf->WriteAt($nameX+$imageHeight/1.22-$pdf->GetStringWidth($studentName)/2, $y+$imageHeight+2, $studentName);
					$nameX += $withStudentName;
				
					$imgName = str_replace(SystemURLs::getDocumentRoot(),"",$person->getPhotoURI());
				
					$pdf->SetLineWidth(0.25);
					$pdf->Line($nameX-$imageHeight,$y,$nameX,$y);
					$pdf->Line($nameX-$imageHeight,$y+$imageHeight,$nameX,$y+$imageHeight);
					$pdf->Line($nameX-$imageHeight,$y,$nameX,$y);
					$pdf->Line($nameX-$imageHeight,$y,$nameX-$imageHeight,$y+$imageHeight);
					$pdf->Line($nameX,$y,$nameX,$y+$imageHeight);
		
					// we build the cross in the case of there's no photo
					//$this->SetLineWidth(0.25);
					$pdf->Line($nameX-$imageHeight,$y+$imageHeight,$nameX,$y);
					$pdf->Line($nameX-$imageHeight,$y,$nameX,$y+$imageHeight);
				
					if ($imgName != '   ' && strlen($imgName) > 5 && file_exists($_SERVER['DOCUMENT_ROOT'].$imgName))
					{
						list($width, $height) = getimagesize($_SERVER['DOCUMENT_ROOT'].$img);
						$nw = $imageHeight;
						$nh = $imageHeight;
			
						$pdf->Image('https://'.$_SERVER['HTTP_HOST'].$imgName, $nameX-$nw , $y, $nw,$nh,'PNG');
					}
					
					$nameX -= $withStudentName;

					$nameX+=$withStudentName;
					$nameX=$nameX%($withStudentName*5);
					
					if ($nameX == 0)
						$y+=$imageHeight*2;
						
			}

			$prevStudentName = $studentName;
			//$y += 1.5 * $yIncrement;

			if ($y > 250) {
					$pdf->AddPage();
					$y = 20;
			}  
	}
	
	if ($nameX != 0)
		$y+=$imageHeight*1.2;
	else
		$y-=$imageHeight/2;

	$pdf->SetFont('Times', 'B', 12);
	$pdf->WriteAt($phoneX-7, $y+5, FormatDate(date('Y-m-d')));

	
	// we start to create all the teachers
	$pdf->AddPage();
	$nameX = 20;
	$yTitle = 20;
	$pdf->WriteAt($nameX, $yTitle, (gettext("PhotoBook").' - '.$group->getName().' - '.gettext("Teachers")));

	$FYString = MakeFYString($iFYID);
	$pdf->WriteAt($phoneX, $yTitle, $FYString);	
	$pdf->SetLineWidth(0.5);
	$pdf->Line($nameX, $yTeachers - 0.75, 195, $yTeachers - 0.75);

	$nameX = 0;
	$y = $yTeachers;
	$y += $yIncrement;
	$y += $yOffsetStartStudents;

	$teacherCount = count ($teachers);
	
	$pdf->SetFont('Times', '', 9);
	$prevTeacherName = '';
	
	$withTeacherName = 40;
	
	for ($row = 0; $row < $teacherCount; $row++){	
			$teacher = $teachers[$row];
		
			$person = PersonQuery::create()->findPk($teacher['perID']);		
			$teacherName = $person->getFullName();
		
			if ($teacherName != $prevTeacherName) {
					$pdf->WriteAt($nameX+$imageHeight/1.22-$pdf->GetStringWidth($teacherName)/2, $y+$imageHeight+2, $teacherName);
					$nameX += $withTeacherName;
				
					$imgName = str_replace(SystemURLs::getDocumentRoot(),"",$person->getPhotoURI());
					
					$pdf->SetLineWidth(0.25);
					$pdf->Line($nameX-$imageHeight,$y,$nameX,$y);
					$pdf->Line($nameX-$imageHeight,$y+$imageHeight,$nameX,$y+$imageHeight);
					$pdf->Line($nameX-$imageHeight,$y,$nameX,$y);
					$pdf->Line($nameX-$imageHeight,$y,$nameX-$imageHeight,$y+$imageHeight);
					$pdf->Line($nameX,$y,$nameX,$y+$imageHeight);
		
					// we build the cross in the case of there's no photo
					//$this->SetLineWidth(0.25);
					$pdf->Line($nameX-$imageHeight,$y+$imageHeight,$nameX,$y);
					$pdf->Line($nameX-$imageHeight,$y,$nameX,$y+$imageHeight);
				
					if ($imgName != '   ' && strlen($imgName) > 5 && file_exists($_SERVER['DOCUMENT_ROOT'].$imgName))
					{
						list($width, $height) = getimagesize($_SERVER['DOCUMENT_ROOT'].$img);
						$nw = $imageHeight;
						$nh = $imageHeight;
			
						$pdf->Image('https://'.$_SERVER['HTTP_HOST'].$imgName, $nameX-$nw , $y, $nw,$nh,'PNG');
					}
					
					$nameX -= $withTeacherName;

					$nameX+=$withTeacherName;
					$nameX=$nameX%($withTeacherName*5);
					
					if ($nameX == 0)
						$y+=$imageHeight*2;
						
					//echo $nameX." ".$y."<br>";
						
			}

			$prevTeacherName = $teacherName;

			if ($y > 250) {
					$pdf->AddPage();
					$y = 20;
			}  
	}
	
	if ($nameX != 0)
		$y+=$imageHeight*1.2;
	else
		$y-=$imageHeight/2;


	$pdf->SetFont('Times', 'B', 12);
	$pdf->WriteAt($phoneX-7, $y+5, FormatDate(date('Y-m-d')));
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ClassList'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
