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
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;

$iGroupID = InputUtils::LegacyFilterInput($_GET['GroupID']);
$aGrp = explode(',', $iGroupID);
$nGrps = count($aGrp);

$iFYID = InputUtils::LegacyFilterInput($_GET['FYID'], 'int');
$dFirstSunday = InputUtils::LegacyFilterInput($_GET['FirstSunday']);
$dLastSunday = InputUtils::LegacyFilterInput($_GET['LastSunday']);
$withPictures = InputUtils::LegacyFilterInput($_GET['pictures']);

class PDF_PhotoBook extends ChurchInfoReport
{
  private $students;
  private $teachers;
  private $group;
  private $FYIDString;
  private $currentX;
  private $currentY;
  private $imageHeight;
  
  // Constructor
  public function __construct($iGroupID,$iFYID)
  {
    parent::__construct('P', 'mm', $this->paperFormat);
    
    
    //$this->initializeArrays();
    $this->FYIDString = MakeFYString($iFYID);
    $this->imageHeight = 30;
    $this->group = GroupQuery::Create()->findOneById($iGroupID);
    $this->SetMargins(0, 0);
    $this->SetFont('Times', '', 14);
    $this->SetAutoPageBreak(false);
    $this->AddPage();
    $this->drawGroupMebersByRole("Teacher",gettext("Teachers"));
    $this->AddPage();
   
    $this->drawGroupMebersByRole("Student",gettext("Students"));
  }
    
  private function drawPageHeader($title) {
    $this->currentX = 20;
    $this->currentY = 26;

    $this->SetFont('Times', 'B', 16);

    $this->WriteAt($this->currentX, $this->currentY, $title);

    $this->WriteAt($this->currentX, $this->currentY, $this->iFYIDString);
    $this->SetLineWidth(0.5);
    $this->Line($this->currentX, $this->currentY - 0.75, 195, $this->currentY - 0.75);
  }
    
  private function drawPersonBlock($name, $thumbnailURI) {

    $nameX = $this->currentX+$this->imageHeight/1.22-$this->GetStringWidth($name)/2;
    $nameY = $this->currentY+$this->imageHeight+2;

    $this->WriteAt($nameX,$nameY , $name);


    $this->SetLineWidth(0.25);
    $this->Line($nameX-$this->imageHeight, $y, $nameX, $y);
    $this->Line($nameX-$this->imageHeight, $y+$this->imageHeight, $nameX, $y+$this->imageHeight);
    $this->Line($nameX-$this->imageHeight, $y, $nameX, $y);
    $this->Line($nameX-$this->imageHeight, $y, $nameX-$this->imageHeight, $y+$this->imageHeight);
    $this->Line($nameX, $y, $nameX, $y+$this->imageHeight);

    $nameX -= $widthName;
    $nameX+=$widthName;


    if ($nameX == 0) {
        $y+=$this->imageHeight*2;
    }

    if (file_exists($thumbnailURI)) {
        $nw = $this->imageHeight;
        $nh = $this->imageHeight;

        $this->Image($thumbnailURI, $nameX-$nw, $y, $nw, $nh, 'PNG');
    }
    else
    {
      $this->Line($nameX-$this->imageHeight, $y+$this->imageHeight, $nameX, $y);
      $this->Line($nameX-$this->imageHeight, $y, $nameX, $y+$this->imageHeight);
    }
    
    $this->currentX+=50;
  }

  private function initializeArrays(){
    
    
    $teacherCount = 0;
    $studentCount = 0;

    $this->students = [];
    $this->teachers = [];

    foreach ($groupRoleMemberships as $groupRoleMembership) {
      $person = $groupRoleMembership->getPerson();
      
      $lst_OptionName = $groupRole->getOptionName();
      if ($lst_OptionName == 'Teacher') {
        $elt = ['perID' => $groupRoleMembership->getPersonId()];
        array_push($teachers, $elt);
        ++$teacherCount;
      } elseif ($lst_OptionName == 'Student') {
        $elt = ['perID' => $groupRoleMembership->getPersonId()];
        array_push($students, $elt);
        ++$studentCount;
      }
    }
  }
  
  private function drawGroupMebersByRole($roleName,$roleDisplayName) {
    
    $this->drawPageHeader((gettext("PhotoBook").' - '.$this->group->getName().' - '.$roleDisplayName));
    $RoleListID =$this->group->getRoleListId();
    $groupRole = ListOptionQuery::create()->filterById($RoleListID)->filterByOptionName($roleName)->findOne();
    
    
    
    $groupRoleMemberships = Person2group2roleP2g2rQuery::create() 
                            ->filterByGroup($this->group)
                            ->filterByRoleId($groupRole->getOptionId())
                            ->joinWithPerson()
                            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME) // I've try to reproduce per_LastName, per_FirstName
                            ->find();
    //echo $groupRoleMemberships->count();
    
