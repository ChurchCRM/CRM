<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

class PdfConfirmReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(10, 20);

        $this->SetAutoPageBreak(false);
    }

    public function startNewPage(Family $family): float
    {
        $curY = $this->startLetterPage(
            $family->getId(),
            $family->getName(),
            $family->getAddress1(),
            $family->getAddress2(),
            $family->getCity(),
            $family->getState(),
            $family->getZip(),
            $family->getCountry(),
            'graphic'
        );

        $curY += SystemConfig::getValue('incrementY');
        $blurb = SystemConfig::getValue('sConfirm1');

        $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);

        return $curY + SystemConfig::getValue('incrementY');
    }

    public function finishPage($curY): void
    {
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm2'));

        $curY += 3 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm3'));
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm4'));

        if (!empty(SystemConfig::getValue('sConfirm5'))) {
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm5'));
            $curY += 2 * SystemConfig::getValue('incrementY');
        }
        if (!empty(SystemConfig::getValue('sConfirm6'))) {
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm6'));
        }
        //If the Reports Settings Menu's SystemConfig::getValue("sConfirmSigner") is set, then display the closing statement.  Hide it otherwise.
        if (SystemConfig::getValue('sConfirmSigner')) {
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSigner'));
        }
    }
}

// Instantiate the directory class and build the report.
$pdf = new PdfConfirmReport();
$filename = 'ConfirmReport' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';

// Get the list of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

if ($numCustomFields > 0) {
    $iFieldNum = 0;
    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_ASSOC)) {
        extract($rowCustomField);
        $sCustomFieldName[$iFieldNum] = $custom_Name;
        $iFieldNum += 1;
    }
}

$iFamilyID = null;
$familyQuery = FamilyQuery::create()->orderByName();

