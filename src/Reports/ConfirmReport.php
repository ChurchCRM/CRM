<?php

/*******************************************************************************
*
*  filename    : Reports/ConfirmReport.php
*  last change : 2003-08-30
*  description : Creates a PDF with all the confirmation letters asking member
*                families to verify the information in the database.
*
******************************************************************************/

namespace ChurchCRM\Reports;

require '../Include/Config.php';
require '../Include/Functions.php';

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

        $curY += 2 * SystemConfig::getValue('incrementY');
        $blurb = SystemConfig::getValue('sConfirm1');

        $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);

        $curY += 2 * SystemConfig::getValue('incrementY');

        return $curY;
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

$dataCol = 55;
$dataWid = 65;

// Loop through families
foreach ($families as $family) {
    //If this is a report for a single family, name the file accordingly.
    if ($iFamilyID) {
        $filename = 'ConfirmReport-' . $family->getName() . '.pdf';
    }

    $curY = $pdf->startNewPage($family);
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Family Name'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getName());
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Address 1'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getAddress1());
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Address 2'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getAddress2());
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('City, State, Zip'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getCity() . ', ' . $family->getState() . '  ' . $family->getZip());
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Home Phone'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getHomePhone());
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Send Newsletter'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getSendNewsletter());
    $curY += SystemConfig::getValue('incrementY');

    // Missing the following information from the Family record:
    // Wedding date (if present) - need to figure how to do this with sensitivity
    // Family e-mail address

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Anniversary Date'));
    $pdf->SetFont('Times', '', 10);
    if ($family->getWeddingdate()) {
        $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getWeddingdate(SystemConfig::getValue('sDateFormatLong')));
    }
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Family Email'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $family->getEmail());
    $curY += SystemConfig::getValue('incrementY');
    $curY += SystemConfig::getValue('incrementY');

    $sSQL = 'SELECT *, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole FROM person_per
				LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
				WHERE per_fam_ID = ' . $family->getId() . ' ORDER BY per_fmr_ID';
    $rsFamilyMembers = RunQuery($sSQL);

    $XName = 10;
    $XGender = 40;
    $XRole = 50;
    $XEmail = 80;
    $XBirthday = 125;
    $XHideAge = 145;
    $XCellPhone = 155;
    $XClassification = 180;
    $XWorkPhone = 155;
    $XRight = 208;

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell($XName, $curY, $XGender - $XName, gettext('Member Name'));
    $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, gettext('M/F'));
    $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, gettext('Adult/Child'));
    $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, gettext('Email'));
    $pdf->writeAtCell($XBirthday, $curY, $XHideAge - $XBirthday, gettext('Birthday'));
    $pdf->writeAtCell($XHideAge, $curY, $XCellPhone - $XHideAge, gettext('Hide Age'));
    $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, gettext('Cell phone'));
    $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, gettext('Member/Friend'));
    $pdf->SetFont('Times', '', 10);
    $curY += 3 * SystemConfig::getValue('incrementY');

    $numFamilyMembers = 0;
    while ($aMember = mysqli_fetch_array($rsFamilyMembers)) {
        $numFamilyMembers++;    // add one to the people count
        extract($aMember);
        // Make sure the person data will display with adequate room for the trailer and group information
        if (($curY + $numCustomFields * SystemConfig::getValue('incrementY')) > 260) {
            $curY = $pdf->startLetterPage($family->getId(), $family->getName(), $family->getAddress1(), $family->getAddress2(), $family->getCity(), $family->getState(), $family->getZip(), $family->getCountry());
            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell($XName, $curY, $XGender - $XName, gettext('Member Name'));
            $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, gettext('M/F'));
            $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, gettext('Adult/Child'));
            $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, gettext('Email'));
            $pdf->writeAtCell($XBirthday, $curY, $XHideAge - $XBirthday, gettext('Birthday'));
            $pdf->writeAtCell($XHideAge, $curY, $XCellPhone - $XHideAge, gettext('Hide Age'));
            $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, gettext('Cell phone'));
            $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, gettext('Member/Friend'));
            $pdf->SetFont('Times', '', 10);
            $curY += SystemConfig::getValue('incrementY');
        }

        $pdf->SetFont('Times', 'B', 10);
        $pdf->writeAtCell($XName, $curY, $XGender - $XName, $per_FirstName . ' ' . $per_MiddleName . ' ' . $per_LastName);
        $pdf->SetFont('Times', '', 10);
        $genderStr = ($per_Gender == 1 ? 'M' : 'F');
        $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, $genderStr);
        $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, $sFamRole);
        $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, $per_Email);

        $birthdayStr = MiscUtils::formatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay, $null, $null);
        //If the "HideAge" check box is true, then create a Yes/No representation of the check box.
        if ($per_Flags) {
            $hideAgeStr = gettext('Yes');
        } else {
            $hideAgeStr = gettext('No');
        }

        $pdf->writeAtCell($XBirthday, $curY, $XHideAge - $XBirthday, $birthdayStr);
        $pdf->writeAtCell($XHideAge, $curY, $XCellPhone - $XHideAge, $hideAgeStr);
        $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, $per_CellPhone);
        $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, $sClassName);
        $curY += SystemConfig::getValue('incrementY');
        // Missing the following information for the personal record: ? Is this the place to put this data ?
        // Work Phone
        $pdf->writeAtCell($XWorkPhone, $curY, $XRight - $XWorkPhone, gettext('Work Phone') . ':' . $per_WorkPhone);
        $curY += SystemConfig::getValue('incrementY');
        $curY += SystemConfig::getValue('incrementY');

        // *** All custom fields ***
        // Get the list of custom person fields

        $xSize = 40;
        $numCustomFields = mysqli_num_rows($rsCustomFields);
        if ($numCustomFields > 0) {
            extract($aMember);
            $sSQL = 'SELECT * FROM person_custom WHERE per_ID = ' . $per_ID;
            $rsCustomData = RunQuery($sSQL);
            $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);
            $numCustomData = mysqli_num_rows($rsCustomData);
            mysqli_data_seek($rsCustomFields, 0);
            $OutStr = '';
            $xInc = $XName;    // Set the starting column for Custom fields
            // Here is where we determine if space is available on the current page to
            // display the custom data and still get the ending on the page
            // Calculations (without groups) show 84 mm is needed.
            // For the Letter size of 279 mm, this says that curY can be no bigger than 195 mm.
            // Leaving 12 mm for a bottom margin yields 183 mm.
            $numWide = 0;    // starting value for columns
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                extract($rowCustomField);
                if ($sCustomFieldName[$custom_Order - 1]) {
                    $currentFieldData = trim($aCustomData[$custom_Field]);

                    $OutStr = $sCustomFieldName[$custom_Order - 1] . ' : ' . $currentFieldData . '    ';
                    $pdf->writeAtCell($xInc, $curY, $xSize, $sCustomFieldName[$custom_Order - 1]);
                    if (empty($currentFieldData)) {
                        $pdf->SetFont('Times', 'B', 6);
                        $pdf->writeAtCell($xInc + $xSize, $curY, $xSize, '');
                        $pdf->SetFont('Times', '', 10);
                    } else {
                        $pdf->writeAtCell($xInc + $xSize, $curY, $xSize, $currentFieldData);
                    }
                    $numWide += 1;    // increment the number of columns done
                    $xInc += (2 * $xSize);    // Increment the X position by about 1/2 page width
                    if (($numWide % 2) === 0) { // 2 columns
                        $xInc = $XName;    // Reset margin
                        $curY += SystemConfig::getValue('incrementY');
                    }
                }
            }
            //$pdf->writeAt($XName,$curY,$OutStr);
            //$curY += (2 * SystemConfig::getValue("incrementY"));
        }
        $curY += 2 * SystemConfig::getValue('incrementY');
    }
    //

    $curY += SystemConfig::getValue('incrementY');

    if (($curY + 2 * $numFamilyMembers * SystemConfig::getValue('incrementY')) >= 260) {
        $curY = $pdf->startLetterPage($family->getId(), $family->getName(), $family->getAddress1(), $family->getAddress2(), $family->getCity(), $family->getState(), $family->getZip(), $family->getCountry());
    }
    $sSQL = 'SELECT * FROM person_per WHERE per_fam_ID = ' . $family->getId() . ' ORDER BY per_fmr_ID';
    $rsFamilyMembers = RunQuery($sSQL);
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
            $groupStr = 'Assigned groups for ' . $per_FirstName . ' ' . $per_LastName . ': ';

            while ($aGroup = mysqli_fetch_array($rsAssignedGroups)) {
                extract($aGroup);
                $groupStr .= $grp_Name . ' (' . $roleName . ') ';
            }

            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $groupStr);
            $curY += 2 * SystemConfig::getValue('incrementY');
        }
    }

    if ($curY > 183) {    // This ensures the trailer information fits continuously on the page (3 inches of "footer"
        $curY = $pdf->startLetterPage($family->getId(), $family->getName(), $family->getAddress1(), $family->getAddress2(), $family->getCity(), $family->getState(), $family->getZip(), $family->getCountry());
    }
    $pdf->finishPage($curY);
}

if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
    $pdf->Output($filename, 'D');
} else {
    $pdf->Output();
}
