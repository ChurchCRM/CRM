<?php

/*******************************************************************************
 *
 *  filename    : /Include/CanvassUtilities.php
 *  last change : 2013-02-22
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2013 Michael Wilt
  *
 ******************************************************************************/

require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PdfCanvassBriefingReport;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

//Get the Fiscal Year ID out of the querystring
$iFYID = InputUtils::legacyFilterInput($_GET['FYID'], 'int');
$sWhichReport = InputUtils::legacyFilterInput($_GET['WhichReport']);

function TopPledgersLevel(string $iFYID, $iPercent)
{
    // Get pledges for this fiscal year, highest first
    $sSQL = 'SELECT plg_Amount FROM pledge_plg
			 WHERE plg_FYID = ' . $iFYID . ' AND plg_PledgeOrPayment="Pledge" ORDER BY plg_Amount DESC';
    $rsPledges = RunQuery($sSQL);
    $pledgeCount = mysqli_num_rows($rsPledges);
    mysqli_data_seek($rsPledges, $pledgeCount * $iPercent / 100);
    $aLastTop = mysqli_fetch_array($rsPledges);

    return $aLastTop['plg_Amount'];
}

require '../Include/CanvassUtilities.php';

function CanvassProgressReport(string $iFYID): void
{
    // Instantiate the directory class and build the report.
    $pdf = new PdfCanvassBriefingReport();

    $curY = 10;

    $pdf->SetFont('Times', '', 24);
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Canvass Progress Report') . ' ' . date(SystemConfig::getValue('sDateFormatLong')));
    $pdf->SetFont('Times', '', 14);

    $curY += 10;

    $pdf->SetFont('Times', '', 12);
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchName'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchAddress'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  ' . SystemConfig::getValue('sChurchZip'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchPhone') . '  ' . SystemConfig::getValue('sChurchEmail'));
    $curY += 10;
    $pdf->SetFont('Times', '', 14);

    $nameX = 20;
    $doneX = 70;
    $toDoX = 85;
    $percentX = 110;

    $pdf->SetFont('Times', 'B', 14);
    $pdf->writeAt($nameX, $curY, gettext('Name'));
    $pdf->writeAt($doneX, $curY, gettext('Done'));
    $pdf->writeAt($toDoX, $curY, gettext('Assigned'));
    $pdf->writeAt($percentX, $curY, gettext('Percent'));
    $pdf->SetFont('Times', '', 14);

    $curY += 6;

    $totalToDo = 0;
    $totalDone = 0;

    // Get all the canvassers
    $canvassGroups = ['Canvassers', 'BraveCanvassers'];
    foreach ($canvassGroups as $cgName) {
        $rsCanvassers = CanvassGetCanvassers(gettext($cgName));
        if ($rsCanvassers == 0) {
            continue;
        }

        while ($aCanvasser = mysqli_fetch_array($rsCanvassers)) {
            // Get all the families for this canvasser
            $sSQL = 'SELECT fam_ID from family_fam WHERE fam_Canvasser = ' . $aCanvasser['per_ID'];
            $rsCanvassees = RunQuery($sSQL);

            $thisCanvasserToDo = mysqli_num_rows($rsCanvassees);
            $thisCanvasserDone = 0;

            while ($aCanvassee = mysqli_fetch_array($rsCanvassees)) {
                // Get all the canvass input entered so far by this canvasser
                $sSQL = 'SELECT can_ID from canvassdata_can WHERE can_famID=' . $aCanvassee['fam_ID'] .
                            ' AND can_FYID=' . $iFYID;
                $rsCanvassData = RunQuery($sSQL);

                if (mysqli_num_rows($rsCanvassData) == 1) {
                    $thisCanvasserDone++;
                }
            }

            $totalToDo += $thisCanvasserToDo;
            $totalDone += $thisCanvasserDone;

            // Write the status output line for this canvasser
            $pdf->writeAt($nameX, $curY, $aCanvasser['per_FirstName'] . ' ' . $aCanvasser['per_LastName']);
            $pdf->writeAt($doneX, $curY, $thisCanvasserDone);
            $pdf->writeAt($toDoX, $curY, $thisCanvasserToDo);
            if ($thisCanvasserToDo > 0) {
                $percentStr = sprintf('%.0f%%', ($thisCanvasserDone / $thisCanvasserToDo) * 100);
            } else {
                $percentStr = 'N/A';
            }
            $pdf->writeAt($percentX, $curY, $percentStr);
            $curY += 6;
        }
    }

    // Summary status
    $pdf->SetFont('Times', 'B', 14);

    $pdf->writeAt($nameX, $curY, gettext('Total'));
    $pdf->writeAt($doneX, $curY, $totalDone);
    $pdf->writeAt($toDoX, $curY, $totalToDo);
    $percentStr = sprintf('%.0f%%', ($totalDone / $totalToDo) * 100);
    $pdf->writeAt($percentX, $curY, $percentStr);

    $pdf->Output('CanvassProgress' . date(SystemConfig::getValue('sDateFormatLong')) . '.pdf', 'D');
}

