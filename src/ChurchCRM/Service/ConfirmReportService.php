<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\verify\FamilyVerificationEmail;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Reports\ChurchInfoReport;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Download-format confirmation report PDF.
 *
 * Generates a combined multi-page PDF with all family information laid out
 * for print. Used by the "Letters" button on the People Verify dashboard.
 */
class ConfirmDownloadPdf extends ChurchInfoReport
{
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(10, 20);
        $this->SetAutoPageBreak(false);
    }

    public function startNewPage(\ChurchCRM\model\ChurchCRM\Family $family): float
    {
        $curY = $this->startLetterPage(
            $family->getId(),
            $family->getName(),
            $family->getAddress1(),
            $family->getAddress2(),
            (string) ($family->getCity() ?? ''),
            (string) ($family->getState() ?? ''),
            (string) ($family->getZip() ?? ''),
            $family->getCountry(),
            'graphic'
        );

        $curY += SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm1'));

        return $curY + SystemConfig::getValue('incrementY');
    }

    public function finishPage(float $curY): void
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
        if (SystemConfig::getValue('sConfirmSigner')) {
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
            $curY += 4 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSigner'));
        }
    }
}

/**
 * Email-format confirmation report PDF.
 *
 * Generates one PDF per family (with detailed field-label layout) for
 * emailing as an attachment.  Used by the "Email Families" button.
 */
class ConfirmEmailPdf extends ChurchInfoReport
{
    public function __construct()
    {
        parent::__construct('P', 'mm', $this->paperFormat);
        $this->SetFont('Times', '', 10);
        $this->SetMargins(10, 20);
        $this->SetAutoPageBreak(false);
    }

    public function startNewPage(int $famId, string $famName, ?string $addr1, ?string $addr2, string $city, string $state, string $zip, ?string $country): float
    {
        $curY = $this->startLetterPage($famId, $famName, $addr1, $addr2, $city, $state, $zip, $country);
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm1'));

        return $curY + 2 * SystemConfig::getValue('incrementY');
    }

    public function finishPage(float $curY): void
    {
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm2'));

        $curY += 3 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm3'));
        $curY += 2 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm4'));

        if (SystemConfig::getValue('sConfirm5') !== '') {
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm5'));
            $curY += 2 * SystemConfig::getValue('incrementY');
        }
        if (SystemConfig::getValue('sConfirm6') !== '') {
            $curY += 2 * SystemConfig::getValue('incrementY');
            $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirm6'));
        }

        $curY += 4 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSincerely') . ',');
        $curY += 4 * SystemConfig::getValue('incrementY');
        $this->writeAt(SystemConfig::getValue('leftX'), $curY, SystemConfig::getValue('sConfirmSigner'));
    }
}

/**
 * Service for generating and emailing family-data confirmation reports.
 *
 * Consolidates the logic previously spread across:
 *  - src/Reports/ConfirmReport.php      (download PDF)
 *  - src/Reports/ConfirmReportEmail.php (email PDF per family)
 *
 * Both legacy entry-points now redirect to the v2 MVC routes
 *  GET /v2/people/report/verify        → generateDownloadPDF()
 *  GET /v2/people/report/verify/email  → sendFamilyEmails()
 */
