<?php

/*******************************************************************************
*
*  filename    : Reports/ClassAttendance.php
*  last change : 2013-02-22
*  description : Creates a PDF for a Sunday School Class Attendance List
*  Udpdated    : 2017-10-23
*                Philippe Logel
******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Base\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\Reports\PdfAttendance;
use ChurchCRM\Utils\InputUtils;

$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID']);
$aGrp = explode(',', $iGroupID);
$nGrps = count($aGrp);
//echo $iGroupID;

$iFYID = InputUtils::legacyFilterInput($_GET['FYID'], 'int');

$tFirstSunday = InputUtils::legacyFilterInput($_GET['FirstSunday']);
$tLastSunday = InputUtils::legacyFilterInput($_GET['LastSunday']);
$tAllRoles = InputUtils::legacyFilterInput($_GET['AllRoles'], 'int');
$withPictures = InputUtils::legacyFilterInput($_GET['withPictures'], 'int');

//echo "all roles ={$tAllRoles}";

$tNoSchool1 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool1');
$tNoSchool2 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool2');
$tNoSchool3 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool3');
$tNoSchool4 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool4');
$tNoSchool5 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool5');
$tNoSchool6 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool6');
$tNoSchool7 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool7');
$tNoSchool8 = InputUtils::legacyFilterInputArr($_GET, 'NoSchool8');

$iExtraStudents = InputUtils::legacyFilterInputArr($_GET, 'ExtraStudents', 'int');
$iExtraTeachers = InputUtils::legacyFilterInputArr($_GET, 'ExtraTeachers', 'int');

$dFirstSunday = strtotime($tFirstSunday);
$dLastSunday = strtotime($tLastSunday);

$dNoSchool1 = strtotime($tNoSchool1);
$dNoSchool2 = strtotime($tNoSchool2);
$dNoSchool3 = strtotime($tNoSchool3);
$dNoSchool4 = strtotime($tNoSchool4);
$dNoSchool5 = strtotime($tNoSchool5);
$dNoSchool6 = strtotime($tNoSchool6);
$dNoSchool7 = strtotime($tNoSchool7);
$dNoSchool8 = strtotime($tNoSchool8);

// Reformat the dates to get standardized text representation
$tFirstSunday = date('Y-m-d', $dFirstSunday);
$tLastSunday = date('Y-m-d', $dLastSunday);

$tNoSchool1 = date('Y-m-d', $dNoSchool1);
$tNoSchool2 = date('Y-m-d', $dNoSchool2);
$tNoSchool3 = date('Y-m-d', $dNoSchool3);
$tNoSchool4 = date('Y-m-d', $dNoSchool4);
$tNoSchool5 = date('Y-m-d', $dNoSchool5);
$tNoSchool6 = date('Y-m-d', $dNoSchool6);
$tNoSchool7 = date('Y-m-d', $dNoSchool7);
$tNoSchool8 = date('Y-m-d', $dNoSchool8);

// Instantiate the class and build the report.
$yTitle = 20;
$yTeachers = $yTitle + 6;
$nameX = 10;
$epd = 3;

$pdf = new PdfAttendance();

for ($i = 0; $i < $nGrps; $i++) {
    $iGroupID = $aGrp[$i];
    //  uset($aStudents);
    if ($i > 0) {
        $pdf->addPage();
    }
    //Get the data on this group
    $group = GroupQuery::Create()->findOneById($iGroupID);

    $FYString = MakeFYString($iFYID);

    $reportHeader = str_pad($group->getName(), 95) . $FYString;

    // Build the teacher string- first teachers, then the liaison
    $teacherString = gettext('Teachers') . ': ';
    $bFirstTeacher = true;
    $iTeacherCnt = 0;
    $iMaxTeachersFit = 4;
    $iStudentCnt = 0;

    $groupRoleMemberships = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->orderBy(PersonTableMap::COL_PER_LASTNAME)
            ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME) // I've try to reproduce per_LastName, per_FirstName
            ->findByGroupId($iGroupID);

    if ($tAllRoles != 1) {
        $liaisonString = '';

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
                $aTeachers[$iTeacherCnt] = $person; // Make an array of teachers while we're here
                if (!$bFirstTeacher) {
                    $teacherString .= ', ';
                }
                $teacherString .= $person->getFullName();
                $bFirstTeacher = false;

                $person->getPhoto()->createThumbnail();
                $aTeachersIMG[$iTeacherCnt++] = str_replace(SystemURLs::getDocumentRoot(), '', $person->getPhoto()->getThumbnailURI());
            } elseif ($lst_OptionName === 'Student') {
                $aStudents[$iStudentCnt] = $person;

                $person->getPhoto()->createThumbnail();
                $aStudentsIMG[$iStudentCnt++] = $person->getPhoto()->getThumbnailURI();
            } elseif ($lst_OptionName == gettext('Liaison')) {
                $liaisonString .= gettext('Liaison') . ':' . $person->getFullName() . ' ' . $pdf->stripPhone($homePhone) . ' ';
            }
        }

        if ($iTeacherCnt < $iMaxTeachersFit) {
            $teacherString .= '  ' . $liaisonString;
        }

        $pdf->SetFont('Times', 'B', 12);

        $y = $yTeachers;
        $pdf->writeAt($nameX, $y, $teacherString);
        $y += 4;

        if ($iTeacherCnt >= $iMaxTeachersFit) {
            $pdf->writeAt($nameX, $y, $liaisonString);
            $y += 4;
        }

        $y = $pdf->drawAttendanceCalendar(
            $nameX,
            $y + 6,
            $aStudents,
            gettext('Students'),
            $iExtraStudents,
            $tFirstSunday,
            $tLastSunday,
            $tNoSchool1,
            $tNoSchool2,
            $tNoSchool3,
            $tNoSchool4,
            $tNoSchool5,
            $tNoSchool6,
            $tNoSchool7,
            $tNoSchool8,
            $reportHeader,
            $aStudentsIMG,
            $withPictures
        );

        // we start a new page
        if ($y > $yTeachers + 10) {
            $pdf->addPage();
        }

        $y = $yTeachers;
        $pdf->drawAttendanceCalendar(
            $nameX,
            $y + 6,
            $aTeachers,
            gettext('Teachers'),
            $iExtraTeachers,
            $tFirstSunday,
            $tLastSunday,
            $tNoSchool1,
            $tNoSchool2,
            $tNoSchool3,
            $tNoSchool4,
            $tNoSchool5,
            $tNoSchool6,
            $tNoSchool7,
            $tNoSchool8,
            '',
            $aTeachersIMG,
            $withPictures
        );
    } else {
        //
        // print all roles on the attendance sheet
        //
        $iStudentCnt = 0;

        unset($aStudents);

        foreach ($groupRoleMemberships as $groupRoleMembership) {
            $person = $groupRoleMembership->getPerson();

            $aStudents[$iStudentCnt] = $groupRoleMembership->getPerson();
            $aStudentsIMG[$iStudentCnt++] = $person->getPhoto()->getThumbnailURI();
        }

        $pdf->SetFont('Times', 'B', 12);

        $y = $yTeachers;

        $y = $pdf->drawAttendanceCalendar(
            $nameX,
            $y + 6,
            $aStudents,
            gettext('All Members'),
            $iExtraStudents + $iExtraTeachers,
            $tFirstSunday,
            $tLastSunday,
            $tNoSchool1,
            $tNoSchool2,
            $tNoSchool3,
            $tNoSchool4,
            $tNoSchool5,
            $tNoSchool6,
            $tNoSchool7,
            $tNoSchool8,
            $reportHeader,
            $aStudentsIMG,
            $withPictures
        );
    }
}

header('Pragma: public');  // Needed for IE when using a shared SSL certificate
if ($iPDFOutputType == 1) {
    $pdf->Output('ClassAttendance' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
} else {
    $pdf->Output();
}