function CanvassBriefingSheets(string $iFYID): void
{
    // Instantiate the directory class and build the report.
    $pdf = new PdfCanvassBriefingReport();

    $aQuestions = file('CanvassQuestions.txt');
    $iNumQuestions = count($aQuestions);

    // Get all the families which need canvassing
    $sSQL = 'SELECT *, a.per_FirstName AS CanvasserFirstName, a.per_LastName AS CanvasserLastName FROM family_fam
	         LEFT JOIN person_per a ON fam_Canvasser = a.per_ID
			 WHERE fam_OkToCanvass="TRUE" AND fam_Canvasser>0 ORDER BY fam_Canvasser, fam_Name';
    $rsFamilies = RunQuery($sSQL);

    $topPledgeLevel = TopPledgersLevel($iFYID, 20); // mjw fix this- percentage should be a config option
    $canvasserX = 160;

    $topY = 20;
    $memberNameX = SystemConfig::getValue('leftX');
    $memberGenderX = $memberNameX + 30;
    $memberRoleX = $memberGenderX + 15;
    $memberAgeX = $memberRoleX + 30;
    $memberClassX = $memberAgeX + 20;
    $memberCellX = $memberClassX + 20;
    $memberEmailX = $memberCellX + 25;

    while ($aFamily = mysqli_fetch_array($rsFamilies)) {
        $curY = $topY;

        $pdf->SetFont('Times', '', 24);

        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $aFamily['fam_Name']);

        $pdf->SetFont('Times', '', 16);
        $pdf->printRightJustified($canvasserX, $curY, gettext('Canvasser') . ': ' . $aFamily['CanvasserFirstName'] . ' ' . $aFamily['CanvasserLastName']);

        $curY += 8;

        $pdf->SetFont('Times', '', 14);

        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $pdf->makeSalutation($aFamily['fam_ID']));
        $curY += 5;
        if ($aFamily['fam_Address1'] != '') {
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $aFamily['fam_Address1']);
            $curY += 5;
        }
        if ($aFamily['fam_Address2'] != '') {
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $aFamily['fam_Address2']);
            $curY += 5;
        }
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $aFamily['fam_City'] . ', ' . $aFamily['fam_State'] . '  ' . $aFamily['fam_Zip']);
        $curY += 5;
        if ($aFamily['fam_Country'] != '' && $aFamily['fam_Country'] != 'United States' && $aFamily['fam_Country'] != 'USA') {
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $aFamily['fam_Country']);
            $curY += 5;
        }
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $pdf->stripPhone($aFamily['fam_HomePhone']));
        $curY += 5;

        // Get pledges for this fiscal year, this family
        $sSQL = 'SELECT plg_Amount FROM pledge_plg
				 WHERE plg_FYID = ' . $iFYID . ' AND plg_PledgeOrPayment="Pledge" AND plg_FamID = ' . $aFamily['fam_ID'] . ' ORDER BY plg_Amount DESC';
        $rsPledges = RunQuery($sSQL);

        $pledgeCount = mysqli_num_rows($rsPledges);

        $sPledgeStatus = '';
        if ($pledgeCount == 0) {
            $sPledgeStatus .= gettext('Did not pledge');
        } else {
            $aPledge = mysqli_fetch_array($rsPledges);
            if ($aPledge['plg_Amount'] >= $topPledgeLevel) {
                $sPledgeStatus .= gettext('Top pledger');
            } else {
                $sPledgeStatus .= gettext('Pledged');
            }
        }

        $curY += SystemConfig::getValue('incrementY');

        $pdf->SetFont('Times', '', 12);
        $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Pledge status') . ': ');
        $pdf->SetFont('Times', 'B', 12);
        $pdf->writeAt(SystemConfig::getValue('leftX') + 25, $curY, $sPledgeStatus);
        $pdf->SetFont('Times', '', 12);

        $curY += 2 * SystemConfig::getValue('incrementY');

        //Get the family members for this family
        $sSQL = 'SELECT per_ID, per_Title, per_FirstName, per_LastName, per_Suffix, per_Gender,
				per_BirthMonth, per_BirthDay, per_BirthYear, per_Flags,
				per_HomePhone, per_WorkPhone, per_CellPhone, per_Email, per_WorkEmail,
				cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole
				FROM person_per
				LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
				WHERE per_fam_ID = ' . $aFamily['fam_ID'] . ' ORDER BY fmr.lst_OptionSequence';
        $rsFamilyMembers = RunQuery($sSQL);

        $pdf->SetFont('Times', 'B', 10);

        $pdf->writeAt($memberNameX, $curY, gettext('Name'));
        $pdf->writeAt($memberGenderX, $curY, gettext('M/F'));
        $pdf->writeAt($memberRoleX, $curY, gettext('Role'));
        $pdf->writeAt($memberAgeX, $curY, gettext('Age'));
        $pdf->writeAt($memberClassX, $curY, gettext('Member'));
        $pdf->writeAt($memberCellX, $curY, gettext('Cell Phone'));
        $pdf->writeAt($memberEmailX, $curY, gettext('Email'));
        $curY += SystemConfig::getValue('incrementY');

        $pdf->SetFont('Times', '', 10);

        while ($aFamilyMember = mysqli_fetch_array($rsFamilyMembers)) {
            if ($aFamilyMember['per_Gender'] == 1) {
                $sGender = 'M';
            } else {
                $sGender = 'F';
            }
            $sAge = MiscUtils::formatAge($aFamilyMember['per_BirthMonth'], $aFamilyMember['per_BirthDay'], $aFamilyMember['per_BirthYear']);
            $pdf->writeAt($memberNameX, $curY, $aFamilyMember['per_FirstName'] . ' ' . $aFamilyMember['per_LastName']);
            $pdf->writeAt($memberGenderX, $curY, $sGender);
            $pdf->writeAt($memberRoleX, $curY, $aFamilyMember['sFamRole']);
            $pdf->writeAt($memberAgeX, $curY, $sAge);
            $pdf->writeAt($memberClassX, $curY, $aFamilyMember['sClassName']);
            $pdf->writeAt($memberCellX, $curY, $pdf->stripPhone($aFamilyMember['per_CellPhone']));
            $pdf->writeAt($memberEmailX, $curY, $aFamilyMember['per_Email']);
            $curY += SystemConfig::getValue('incrementY');
        }

        // Go back around to get group affiliations
        if (mysqli_num_rows($rsFamilyMembers) > 0) {
            mysqli_data_seek($rsFamilyMembers, 0);
            while ($aMember = mysqli_fetch_array($rsFamilyMembers)) {
                // Get the Groups this Person is assigned to
                $sSQL = 'SELECT grp_Name, role.lst_OptionName AS roleName
						FROM group_grp
						LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
						LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
						WHERE person2group2role_p2g2r.p2g2r_per_ID = ' . $aMember['per_ID'] . '
						ORDER BY grp_Name';
                $rsAssignedGroups = RunQuery($sSQL);
                if (mysqli_num_rows($rsAssignedGroups) > 0) {
                    $groupStr = 'Assigned groups for ' . $aMember['per_FirstName'] . ' ' . $aMember['per_LastName'] . ': ';

                    $countGroups = 0;
                    while ($aGroup = mysqli_fetch_array($rsAssignedGroups)) {
                        $groupStr .= $aGroup['grp_Name'] . ' (' . $aGroup['roleName'] . ') ';
                        if ($countGroups == 0) {
                            $curY += SystemConfig::getValue('incrementY');
                        }

                        if (++$countGroups >= 2) {
                            $countGroups = 0;
                            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $groupStr);
                            $groupStr = '        ';
                        }
                    }
                    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $groupStr);
                }
            }
        }
        $curY += 2 * SystemConfig::getValue('incrementY');
        $spaceLeft = 275 - $curY;
        $spacePerQuestion = $spaceLeft / $iNumQuestions;
        for ($i = 0; $i < $iNumQuestions; $i++) {
            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, ($i + 1) . '. ' . $aQuestions[$i]);
            $curY += $spacePerQuestion;
        }

        $pdf->addPage();
    }

    $pdf->Output('CanvassBriefing' . date(SystemConfig::getValue('sDateFormatLong')) . '.pdf', 'D');
}

