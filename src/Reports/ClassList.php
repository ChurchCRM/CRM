<?php

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Base\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;

$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID']);
$aGrp = explode(',', $iGroupID);
$nGrps = count($aGrp);

$iFYID = InputUtils::legacyFilterInput($_GET['FYID'], 'int');
$dFirstSunday = InputUtils::legacyFilterInput($_GET['FirstSunday']);
$dLastSunday = InputUtils::legacyFilterInput($_GET['LastSunday']);
$withPictures = InputUtils::legacyFilterInput($_GET['pictures']);

class PdfClassList extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);

        $this->SetMargins(0, 0);

        $this->SetFont('Times', '', 14);
        $this->SetAutoPageBreak(false);
        $this->addPage();
    }
}

// Instantiate the directory class and build the report.
$pdf = new PdfClassList();

for ($i = 0; $i < $nGrps; $i++) {
    $iGroupID = $aGrp[$i];

    if ($i > 0) {
        $pdf->addPage();
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

    $pdf->writeAt($nameX, $yTitle, $group->getName() . ' - ');

    $FYString = MakeFYString($iFYID);
    $pdf->writeAt($phoneX, $yTitle, $FYString);

    $pdf->SetLineWidth(0.5);
    $pdf->Line($nameX, $yTeachers - 0.75, 195, $yTeachers - 0.75);

    $teacherString1 = '';
    $teacherString2 = '';
    $teacherCount = 0;
    $teachersThatFit = 4;

    $bFirstTeacher1 = true;
    $bFirstTeacher2 = true;

    $groupRoleMemberships = Person2group2roleP2g2rQuery::create()
                            ->joinWithPerson()
                            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME)
                            ->findByGroupId($iGroupID);

    $students = [];

    foreach ($groupRoleMemberships as $groupRoleMembership) {
        $person = $groupRoleMembership->getPerson();
        $family = $person->getFamily();

        $homePhone = '';
        if (!empty($family)) {
            $homePhone = $family->getHomePhone();

            if (empty($homePhone)) {
                $homePhone = $family->getCellPhone();
            }

            if (empty($homePhone)) {
                $homePhone = $family->getWorkPhone();
            }
        }

        $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($groupRoleMembership->getRoleId())->findOne();
        $lst_OptionName = $groupRole->getOptionName();

        if ($lst_OptionName === 'Teacher') {
            $phone = $pdf->stripPhone($homePhone);
            if ($teacherCount >= $teachersThatFit) {
                if (!$bFirstTeacher2) {
                    $teacherString2 .= ', ';
                }
                $teacherString2 .= $person->getFullName() . ' ' . $phone;
                $bFirstTeacher2 = false;
            } else {
                if (!$bFirstTeacher1) {
                    $teacherString1 .= ', ';
                }
                $teacherString1 .= $person->getFullName() . ' ' . $phone;
                $bFirstTeacher1 = false;
            }
            $teacherCount++;
        } elseif ($lst_OptionName == gettext('Liaison')) {
            $liaisonString .= gettext('Liaison') . ':' . $person->getFullName() . ' ' . $phone . ' ';
        } elseif ($lst_OptionName === 'Student') {
            $elt = ['perID' => $groupRoleMembership->getPersonId()];

            $students[] = $elt;
        }
    }

    $pdf->SetFont('Times', 'B', 10);

    $y = $yTeachers;

    $pdf->writeAt($nameX, $y, $teacherString1);
    $y += $yIncrement;

    if ($teacherCount > $teachersThatFit) {
        $pdf->writeAt($nameX, $y, $teacherString2);
        $y += $yIncrement;
    }

    $pdf->writeAt($nameX, $y, $liaisonString);
    $y += $yOffsetStartStudents;

    $pdf->SetFont('Times', '', 12);
    $prevStudentName = '';

    $numMembers = count($students);

    for ($row = 0; $row < $numMembers; $row++) {
        $student = $students[$row];

        $person = PersonQuery::create()->findPk($student['perID']);

        $assignedProperties = $person->getProperties();

        $family = $person->getFamily();

        $studentName = $person->getFullName();

        if ($studentName != $prevStudentName) {
            $pdf->writeAt($nameX, $y, $studentName);

            $imgName = $person->getPhoto()->getThumbnailURI();

            $birthdayStr = change_date_for_place_holder($person->getBirthYear() . '-' . $person->getBirthMonth() . '-' . $person->getBirthDay());
            $pdf->writeAt($birthdayX, $y, $birthdayStr);

            if ($withPictures) {
                $imageHeight = 9;

                $nameX -= 2;
                $y -= 2;

                $pdf->SetLineWidth(0.25);
                $pdf->Line($nameX - $imageHeight, $y, $nameX, $y);
                $pdf->Line($nameX - $imageHeight, $y + $imageHeight, $nameX, $y + $imageHeight);
                $pdf->Line($nameX - $imageHeight, $y, $nameX, $y);
                $pdf->Line($nameX - $imageHeight, $y, $nameX - $imageHeight, $y + $imageHeight);
                $pdf->Line($nameX, $y, $nameX, $y + $imageHeight);

                // We build the cross in the case of there's no photo
                $pdf->Line($nameX - $imageHeight, $y + $imageHeight, $nameX, $y);
                $pdf->Line($nameX - $imageHeight, $y, $nameX, $y + $imageHeight);

                if ($imgName != '   ' && strlen($imgName) > 5 && file_exists($imgName)) {
                    [$width, $height] = getimagesize($imgName);
                    $factor = 8 / $height;
                    $nw = $imageHeight;
                    $nh = $imageHeight;

                    $pdf->Image($imgName, $nameX - $nw, $y, $nw, $nh, 'JPG');
                }

                $nameX += 2;
                $y += 2;
            }

            $props = '';
            if (!empty($assignedProperties)) {
                foreach ($assignedProperties as $property) {
                    $props .= $property->getProName() . ', ';
                }

                $props = chop($props, ', ');

                if (strlen($props) > 0) {
                    $props = ' !!! ' . $props;

                    $pdf->SetFont('Times', 'B', 10);
                    $pdf->writeAt($nameX, $y + 3.5, $props);
                    $pdf->SetFont('Times', '', 12);
                }
            }
        }

        $parentsStr = $phone = '';
        if (!empty($family)) {
            $parentsStr = $pdf->makeSalutation($family->getId());

            $phone = $family->getHomePhone();

            if (empty($phone)) {
                $phone = $family->getCellPhone();
            }

            if (empty($phone)) {
                $phone = $family->getWorkPhone();
            }
        }

        $pdf->writeAt($parentsX, $y, $parentsStr);

        $pdf->writeAt($phoneX, $y, $pdf->stripPhone($phone));
        $y += $yIncrement;

        $addrStr = '';
        if (!empty($family)) {
            $addrStr = $family->getAddress1();
            if ($fam_Address2 != '') {
                $addrStr .= ' ' . $family->getAddress2();
            }
            $addrStr .= ', ' . $family->getCity() . ', ' . $family->getState() . '  ' . $family->getZip();
        }
        $pdf->writeAt($parentsX, $y, $addrStr);

        $prevStudentName = $studentName;
        $y += 1.5 * $yIncrement;

        if ($y > 250) {
            $pdf->addPage();
            $y = 20;
        }
    }

    $pdf->SetFont('Times', 'B', 12);
    $pdf->writeAt($phoneX - 7, $y + 5, FormatDate(date('Y-m-d')));
}

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output('ClassList' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