    $this->WriteAt(80,50,$groupRoleMemberships->count());
    foreach ($groupRoleMemberships as $roleMembership)
    {
      $person = $roleMembership->getPerson();
      $this->drawPersonBlock($person->getFullName(), $person->getThumbnailURI());
    }
  }

}


// Instantiate the directory class and build the report.
$pdf = new PDF_PhotoBook($iGroupID);
/*
for ($i = 0; $i < $nGrps; $i++) {
    $iGroupID = $aGrp[$i];
    
    if ($i > 0) {
        $pdf->AddPage();
    }
    
   

   

    


    $y = $yTeachers;
    $y += $yIncrement;
    $y += $yOffsetStartStudents;

    $pdf->SetFont('Times', '', 9);
    $prevStudentName = '';
    
    
   

    $numMembers = count($students);
    
    $PersonBlockWidth = 40;

    for ($row = 0; $row < $numMembers; $row++) {
        $student = $students[$row];
        
        $person = PersonQuery::create()->findPk($student['perID']);
        $studentName = $person->getFullName();
        
        if ($studentName != $prevStudentName) {
          drawPersonBlock($person->getFullName(), $person->getThumbnailURI(), $nameX, $y, $pdf);
          $nameX += $widthName;
        }

        $prevStudentName = $studentName;
        //$y += 1.5 * $yIncrement;

        if ($y > 250) {
            $pdf->AddPage();
            $y = 20;
        }
    }
    
    if ($nameX != 0) {
        $y+=$this->imageHeight*1.2;
    } else {
        $y-=$this->imageHeight/2;
    }

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

    $teacherCount = count($teachers);
    
    $pdf->SetFont('Times', '', 9);
    $prevTeacherName = '';
    
    $withTeacherName = 40;
    
    for ($row = 0; $row < $teacherCount; $row++) {
        $teacher = $teachers[$row];
        
        $person = PersonQuery::create()->findPk($teacher['perID']);
        $teacherName = $person->getFullName();
        
        if ($teacherName != $prevTeacherName) {
            $pdf->WriteAt($nameX+$this->imageHeight/1.22-$pdf->GetStringWidth($teacherName)/2, $y+$this->imageHeight+2, $teacherName);
            $nameX += $withTeacherName;
                
            $imgName = str_replace(SystemURLs::getDocumentRoot(), "", $person->getPhotoURI());
                    
            $pdf->SetLineWidth(0.25);
            $pdf->Line($nameX-$this->imageHeight, $y, $nameX, $y);
            $pdf->Line($nameX-$this->imageHeight, $y+$this->imageHeight, $nameX, $y+$this->imageHeight);
            $pdf->Line($nameX-$this->imageHeight, $y, $nameX, $y);
            $pdf->Line($nameX-$this->imageHeight, $y, $nameX-$this->imageHeight, $y+$this->imageHeight);
            $pdf->Line($nameX, $y, $nameX, $y+$this->imageHeight);
        
            // we build the cross in the case of there's no photo
            //$this->SetLineWidth(0.25);
            $pdf->Line($nameX-$this->imageHeight, $y+$this->imageHeight, $nameX, $y);
            $pdf->Line($nameX-$this->imageHeight, $y, $nameX, $y+$this->imageHeight);
                
            if ($imgName != '   ' && strlen($imgName) > 5 && file_exists($_SERVER['DOCUMENT_ROOT'].$imgName)) {
                list($width, $height) = getimagesize($_SERVER['DOCUMENT_ROOT'].$img);
                $nw = $this->imageHeight;
                $nh = $this->imageHeight;
            
                $pdf->Image('https://'.$_SERVER['HTTP_HOST'].$imgName, $nameX-$nw, $y, $nw, $nh, 'PNG');
            }
                    
            $nameX -= $withTeacherName;

            $nameX+=$withTeacherName;
            $nameX=$nameX%($withTeacherName*5);
                    
            if ($nameX == 0) {
                $y+=$this->imageHeight*2;
            }
                        
            //echo $nameX." ".$y."<br>";
        }

        $prevTeacherName = $teacherName;

        if ($y > 250) {
            $pdf->AddPage();
            $y = 20;
        }
    }
    
    if ($nameX != 0) {
        $y+=$this->imageHeight*1.2;
    } else {
        $y-=$this->imageHeight/2;
    }


    $pdf->SetFont('Times', 'B', 12);
    $pdf->WriteAt($phoneX-7, $y+5, FormatDate(date('Y-m-d')));
}
*/
header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ClassList'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