function CanvassSummaryReport(string $iFYID): void
{
    // Instantiate the directory class and build the report.
    $pdf = new PdfCanvassBriefingReport();

    $pdf->SetMargins(20, 20);

    $curY = 10;

    $pdf->SetFont('Times', '', 24);

    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Canvass Summary Report') . ' ' . date(SystemConfig::getValue('sDateFormatLong')));

    $pdf->SetFont('Times', '', 14);

    $curY += 10;

    $pdf->SetFont('Times', '', 12);
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchName'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchAddress'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  ' . SystemConfig::getValue('sChurchZip'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchPhone') . '  ' . SystemConfig::getValue('sChurchEmail'));
    $curY += 10;
    $pdf->SetFont('Times', '', 14);

    $pdf->SetAutoPageBreak(1);

    $pdf->Write(5, "\n\n");

    $sSQL = 'SELECT * FROM canvassdata_can WHERE can_FYID=' . $iFYID;
    $rsCanvassData = RunQuery($sSQL);

    foreach ([gettext('Positive'), gettext('Critical'), gettext('Insightful'), gettext('Financial'), gettext('Suggestion'), gettext('WhyNotInterested')] as $colName) {
        $pdf->SetFont('Times', 'B', 14);

        $pdf->Write(5, $colName . ' ' . gettext('Comments') . "\n");
        //      $pdf->WriteAt (SystemConfig::getValue("leftX"), $curY, $colName . " Comments");
        $pdf->SetFont('Times', '', 12);
        while ($aDatum = mysqli_fetch_array($rsCanvassData)) {
            $str = $aDatum['can_' . $colName];
            if ($str != '') {
                $pdf->Write(4, $str . "\n\n");
                //              $pdf->WriteAt (SystemConfig::getValue("leftX"), $curY, $str);
                //              $curY += SystemConfig::getValue("incrementY");
            }
        }
        mysqli_data_seek($rsCanvassData, 0);
    }

    $pdf->Output('CanvassSummary' . date(SystemConfig::getValue('sDateFormatLong')) . '.pdf', 'D');
}

