<?php

namespace ChurchCRM\Reports;

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\verify\FamilyVerificationEmail;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class EmailPdfConfirmReport extends ChurchInfoReport
{
    // Constructor
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(10, 20);

        $this->SetAutoPageBreak(false);
    }

    public function startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, string $fam_City, string $fam_State, string $fam_Zip, $fam_Country): float
    {
        $curY = $this->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
        $curY += 2 * SystemConfig::getValue('incrementY');
        $blurb = SystemConfig::getValue('sConfirm1');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, $blurb);

        return $curY + 2 * SystemConfig::getValue('incrementY');
    }

    public function finishPage($curY): void
    {
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm2'));

        $curY += 3 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm3'));
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm4'));

        if (SystemConfig::getValue('sConfirm5') != '') {
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm5'));
            $curY += 2 * SystemConfig::getValue('incrementY');
        }
        if (SystemConfig::getValue('sConfirm6') != '') {
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm6'));
        }

        $curY += 4 * SystemConfig::getValue('incrementY');

        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
        $curY += 4 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSigner'));
    }
}

$familiesEmailed = 0;

// Get the list of custom person fields
$customFields = PersonCustomMasterQuery::create()
    ->orderByOrder()
    ->find();
$numCustomFields = count($customFields);

$sCustomFieldName = [];
if ($numCustomFields > 0) {
    $iFieldNum = 0;
    foreach ($customFields as $field) {
        $sCustomFieldName[$iFieldNum] = $field->getName();
        $iFieldNum += 1;
    }
}

// Filter by family ID if provided in the request
$familyId = InputUtils::legacyFilterInput($_GET['familyId'] ?? null, 'int');

// Get all the families with email-enabled members
$familyQuery = FamilyQuery::create()
    ->usePersonQuery()
    ->filterByEmail('', Criteria::NOT_EQUAL)
    ->endUse()
    ->orderByName();

// Apply family ID filter if provided
if ($familyId) {
    $familyQuery->filterById((int)$familyId);
}

$families = $familyQuery->distinct()->find();

$dataCol = 55;
$dataWid = 65;