class ConfirmReportService
{
    /** Regex pattern matching valid person_custom column names (c1, c2, …). */
    private const CUSTOM_FIELD_PATTERN = '/^c\d+$/';
    /**
     * Generate a combined download PDF for all families (or one specific family).
     *
     * @param int|null $familyId If set, only include that one family.
     * @return array{bytes: string, filename: string} PDF bytes and suggested filename.
     */
    public function generateDownloadPDF(?int $familyId): array
    {
        $pdf = new ConfirmDownloadPdf();
        $filename = 'ConfirmReport' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';

        $customFields = PersonCustomMasterQuery::create()->orderByOrder()->find();

        $familyQuery = FamilyQuery::create()->orderByName();
        if ($familyId !== null) {
            $familyQuery->filterById($familyId);
        }
        $families = $familyQuery->find();

        $leftX = SystemConfig::getValue('leftX');
        $lineHeight = 5.5;

        foreach ($families as $family) {
            if ($familyId !== null) {
                $filename = 'ConfirmReport-' . $family->getName() . '.pdf';
            }

            $curY = $pdf->startNewPage($family);
            $curY += 7;

            // Helper closure: render a bold label + data value pair
            $addField = function (string $label, ?string $value, float $x, float $y, float $width = 85.0) use ($pdf): float {
                $pdf->SetFont('Times', 'B', 10);
                $pdf->SetXY($x, $y);
                $pdf->Cell($width, 4, $label . ':', 0, 1, 'L');

                $pdf->SetFont('Times', '', 10);
                $pdf->SetXY($x + 2, $pdf->GetY());
                $pdf->MultiCell($width - 4, 4, (string)$value, 0, 'L');

                return $pdf->GetY();
            };

            // --- Family information block (2-column layout) ---
            $col1X = $leftX;
            $col2X = $leftX + 95;
            $col1Y = $curY;
            $col2Y = $curY;

            $col1Y = $addField(gettext('Family Name'), $family->getName(), $col1X, $col1Y) + 1;

            $address = (string) $family->getAddress1();
            if (!empty($family->getAddress2())) {
                $address .= ', ' . (string) $family->getAddress2();
            }
            $address .= ', ' . ((string) $family->getCity()) . ', ' . ((string) $family->getState()) . '  ' . ((string) $family->getZip());
            $col1Y = $addField(gettext('Address'), $address, $col1X, $col1Y) + 1;

            $col2Y = $addField(gettext('Home Phone'), $family->getHomePhone(), $col2X, $col2Y) + 1;
            $col2Y = $addField(gettext('Send Newsletter'), $family->getSendNewsletter(), $col2X, $col2Y) + 1;
            if ($family->getWeddingdate()) {
                $col2Y = $addField(gettext('Anniversary Date'), $family->getWeddingdate(SystemConfig::getValue('sDateFormatLong')), $col2X, $col2Y) + 1;
            }
            $col2Y = $addField(gettext('Family Email'), $family->getEmail(), $col2X, $col2Y) + 1;

            $curY = max($col1Y, $col2Y) + 1;

            // --- Family members section ---
            $familyMembers = PersonQuery::create()
                ->filterByFamId($family->getId())
                ->orderByFmrId()
                ->find();

            if (count($familyMembers) > 0) {
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

                $pdf->SetDrawColor(100, 100, 100);
                $pdf->Line($leftX, $curY, 195, $curY);
                $pdf->SetDrawColor(0, 0, 0);
                $curY += 1.5;

                foreach ($familyMembers as $member) {
                    // New page if needed
                    if ($curY > 255) {
                        $curY = $pdf->startLetterPage(
                            $family->getId(), $family->getName(),
                            $family->getAddress1(), $family->getAddress2(),
                            (string)($family->getCity() ?? ''), (string)($family->getState() ?? ''),
                            (string)($family->getZip() ?? ''), $family->getCountry()
                        );
                        $curY += 3;

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

                    $pdf->SetFont('Times', '', 8.5);
                    $memberName = mb_substr($member->getFirstName() . ' ' . $member->getLastName(), 0, 25);
                    $genderStr = ($member->getGender() == 1 ? 'M' : 'F');
                    $birthdayStr = MiscUtils::formatBirthDate($member->getBirthYear(), $member->getBirthMonth(), $member->getBirthDay(), false);
                    $hideAgeStr = ($member->getFlags() ? 'Y' : 'N');
                    $email = mb_substr((string)$member->getEmail(), 0, 20);
                    $cellPhone = mb_substr((string)$member->getCellPhone(), 0, 18);
                    $famRole = $member->getFamilyRoleName();
                    $role = mb_substr($famRole, 0, 10);
                    if (mb_strlen($famRole) > 10) {
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

                    if (!empty($member->getWorkPhone())) {
                        $pdf->SetFont('Times', '', 7.5);
                        $pdf->SetXY($leftX + 2, $curY);
                        $pdf->Cell(0, $lineHeight - 1, gettext('Work') . ': ' . mb_substr((string)$member->getWorkPhone(), 0, 18), 0, 1, 'L');
                        $curY = $pdf->GetY();
                    }

                    $curY += 0.5;
                }
            }

            $curY += 2;

            // --- Group memberships section ---
            if ($curY + 10 >= 260) {
                $curY = $pdf->startLetterPage(
                    $family->getId(), $family->getName(),
                    $family->getAddress1(), $family->getAddress2(),
                    (string)($family->getCity() ?? ''), (string)($family->getState() ?? ''),
                    (string)($family->getZip() ?? ''), $family->getCountry()
                );
                $curY += 3;
            }

            $groupsFound = false;
            foreach ($familyMembers as $member) {
                $assignedGroups = $member->getPerson2group2roleP2g2rs();
                if (count($assignedGroups) === 0) {
                    continue;
                }

                if (!$groupsFound) {
                    $groupsFound = true;
                    $curY += 1;
                    $pdf->SetFont('Times', 'B', 11);
                    $pdf->SetXY($leftX, $curY);
                    $pdf->Cell(0, 5, gettext('Group Memberships'), 0, 1, 'L');
                    $curY = $pdf->GetY() + 1.5;
                }

                $pdf->SetFont('Times', '', 9);
                $personName = mb_substr($member->getFirstName() . ' ' . $member->getLastName(), 0, 28);
                $pdf->SetXY($leftX, $curY);
                $pdf->Cell(40, $lineHeight, $personName . ':', 0, 0, 'L');

                $groupsList = '';
                foreach ($assignedGroups as $groupRole) {
                    $group = $groupRole->getGroup();
                    if ($group === null) {
                        continue;
                    }
                    if ($groupsList !== '') {
                        $groupsList .= ', ';
                    }
                    $roleName = '';
                    if ($groupRole->getRoleId()) {
                        $roleObj = ListOptionQuery::create()
                            ->filterById($group->getRoleListId())
                            ->filterByOptionId($groupRole->getRoleId())
                            ->findOne();
                        if ($roleObj) {
                            $roleName = $roleObj->getOptionName();
                        }
                    }
                    $groupsList .= $group->getName() . ' (' . $roleName . ')';
                }

                $pdf->SetXY($leftX + 42, $curY);
                $pdf->MultiCell(153, $lineHeight, $groupsList, 0, 'L');
                $curY = $pdf->GetY() + 1;
            }

            $curY += 1;
            if ($curY > 183) {
                $curY = $pdf->startLetterPage(
                    $family->getId(), $family->getName(),
                    $family->getAddress1(), $family->getAddress2(),
                    (string)($family->getCity() ?? ''), (string)($family->getState() ?? ''),
                    (string)($family->getZip() ?? ''), $family->getCountry()
                );
                $curY += 3;
            }
            $pdf->finishPage($curY);
        }

        // FPDF requires at least one page; add a blank page when no families were found
        if (count($families) === 0) {
            $pdf->AddPage();
        }

        return ['bytes' => $pdf->Output($filename, 'S'), 'filename' => $filename];
    }

    /**
     * Generate a per-family PDF and email it to the family.
     *
     * @param int|null $familyId If set, only email that one family.
     * @param bool     $updated  Append "** Updated **" to the email subject.
     * @return int Number of families successfully emailed.
     *
     * @throws \RuntimeException if an email send fails (caller should redirect with error).
     */
    public function sendFamilyEmails(?int $familyId, bool $updated = false): int
    {
        $customFields = PersonCustomMasterQuery::create()->orderByOrder()->find();

        $familyQuery = FamilyQuery::create()
            ->usePersonQuery()
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->endUse()
            ->orderByName();

        if ($familyId !== null) {
            $familyQuery->filterById($familyId);
        }

        $families = $familyQuery->distinct()->find();

        $dataCol = 55;
        $dataWid = 65;
        $familiesEmailed = 0;

        foreach ($families as $family) {
            $pdf = new ConfirmEmailPdf();

            $famId = $family->getId();
            $famName = $family->getName();
            $addr1 = $family->getAddress1();
            $addr2 = $family->getAddress2();
            $city = (string)($family->getCity() ?? '');
            $state = (string)($family->getState() ?? '');
            $zip = (string)($family->getZip() ?? '');
            $country = $family->getCountry();

            $emaillist = $family->getEmails();

            $familyMembers = PersonQuery::create()
                ->filterByFamId($famId)
                ->orderByFmrId()
                ->find();

            $curY = $pdf->startNewPage($famId, $famName, $addr1, $addr2, $city, $state, $zip, $country);
            $curY += SystemConfig::getValue('incrementY');

            // --- Family info (field-label table format) ---
            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Family Name'));
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, (string)$famName);
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Address') . ' 1');
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, (string)$addr1);
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Address') . ' 2');
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, (string)$addr2);
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('City, State, Zip'));
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, $city . ', ' . $state . '  ' . $zip);
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Home Phone'));
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, (string)$family->getHomePhone());
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Send Newsletter'));
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, (string)$family->getSendNewsletter());
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Anniversary Date'));
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, DateTimeUtils::formatDate($family->getWeddingDate()));
            $curY += SystemConfig::getValue('incrementY');

            $pdf->SetFont('Times', 'B', 10);
            $pdf->writeAtCell(SystemConfig::getValue('leftX'), $curY, $dataCol - SystemConfig::getValue('leftX'), gettext('Family Email'));
            $pdf->SetFont('Times', '', 10);
            $pdf->writeAtCell($dataCol, $curY, $dataWid, (string)$family->getEmail());
            $curY += SystemConfig::getValue('incrementY');
            $curY += SystemConfig::getValue('incrementY');

            // --- Member table header ---
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
                $numFamilyMembers++;

                $sClassName = $member->getClassificationName();
                $sFamRole = $member->getFamilyRoleName();

                // New page if not enough room for custom fields + trailer
                if (($curY + count($customFields) * SystemConfig::getValue('incrementY')) > 260) {
                    $curY = $pdf->startLetterPage($famId, $famName, $addr1, $addr2, $city, $state, $zip, $country);
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

                $pdf->SetFont('Times', 'B', 10);
                $pdf->writeAtCell($XName, $curY, $XGender - $XName, $member->getFirstName() . ' ' . $member->getMiddleName() . ' ' . $member->getLastName());
                $pdf->SetFont('Times', '', 10);
                $genderStr = ($member->getGender() == 1 ? 'M' : 'F');
                $pdf->writeAtCell($XGender, $curY, $XRole - $XGender, $genderStr);
                $pdf->writeAtCell($XRole, $curY, $XEmail - $XRole, $sFamRole);
                $pdf->writeAtCell($XEmail, $curY, $XBirthday - $XEmail, (string)$member->getEmail());

                if ($member->getBirthYear()) {
                    $birthdayStr = $member->getBirthMonth() . '/' . $member->getBirthDay() . '/' . $member->getBirthYear();
                } else {
                    $birthdayStr = '';
                }
                $pdf->writeAtCell($XBirthday, $curY, $XCellPhone - $XBirthday, $birthdayStr);
                $pdf->writeAtCell($XCellPhone, $curY, $XClassification - $XCellPhone, (string)$member->getCellPhone());
                $pdf->writeAtCell($XClassification, $curY, $XRight - $XClassification, $sClassName);
                $curY += SystemConfig::getValue('incrementY');

                $pdf->writeAtCell($XWorkPhone, $curY, $XRight - $XWorkPhone, gettext('Work Phone') . ': ' . (string)$member->getWorkPhone());
                $curY += SystemConfig::getValue('incrementY');
                $curY += SystemConfig::getValue('incrementY');

                // Custom person fields
                $xSize = 40;
                $numCustomFields = count($customFields);
                if ($numCustomFields > 0) {
                    $xInc = $XName;

                    // Fetch custom field data for this person
                    $rawQry = PersonCustomQuery::create();
                    foreach ($customFields as $field) {
                        if (preg_match(self::CUSTOM_FIELD_PATTERN, (string)$field->getId())) {
                            $rawQry->withColumn($field->getId());
                        }
                    }
                    $personCustomData = $rawQry->findOneByPerId($member->getId());
                    $virtualCols = $personCustomData ? $personCustomData->getVirtualColumns() : [];

                    $numWide = 0;
                    foreach ($customFields as $field) {
                        $fieldId = (string)$field->getId();
                        $currentFieldData = '';
                        if (preg_match(self::CUSTOM_FIELD_PATTERN, $fieldId)) {
                            $currentFieldData = trim((string)($virtualCols[$fieldId] ?? ''));
                        }

                        $pdf->writeAtCell($xInc, $curY, $xSize, $field->getName());
                        if ($currentFieldData === '') {
                            $pdf->SetFont('Times', 'B', 6);
                            $pdf->writeAtCell($xInc + $xSize, $curY, $xSize, '');
                            $pdf->SetFont('Times', '', 10);
                        } else {
                            $pdf->writeAtCell($xInc + $xSize, $curY, $xSize, $currentFieldData);
                        }
                        $numWide++;
                        $xInc += (2 * $xSize);
                        if (($numWide % 2) === 0) {
                            $xInc = $XName;
                            $curY += SystemConfig::getValue('incrementY');
                        }
                    }
                }
                $curY += 2 * SystemConfig::getValue('incrementY');
            }

            $curY += SystemConfig::getValue('incrementY');

            // New page if there's not enough room for group assignments
            if (($curY + 2 * $numFamilyMembers * SystemConfig::getValue('incrementY')) >= 260) {
                $curY = $pdf->startLetterPage($famId, $famName, $addr1, $addr2, $city, $state, $zip, $country);
            }

            // --- Group memberships ---
            foreach ($familyMembers as $aMember) {
                $assignedGroups = $aMember->getPerson2group2roleP2g2rs();
                if (count($assignedGroups) === 0) {
                    continue;
                }

                $groupStr = 'Assigned groups for ' . $aMember->getFirstName() . ' ' . $aMember->getLastName() . ': ';
                foreach ($assignedGroups as $groupRole) {
                    $group = $groupRole->getGroup();
                    if ($group === null) {
                        continue;
                    }
                    $roleName = '';
                    if ($groupRole->getRoleId()) {
                        $roleObj = ListOptionQuery::create()
                            ->filterById($group->getRoleListId())
                            ->filterByOptionId($groupRole->getRoleId())
                            ->findOne();
                        if ($roleObj) {
                            $roleName = $roleObj->getOptionName();
                        }
                    }
                    $groupStr .= $group->getName() . ' (' . $roleName . ') ';
                }

                $pdf->writeAt(SystemConfig::getValue('leftX'), $curY, $groupStr);
                $curY += 2 * SystemConfig::getValue('incrementY');
            }

            if ($curY > 183) {
                $curY = $pdf->startLetterPage($famId, $famName, $addr1, $addr2, $city, $state, $zip, $country);
            }
            $pdf->finishPage($curY);

            // Email the PDF
            if (!empty($emaillist)) {
                $pdfBytes = $pdf->Output('ConfirmReportEmail-' . $famId . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'S');
                $subject = $famName . ' Family Information Review';
                if ($updated) {
                    $subject .= ' ** Updated **';
                }

                $mail = new FamilyVerificationEmail($emaillist, $famName);
                $attachmentFilename = 'ConfirmReportEmail-' . $famName . '-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf';
                $mail->addStringAttachment($pdfBytes, $attachmentFilename);

                if ($mail->send()) {
                    $familiesEmailed++;
                } else {
                    LoggerUtils::getAppLogger()->error('ConfirmReportService email error: ' . $mail->getError());
                    throw new \RuntimeException('Email send failed for family: ' . $famName);
                }
            }
        }

        return $familiesEmailed;
    }
}