function CanvassNotInterestedReport(string $iFYID): void
{
    // Instantiate the directory class and build the report.
    $pdf = new PdfCanvassBriefingReport();

    $pdf->SetMargins(20, 20);

    $curY = 10;

    $pdf->SetFont('Times', '', 24);
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, gettext('Canvass Not Interested Report') . ' ' . date(SystemConfig::getValue('sDateFormatLong')));
    $pdf->SetFont('Times', '', 14);

    $curY += 10;

    $pdf->SetFont('Times', '', 12);
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchName'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchAddress'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchCity') . ', ' . SystemConfig::getValue('sChurchState') . '  ' . SystemConfig::getValue('sChurchZip'));
    $curY += SystemConfig::getValue('incrementY');
    $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sChurchPhone') . '  ' . SystemConfig::getValue('sChurchEmail'));
    $curY += 10;
    $pdf->SetFont('Times', '', 14);

    $pdf->SetAutoPageBreak(1);

    $pdf->Write(5, "\n\n");

    $sSQL = 'SELECT *,a.fam_Name FROM canvassdata_can LEFT JOIN family_fam a ON fam_ID=can_famID WHERE can_FYID=' . $iFYID . ' AND can_NotInterested=1';
    $rsCanvassData = RunQuery($sSQL);

    $pdf->SetFont('Times', '', 12);
    while ($aDatum = mysqli_fetch_array($rsCanvassData)) {
        $str = sprintf("%s : %s\n", $aDatum['fam_Name'], $aDatum['can_WhyNotInterested']);
        $pdf->Write(4, $str . "\n\n");
    }

    header('Pragma: public');  // Needed for IE when using a shared SSL certificate
    $pdf->Output('CanvassNotInterested' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
}

if ($sWhichReport === 'Briefing') {
    CanvassBriefingSheets($iFYID);
}

if ($sWhichReport === 'Progress') {
    CanvassProgressReport($iFYID);
}

if ($sWhichReport === 'Summary') {
    CanvassSummaryReport($iFYID);
}

if ($sWhichReport === 'NotInterested') {
    CanvassNotInterestedReport($iFYID);
}
