<?php
/*******************************************************************************
*
*  filename    : Reports/ClassList.php
*  last change : 2017-10-29 Philippe Logel
*  description : Creates a PDF for a Sunday School Class List

******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';
require '../Include/ReportFunctions.php';
require '../Include/GetGroupArray.php';

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

    $pdf->WriteAt($nameX, $yTitle, ($group->getName().' - '));

    $FYString = MakeFYString($iFYID);
    $pdf->WriteAt($phoneX, $yTitle, $FYString);

    $pdf->SetLineWidth(0.5);
    $pdf->Line($nameX, $yTeachers - 0.75, 195, $yTeachers - 0.75);

    $ga = GetGroupArray($iGroupID);
    $numMembers = count($ga);

    $teacherString1 = '';
    $teacherString2 = '';
    $teacherCount = 0;
    $teachersThatFit = 4;

    $bFirstTeacher1 = true;
    $bFirstTeacher2 = true;

    $groupRoleMemberships = ChurchCRM\Person2group2roleP2g2rQuery::create()
                            ->joinWithPerson()
                            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME) // I've try to reproduce per_LastName, per_FirstName
                            ->findByGroupId($iGroupID);

    $students = [];

    foreach ($groupRoleMemberships as $groupRoleMembership) {
        $perID = $groupRoleMembership->getPersonId();
        $person = PersonQuery::create()->findPk($perID);
        
        $firstName = $person->getFirstName();
        $middlename = $person->getMiddleName();
        $lastname = $person->getLastName();
        $family = $person->getFamily();
            
        $homePhone = "";
        if (!empty($family)) {
            $homePhone = $family->getHomePhone();
        
        
            if (strlen($homePhone) == 0) {
                $homePhone = $family->getCellPhone();
            }
            
            if (strlen($homePhone) == 0) {
                $homePhone = $family->getWorkPhone();
            }
        }

        $groupRole = ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($groupRoleMembership->getRoleId())->findOne();
                
        $lst_OptionName = $groupRole->getOptionName();
        
        if ($lst_OptionName == 'Teacher') {
            $phone = $pdf->StripPhone($homePhone);
            if ($teacherCount >= $teachersThatFit) {
                if (!$bFirstTeacher2) {
                    $teacherString2 .= ', ';
                }
                $teacherString2 .= $firstName.' '.$middlename.' '.$lastname.' '.$phone;
                $bFirstTeacher2 = false;
            } else {
                if (!$bFirstTeacher1) {
                    $teacherString1 .= ', ';
                }
                $teacherString1 .= $firstName.' '.$middlename.' '.$lastname.' '.$phone;
                $bFirstTeacher1 = false;
            }
            ++$teacherCount;
        } elseif ($lst_OptionName == gettext('Liaison')) {
            $liaisonString .= gettext('Liaison').':'.$firstName.' '.$middlename.' '.$lastname.' '.$phone.' ';
        } elseif ($lst_OptionName == 'Student') {
            $elt = ['perID' => $perID,
                                     'firstName' => $firstName,
                                     'middlename' => $middlename,
                                     'lastname' => $lastname,
                                     'homePhone' => $homePhone];
                                 
            array_push($students, $elt);
        }
    }


    $pdf->SetFont('Times', 'B', 10);

    $y = $yTeachers;

    $pdf->WriteAt($nameX, $y, $teacherString1);
    $y += $yIncrement;

    if ($teacherCount > $teachersThatFit) {
        $pdf->WriteAt($nameX, $y, $teacherString2);
        $y += $yIncrement;
    }

    $pdf->WriteAt($nameX, $y, $liaisonString);
    $y += $yOffsetStartStudents;

    $pdf->SetFont('Times', '', 12);
    $prevStudentName = '';

    $numMembers = count($students);

    for ($row = 0; $row < $numMembers; $row++) {
        $student = $students[$row];
        
        $person = PersonQuery::create()->findPk($student['perID']);
        
        $firstName = $person->getFirstName();
        $middlename = $person->getMiddleName();
        $lastname = $person->getLastName();
        
        $assignedProperties = $person->getProperties();
        
        $family = $person->getFamily();

        $studentName = ($lastname.','.$$middlename.' '.$firstName);
        
        if ($studentName != $prevStudentName) {
            $pdf->WriteAt($nameX, $y, $studentName);
                
            $imgName = str_replace(SystemURLs::getDocumentRoot(), "", $person->getThumbnailURI());//'/Images/Person/'.$per_ID.'.png';
                
            //echo $imgName."<br>";
                
            $birthdayStr = change_date_for_place_holder($person->getBirthYear().'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
            $pdf->WriteAt($birthdayX, $y, $birthdayStr);

            if ($withPictures) {
                $imageHeight=9;
                    
                $nameX-=2;
                $y-=2;
                                        
                $pdf->SetLineWidth(0.25);
                $pdf->Line($nameX-$imageHeight, $y, $nameX, $y);
                $pdf->Line($nameX-$imageHeight, $y+$imageHeight, $nameX, $y+$imageHeight);
                $pdf->Line($nameX-$imageHeight, $y, $nameX, $y);
                $pdf->Line($nameX-$imageHeight, $y, $nameX-$imageHeight, $y+$imageHeight);
                $pdf->Line($nameX, $y, $nameX, $y+$imageHeight);
            
                // we build the cross in the case of there's no photo
                //$this->SetLineWidth(0.25);
                $pdf->Line($nameX-$imageHeight, $y+$imageHeight, $nameX, $y);
                $pdf->Line($nameX-$imageHeight, $y, $nameX, $y+$imageHeight);
                    
                if ($imgName != '   ' && strlen($imgName) > 5 && file_exists($_SERVER['DOCUMENT_ROOT'].$imgName)) {
                    list($width, $height) = getimagesize($_SERVER['DOCUMENT_ROOT'].$img);
                    $factor = 8/$height;
                    $nw = $imageHeight;
                    $nh = $imageHeight;
                
                    $pdf->Image('https://'.$_SERVER['HTTP_HOST'].$imgName, $nameX-$nw, $y, $nw, $nh, 'PNG');
                }
                    
                $nameX+=2;
                $y+=2;
            }
                
            $props = "";
            foreach ($assignedProperties as $property) {
                $props.= $property->getProName().", ";
            }
                
            if (strlen($props) > 1) {
                $props = " !!! ".$props;
                    
                $pdf->SetFont('Times', 'B', 10);
                $pdf->WriteAt($nameX, $y+3.5, $props);
                $pdf->SetFont('Times', '', 12);
            }
        }
        
        $parentsStr = $phone = "";
        if (!empty($family)) {
            $parentsStr = $pdf->MakeSalutation($family->getId());
        
            $phone = $family->getHomePhone();
        
            if (strlen($phone) == 0) {
                $phone = $family->getCellPhone();
            }
            
            if (strlen($phone) == 0) {
                $phone = $family->getWorkPhone();
            }
        }

        $pdf->WriteAt($parentsX, $y, $parentsStr);
        
        $pdf->WriteAt($phoneX, $y, $pdf->StripPhone($phone));
        $y += $yIncrement;

        $addrStr = "";
        if (!empty($family)) {
            $addrStr = $family->getAddress1();
            if ($fam_Address2 != '') {
                $addrStr .= ' '.$family->getAddress2();
            }
            $addrStr .= ', '.$family->getCity().', '.$family->getState().'  '.$family->getZip();
        }
        $pdf->WriteAt($parentsX, $y, $addrStr);

        $prevStudentName = $studentName;
        $y += 1.5 * $yIncrement;

        if ($y > 250) {
            $pdf->AddPage();
            $y = 20;
        }
    }

    $pdf->SetFont('Times', 'B', 12);
    $pdf->WriteAt($phoneX-7, $y+5, FormatDate(date('Y-m-d')));
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ClassList'.date(SystemConfig::getValue("sDateFilenameFormat")).'.pdf', 'D');
} else {
    $pdf->Output();
}