// Loop through families
foreach ($families as $family) {
    // Instantiate the directory class and build the report.
    $pdf = new EmailPdfConfirmReport();

    $fam_ID = $family->getId();
    $fam_Name = $family->getName();
    $fam_Address1 = $family->getAddress1();
    $fam_Address2 = $family->getAddress2();
    $fam_City = $family->getCity();
    $fam_State = $family->getState();
    $fam_Zip = $family->getZip();
    $fam_Country = $family->getCountry();
    $fam_HomePhone = $family->getHomePhone();
    $fam_SendNewsLetter = $family->getSendNewsletter();
    $fam_WeddingDate = $family->getWeddingDate();
    $fam_Email = $family->getEmail();

    // Get unique family emails
    $emaillist = $family->getEmails();
    
    // Get family members for PDF rendering
    $familyMembers = PersonQuery::create()
        ->filterByFamId($fam_ID)
        ->orderByFmrId()
        ->find();

    $curY = $pdf->startNewPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Family Name'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_Name);
    $curY += SystemConfig::getValue('incrementY');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Address') . ' 1');
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_Address1);
    $curY += SystemConfig::getValue('incrementY');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Address') . ' 2');
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_Address2);
    $curY += SystemConfig::getValue('incrementY');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('City, State, Zip'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_City . ', ' . $fam_State . '  ' . $fam_Zip);
    $curY += SystemConfig::getValue('incrementY');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Home Phone'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_HomePhone);
    $curY += SystemConfig::getValue('incrementY');
    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Send Newsletter'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_SendNewsLetter);
    $curY += SystemConfig::getValue('incrementY');

    // Missing the following information from the Family record:
    // Wedding date (if present) - need to figure how to do this with sensitivity
    // Family e-mail address

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Anniversary Date'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, FormatDate($fam_WeddingDate));
    $curY += SystemConfig::getValue('incrementY');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Family Email'));
    $pdf->SetFont('Times', '', 10);
    $pdf->writeAtCell($dataCol, $curY, $dataWid, $fam_Email);

    $curY += SystemConfig::getValue('incrementY');
    $curY += SystemConfig::getValue('incrementY');

    $XName = 10;
    $XGender = 50;
    $XRole = 60;
    $XEmail = 90;
    $XBirthday = 135;
    $XCellPhone = 155;
    $XClassification = 180;
    $XWorkPhone = 155;
    $XRight = 208;

    $pdf->SetFont('Times', 'B', 10);
    $pdf->writeAtCell($XName, $curY, $XGender - $XName, gettext('Member Name'));
    $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, gettext('M/F'));
    $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, gettext('Adult/Child'));
    $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, gettext('Email'));
    $pdf->writeAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, gettext('Birthday'));
    $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, gettext('Cell Phone'));
    $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, gettext('Member/Friend'));
    $pdf->SetFont('Times', '', 10);
    $curY += SystemConfig::getValue('incrementY');

    $numFamilyMembers = 0;
    foreach ($familyMembers as $member) {
        $numFamilyMembers++; // add one to the people count
        
        // Get the classification and family role objects
        $sClassName = '';
        if ($member->getClsId()) {
            $classObj = $member->getClassification();
            if ($classObj) {
                $sClassName = $classObj->getOptionName();
            }
        }
        
        $sFamRole = '';
        if ($member->getFmrId()) {
            $roleObj = $member->getFamilyRole();
            if ($roleObj) {
                $sFamRole = $roleObj->getOptionName();
            }
        }
        // Make sure the person data will display with adequate room for the trailer and group information
        if (($curY + $numCustomFields * SystemConfig::getValue('incrementY')) > 260) {
            $curY = $pdf->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell($XName, $curY, $XGender - $XName, gettext('Member Name'));
            $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, gettext('M/F'));
            $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, gettext('Adult/Child'));
            $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, gettext('Email'));
            $pdf->writeAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, gettext('Birthday'));
            $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, gettext('Cell Phone'));
            $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, gettext('Member/Friend'));
            $pdf->SetFont('Times', '', 10);
            $curY += SystemConfig::getValue('incrementY');
        }
        $iPersonID = $member->getId();
        $pdf->SetFont('Times', 'B', 10);
        $pdf->writeAtCell($XName, $curY, $XGender - $XName, $member->getFirstName() . ' ' . $member->getMiddleName() . ' ' . $member->getLastName());
        $pdf->SetFont('Times', '', 10);
        $genderStr = ($member->getGender() == 1 ? 'M' : 'F');
        $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, $genderStr);
        $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, $sFamRole);
        $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, $member->getEmail());
        
        if ($member->getBirthYear()) {
            $birthdayStr = $member->getBirthMonth() . '/' . $member->getBirthDay() . '/' . $member->getBirthYear();
        } else {
            $birthdayStr = '';
        }
        $pdf->writeAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, $birthdayStr);
        $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, $member->getCellPhone());
        $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, $sClassName);
        $curY += SystemConfig::getValue('incrementY');
        // Missing the following information for the personal record: ? Is this the place to put this data ?
        // Work Phone
        $pdf->writeAtCell($XWorkPhone, $curY, $XRight - $XWorkPhone, gettext('Work Phone') . ': ' . $member->getWorkPhone());
        $curY += SystemConfig::getValue('incrementY');
        $curY += SystemConfig::getValue('incrementY');

        // *** All custom fields ***
        // Get the list of custom person fields

        $xSize = 40;
        if ($numCustomFields > 0) {
            $xInc = $XName; // Set the starting column for Custom fields
      // Here is where we determine if space is available on the current page to
      // display the custom data and still get the ending on the page
      // Calculations (without groups) show 84 mm is needed.
      // For the Letter size of 279 mm, this says that curY can be no bigger than 195 mm.
      // Leaving 12 mm for a bottom margin yields 183 mm.
            $numWide = 0; // starting value for columns
            foreach ($customFields as $field) {
                $currentFieldData = '';
                // Try to access custom field data from the member object
                $fieldPropertyName = $field->getId();
                try {
                    $methodName = 'get' . ucfirst($fieldPropertyName);
                    $currentFieldData = trim($member->$methodName() ?? '');
                } catch (Exception $e) {
                    // Custom field getter does not exist or threw an exception
                    $currentFieldData = '';
                }

                $OutStr = $field->getName() . ' : ' . $currentFieldData . '    ';
                $pdf->writeAtCell($xInc, $curY, $xSize, $field->getName());
                if ($currentFieldData === '') {
                    $pdf->SetFont('Times', 'B', 6);
                    $pdf->writeAtCell($xInc + $xSize, $curY, $xSize, '');
                    $pdf->SetFont('Times', '', 10);
                } else {
                    $pdf->writeAtCell($xInc + $xSize, $curY, $xSize, $currentFieldData);
                }
                $numWide += 1; // increment the number of columns done
                $xInc += (2 * $xSize); // Increment the X position by about 1/2 page width
                if (($numWide % 2) == 0) { // 2 columns
                    $xInc = $XName; // Reset margin
                    $curY += SystemConfig::getValue('incrementY');
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
        $curY = $pdf->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    }
    
    // Get all family members again to display group assignments
    $familyMembersForGroups = PersonQuery::create()
        ->filterByFamId($fam_ID)
        ->orderByFmrId()
        ->find();
    
    foreach ($familyMembersForGroups as $aMember) {
        // Get the Groups this Person is assigned to
        $assignedGroups = $aMember->getPerson2group2roleP2g2rs();
        
        if (count($assignedGroups) > 0) {
            $groupStr = 'Assigned groups for ' . $aMember->getFirstName() . ' ' . $aMember->getLastName() . ': ';

            foreach ($assignedGroups as $groupRole) {
                $group = $groupRole->getGroup();
                if ($group) {
                    $roleName = '';
                    if ($groupRole->getRoleId()) {
                        $roleObj = $groupRole->getListOption();
                        if ($roleObj) {
                            $roleName = $roleObj->getOptionName();
                        }
                    }
                    $groupStr .= $group->getName() . ' (' . $roleName . ') ';
                }
            }

            $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $groupStr);
            $curY += 2 * SystemConfig::getValue('incrementY');
        }
    }

    if ($curY > 183) { // This insures the trailer information fits continuously on the page (3 inches of "footer"
        $curY = $pdf->startLetterPage($fam_ID, $fam_Name, $fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
    }
    $pdf->finishPage($curY);

    if (!empty($emaillist)) {
        $doc = $pdf->Output('ConfirmReportEmail-' . $fam_ID . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'S');

        $subject = $fam_Name . ' Family Information Review';

        if ($_GET['updated']) {
            $subject = $subject . ' ** Updated **';
        }

        $mail = new FamilyVerificationEmail($emaillist, $fam_Name);
        $filename = 'ConfirmReportEmail-' . $fam_Name . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
        $mail->addStringAttachment($doc, $filename);

        if ($mail->send()) {
            $familiesEmailed = $familiesEmailed + 1;
        } else {
            LoggerUtils::getAppLogger()->error($mail->getError());
            RedirectUtils::redirect(SystemURLs::getRootPath() . '/v2/people/verify?EmailsError=true');
        }
    }
}

if ($_GET['familyId']) {
    RedirectUtils::redirect('v2/family/' . $_GET['familyId'] . '?PDFEmailed=' . $familiesEmailed);
} else {
    RedirectUtils::redirect(SystemURLs::getRootPath() . '/v2/people/verify?AllPDFsEmailed=' . $familiesEmailed);
}