if ($_GET['familyId']) {
    $iFamilyID = (int) InputUtils::legacyFilterInput($_GET['familyId'], 'int');
    $families = $familyQuery->findById($iFamilyID);
} else {
    $families = $familyQuery->find();
}

    $leftX = SystemConfig::getValue('leftX');
    $rightMargin = 200;
    $lineHeight = 5.5;

    // Loop through families
    foreach ($families as $family) {
        //If this is a report for a single family, name the file accordingly.
        if ($iFamilyID) {
            $filename = 'ConfirmReport-' . $family->getName() . '.pdf';
        }

        $curY = $pdf->startNewPage($family);
        $curY += 7;

        // Helper function to add a labeled field with wrapping support
        $addField = function ($label, $value, $x, $y, $width = 85) use ($pdf) {
            $pdf->SetFont('Times', 'B', 10);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, $label . ':', 0, 1, 'L');
            
            // Data value with wrapping
            $pdf->SetFont('Times', '', 10);
            $pdf->SetXY($x + 2, $pdf->GetY());
            $pdf->MultiCell($width - 4, 4, $value, 0, 'L');
            
            return $pdf->GetY();
        };

        // Family information section - 2 column layout
        $col1X = $leftX;
        $col2X = $leftX + 95;
        $fieldY = $curY;
        $col1Y = $fieldY;
        $col2Y = $fieldY;
        $maxY = $fieldY;

        // Column 1
        $col1Y = $addField(gettext('Family Name'), $family->getName(), $col1X, $col1Y, 85) + 1;
        
        // Combined address field
        $address = $family->getAddress1();
        if (!empty($family->getAddress2())) {
            $address .= ', ' . $family->getAddress2();
        }
        $address .= ', ' . $family->getCity() . ', ' . $family->getState() . '  ' . $family->getZip();
        $col1Y = $addField(gettext('Address'), $address, $col1X, $col1Y, 85) + 1;

        // Column 2
        $col2Y = $addField(gettext('Home Phone'), $family->getHomePhone(), $col2X, $col2Y, 85) + 1;
        $col2Y = $addField(gettext('Send Newsletter'), $family->getSendNewsletter(), $col2X, $col2Y, 85) + 1;
        if ($family->getWeddingdate()) {
            $col2Y = $addField(gettext('Anniversary Date'), $family->getWeddingdate(SystemConfig::getValue('sDateFormatLong')), $col2X, $col2Y, 85) + 1;
        }
        $col2Y = $addField(gettext('Family Email'), $family->getEmail(), $col2X, $col2Y, 85) + 1;

        // Move cursor to after both columns
        $curY = max($col1Y, $col2Y) + 1;
        
        $sSQL = 'SELECT *, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole FROM person_per
                LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
                LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
                WHERE per_fam_ID = ' . $family->getId() . ' ORDER BY per_fmr_ID';
        $rsFamilyMembers = RunQuery($sSQL);
        $numFamilyMembers = 0;

        // Check if we have family members to display
        if (mysqli_num_rows($rsFamilyMembers) > 0) {
            // Family members section header
            $curY += 1;
            $pdf->SetFont('Times', 'B', 11);
            $pdf->SetXY($leftX, $curY);
            $pdf->Cell(0, 5, gettext('Family Members'), 0, 1, 'L');
            $curY = $pdf->GetY() + 2;

            // Table header
            $pdf->SetFont('Times', 'B', 8.5);
            $pdf->SetXY($leftX, $curY);
            $pdf->Cell(40, $lineHeight + 0.5, gettext('Name'), 0, 0, 'L');
            $pdf->Cell(8, $lineHeight + 0.5, gettext('M/F'), 0, 0, 'C');
            $pdf->Cell(15, $lineHeight + 0.5, gettext('Role'), 0, 0, 'L');
            $pdf->Cell(30, $lineHeight + 0.5, gettext('Email'), 0, 0, 'L');
            $pdf->Cell(18, $lineHeight + 0.5, gettext('Birthday'), 0, 0, 'L');
            $pdf->Cell(8, $lineHeight + 0.5, gettext('Age'), 0, 0, 'C');
            $pdf->Cell(28, $lineHeight + 0.5, gettext('Cell Phone'), 0, 1, 'L');
            $curY = $pdf->GetY();
            
            // Divider line
            $pdf->SetDrawColor(100, 100, 100);
            $pdf->Line($leftX, $curY, 195, $curY);
            $pdf->SetDrawColor(0, 0, 0);
            $curY += 1.5;

            // Reset family members query
            mysqli_data_seek($rsFamilyMembers, 0);
            $numFamilyMembers = 0;
            
            while ($aMember = mysqli_fetch_array($rsFamilyMembers)) {
                $numFamilyMembers++;
                extract($aMember);
                
                // Check if we need a new page
                if ($curY > 255) {
                    $curY = $pdf->startLetterPage($family->getId(), $family->getName(), $family->getAddress1(), $family->getAddress2(), $family->getCity(), $family->getState(), $family->getZip(), $family->getCountry());
                    $curY += 3;
                    
                    // Redraw table header on new page
                    $pdf->SetFont('Times', 'B', 8.5);
                    $pdf->SetXY($leftX, $curY);
                    $pdf->Cell(40, $lineHeight + 0.5, gettext('Name'), 0, 0, 'L');
                    $pdf->Cell(8, $lineHeight + 0.5, gettext('M/F'), 0, 0, 'C');
                    $pdf->Cell(15, $lineHeight + 0.5, gettext('Role'), 0, 0, 'L');
                    $pdf->Cell(30, $lineHeight + 0.5, gettext('Email'), 0, 0, 'L');
                    $pdf->Cell(18, $lineHeight + 0.5, gettext('Birthday'), 0, 0, 'L');
                    $pdf->Cell(8, $lineHeight + 0.5, gettext('Age'), 0, 0, 'C');
                    $pdf->Cell(28, $lineHeight + 0.5, gettext('Cell Phone'), 0, 1, 'L');
                    $curY = $pdf->GetY();
                    $pdf->SetDrawColor(100, 100, 100);
                    $pdf->Line($leftX, $curY, 195, $curY);
                    $pdf->SetDrawColor(0, 0, 0);
                    $curY += 1.5;
                }

                // Member data row
                $pdf->SetFont('Times', '', 8.5);
                $memberName = mb_substr($per_FirstName . ' ' . $per_LastName, 0, 25);
                $genderStr = ($per_Gender == 1 ? 'M' : 'F');
                $birthdayStr = MiscUtils::formatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, false);
                $hideAgeStr = ($per_Flags ? 'Y' : 'N');
                $email = mb_substr($per_Email, 0, 20);
                $cellPhone = mb_substr($per_WorkPhone, 0, 18);
                $role = mb_substr($sFamRole, 0, 10);
                if (strlen($sFamRole) > 10) {
                    $role = mb_substr($role, 0, 7) . '...';
                }

                $pdf->SetXY($leftX, $curY);
                $pdf->Cell(40, $lineHeight, $memberName, 0, 0, 'L');
                $pdf->Cell(8, $lineHeight, $genderStr, 0, 0, 'C');
                $pdf->Cell(15, $lineHeight, $role, 0, 0, 'L');
                $pdf->Cell(30, $lineHeight, $email, 0, 0, 'L');
                $pdf->Cell(18, $lineHeight, $birthdayStr, 0, 0, 'L');
                $pdf->Cell(8, $lineHeight, $hideAgeStr, 0, 0, 'C');
                $pdf->Cell(28, $lineHeight, $cellPhone, 0, 1, 'L');
                $curY = $pdf->GetY();
                
                // Show work phone if available
                if (!empty($per_WorkPhone)) {
                    $pdf->SetFont('Times', '', 7.5);
                    $pdf->SetXY($leftX + 2, $curY);
                    $pdf->Cell(0, $lineHeight - 1, gettext('Work') . ': ' . mb_substr($per_WorkPhone, 0, 18), 0, 1, 'L');
                    $curY = $pdf->GetY();
                }
                
                $curY += 0.5;
            }
        }

        $curY += 2;

        if (($curY + 10) >= 260) {
            $curY = $pdf->startLetterPage($family->getId(), $family->getName(), $family->getAddress1(), $family->getAddress2(), $family->getCity(), $family->getState(), $family->getZip(), $family->getCountry());
            $curY += 3;
        }
        
        $sSQL = 'SELECT * FROM person_per WHERE per_fam_ID = ' . $family->getId() . ' ORDER BY per_fmr_ID';
        $rsFamilyMembers = RunQuery($sSQL);
        $groupsFound = false;
        
        while ($aMember = mysqli_fetch_array($rsFamilyMembers)) {
            extract($aMember);

            // Get the Groups this Person is assigned to
            $sSQL = 'SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
                    FROM group_grp
                    LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
                    LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
                    WHERE person2group2role_p2g2r.p2g2r_per_ID = ' . $per_ID . '
                    ORDER BY grp_Name';
            $rsAssignedGroups = RunQuery($sSQL);
            
            if (mysqli_num_rows($rsAssignedGroups) > 0) {
                // Add section header once
                if (!$groupsFound) {
                    $groupsFound = true;
                    $curY += 1;
                    $pdf->SetFont('Times', 'B', 11);
                    $pdf->SetXY($leftX, $curY);
                    $pdf->Cell(0, 5, gettext('Group Memberships'), 0, 1, 'L');
                    $curY = $pdf->GetY() + 1.5;
                }
                
                $pdf->SetFont('Times', '', 9);
                $personName = mb_substr($per_FirstName . ' ' . $per_LastName, 0, 28);
                $pdf->SetXY($leftX, $curY);
                $pdf->Cell(40, $lineHeight, $personName . ':', 0, 0, 'L');
                
                // Build groups string
                $groupsList = '';
                while ($aGroup = mysqli_fetch_array($rsAssignedGroups)) {
                    extract($aGroup);
                    if (!empty($groupsList)) {
                        $groupsList .= ', ';
                    }
                    $groupsList .= $grp_Name . ' (' . $roleName . ')';
                }
                
                // Wrap groups text with proper spacing
                $pdf->SetXY($leftX + 42, $curY);
                $pdf->MultiCell(153, $lineHeight, $groupsList, 0, 'L');
                $curY = $pdf->GetY() + 1;
            }
        }

        $curY += 1;
        if ($curY > 183) {
            $curY = $pdf->startLetterPage($family->getId(), $family->getName(), $family->getAddress1(), $family->getAddress2(), $family->getCity(), $family->getState(), $family->getZip(), $family->getCountry());
            $curY += 3;
        }
        $pdf->finishPage($curY);
    }

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output($filename, 'D');
} else {
    $pdf->Output();
}
