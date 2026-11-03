<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\verify\FamilyVerificationEmail;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustomQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\PdfFilename;

/**
 * Family-data confirmation report: one sheet per family showing what is on record, with the
 * church's wording around it. The same `confirm-report` document is printed for a whole
 * church (or one family) and attached to each family's email.
 *
 * Routes (src/people/routes/people.php):
 *  GET  /people/report/verify        -> generateDownloadPDF()
 *  POST /people/report/verify/email  -> sendFamilyEmails()
 *
 * Callers authorize: both routes require the Edit Records permission.
 */
class ConfirmReportService
{
    /** Regex pattern matching valid person_custom column names (c1, c2, ...). */
    private const CUSTOM_FIELD_PATTERN = '/^c\d+$/';

    /** @var array<string,string> Cache for ListOption role name lookups (key: "listId:optionId"). */
    private array $roleNameCache = [];

    /**
     * Every family that can receive an email: at least one valid address among the family's own
     * email and its living members' emails. Deceased members' addresses are never used.
     *
     * @return list<array{family: Family, members: list<Person>, emails: list<string>}>
     */
    public function emailTargets(?int $familyId): array
    {
        $targets = [];
        foreach ($this->families($familyId) as $family) {
            $members = $this->livingMembers($family);
            $emails = self::recipientEmails($family, $members);
            if ($emails !== []) {
                $targets[] = ['family' => $family, 'members' => $members, 'emails' => $emails];
            }
        }

        return $targets;
    }

    /**
     * The addresses a family's report is emailed to: the family email plus each living member's
     * personal and work email, valid and de-duplicated.
     *
     * @param list<Person> $members living members of the family
     * @return list<string>
     */
    public static function recipientEmails(Family $family, array $members): array
    {
        $candidates = [$family->getEmail()];
        foreach ($members as $member) {
            $candidates[] = $member->getEmail();
            $candidates[] = $member->getWorkEmail();
        }

        $emails = [];
        foreach ($candidates as $candidate) {
            $address = trim((string) $candidate);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[strtolower($address)] = $address;
            }
        }

        return array_values($emails);
    }

    /**
     * Generate a combined download PDF for all active families (or one specific family).
     *
     * @param int|null $familyId If set, only include that one family.
     * @return array{bytes: string, label: string} PDF bytes and report filename label.
     */
    public function generateDownloadPDF(?int $familyId): array
    {
        $families = $this->families($familyId);

        $sheets = [];
        foreach ($families as $family) {
            $sheets[] = $this->familySheet($family, $this->livingMembers($family), []);
        }

        $label = 'ConfirmReport-' . date(SystemConfig::getValue('sDateFilenameFormat'));
        if ($familyId !== null && count($families) === 1) {
            $label = 'ConfirmReport-' . $families[0]->getName();
        }

        $bytes = (new MpdfRenderer())->renderToString('confirm-report', $this->document($sheets, 'graphic'));

        return ['bytes' => $bytes, 'label' => $label];
    }

    /**
     * Build one PDF per family and email it to that family's own addresses.
     *
     * Returns a structured result object instead of throwing on SMTP failure,
     * so callers can surface a specific error message to the user.
     *
     * @param int|null $familyId If set, only email that one family.
     * @param bool     $updated  Append "** Updated **" to the email subject.
     * @return ConfirmReportEmailResult Structured result with status/counts.
     */
    public function sendFamilyEmails(?int $familyId, bool $updated = false): ConfirmReportEmailResult
    {
        $customFields = (new CustomFieldService())->getVisiblePersonFields();
        $renderer = new MpdfRenderer();
        $senderId = (int) AuthenticationManager::getCurrentUser()->getId();
        $dateStamp = date(SystemConfig::getValue('sDateFilenameFormat'));

        $targets = $this->emailTargets($familyId);
        $sentCount = 0;
        $failedFamilies = [];

        foreach ($targets as $target) {
            $family = $target['family'];
            $famName = (string) $family->getName();

            $sheet = $this->familySheet($family, $target['members'], $customFields);
            $pdfBytes = $renderer->renderToString('confirm-report', $this->document([$sheet], 'address'));

            $mail = new FamilyVerificationEmail($target['emails'], $famName, null, $updated);
            $mail->setLogContext(null, (int) $family->getId(), $senderId);
            $mail->addStringAttachment($pdfBytes, PdfFilename::create('ConfirmReportEmail-' . $famName . '-' . $dateStamp));

            if ($mail->send()) {
                $sentCount++;
            } else {
                LoggerUtils::getAppLogger()->error(
                    'ConfirmReportService email error for family "' . $famName . '": ' . $mail->getError()
                );
                $failedFamilies[] = $famName;
            }
        }

        if ($targets === []) {
            return new ConfirmReportEmailResult(ConfirmReportEmailResult::STATUS_NO_RECIPIENTS, 0, 0);
        }

        if ($failedFamilies === []) {
            return new ConfirmReportEmailResult(ConfirmReportEmailResult::STATUS_SUCCESS, $sentCount, 0);
        }

        return new ConfirmReportEmailResult(
            $sentCount === 0 ? ConfirmReportEmailResult::STATUS_SMTP_FAILURE : ConfirmReportEmailResult::STATUS_PARTIAL_FAILURE,
            $sentCount,
            count($failedFamilies),
            $failedFamilies,
        );
    }

    /**
     * Return preview data for the send-confirmation modal without actually sending.
     *
     * @param int|null $familyId If set, scope to a single family.
     * @return array{recipientCount: int, recipients: list<array{id: int, name: string, email: string}>, familiesWithoutEmail: list<array{id: int, name: string}>, templatePreview: array{subject: string, bodyExcerpt: string}}
     */
    public function getEmailPreview(?int $familyId): array
    {
        $recipients = [];
        $recipientIds = [];
        foreach ($this->emailTargets($familyId) as $target) {
            $family = $target['family'];
            $recipientIds[$family->getId()] = true;
            $recipients[] = [
                'id'    => $family->getId(),
                'name'  => $family->getName(),
                'email' => (string) ($family->getEmail() ?: $target['emails'][0]),
            ];
        }

        $familiesWithoutEmail = [];
        foreach ($this->families($familyId) as $family) {
            if (!isset($recipientIds[$family->getId()])) {
                $familiesWithoutEmail[] = [
                    'id'   => $family->getId(),
                    'name' => $family->getName(),
                ];
            }
        }

        $subjectTemplate = sprintf(
            gettext('%s: Please verify your family\'s information'),
            gettext('[Family Name]')
        );

        $rawBody     = (string) SystemConfig::getValue('sConfirm1');
        $bodyPlain   = trim(strip_tags($rawBody));
        $bodyExcerpt = mb_strlen($bodyPlain) > 200
            ? mb_substr($bodyPlain, 0, 200) . '…'
            : $bodyPlain;

        return [
            'recipientCount'       => count($recipients),
            'recipients'           => $recipients,
            'familiesWithoutEmail' => $familiesWithoutEmail,
            'templatePreview'      => [
                'subject'     => $subjectTemplate,
                'bodyExcerpt' => $bodyExcerpt,
            ],
        ];
    }

    /**
     * Families in the report: the one asked for, else every active family.
     *
     * @return list<Family>
     */
    private function families(?int $familyId): array
    {
        $query = FamilyQuery::create()->orderByName();
        if ($familyId !== null) {
            $query->filterById($familyId);
        } else {
            $query->filterByDateDeactivated(null);
        }

        return iterator_to_array($query->find(), false);
    }

    /**
     * @return list<Person>
     */
    private function livingMembers(Family $family): array
    {
        $members = [];
        foreach (PersonQuery::create()->filterByFamId($family->getId())->orderByFmrId()->find() as $person) {
            if (!$person->isDeceased()) {
                $members[] = $person;
            }
        }

        return $members;
    }

    /**
     * The data the `confirm-report` template renders for one family.
     *
     * @param list<Person> $members
     * @param list<\ChurchCRM\model\ChurchCRM\PersonCustomMaster> $customFields fields the signed-in user may see
     */
    private function familySheet(Family $family, array $members, array $customFields): array
    {
        $details = [
            [gettext('Family Name'), (string) $family->getName()],
            [gettext('Address'), Family::formatAddressBlock($family->getPrimaryAddressParts(), ', ')],
        ];
        if ($family->hasDistinctMailingAddress()) {
            $details[] = [gettext('Mailing Address'), $family->getMailingAddressLines(', ')];
        }
        $details[] = [gettext('Home Phone'), (string) $family->getHomePhone()];
        $details[] = [gettext('Send Newsletter'), $family->isSendNewsletter() ? gettext('Yes') : gettext('No')];
        if ($family->getWeddingdate()) {
            $details[] = [gettext('Anniversary Date'), (string) $family->getWeddingdate(SystemConfig::getValue('sDateFormatLong'))];
        }
        $details[] = [gettext('Family Email'), (string) $family->getEmail()];

        $rows = [];
        $groups = [];
        foreach ($members as $member) {
            $rows[] = [
                'name' => $member->getFullName(),
                'gender' => $member->isMale() ? 'M' : ($member->isFemale() ? 'F' : ''),
                'role' => $member->getFamilyRoleName(),
                'classification' => (string) $member->getClassificationName(),
                'email' => (string) $member->getEmail(),
                'birthday' => $this->birthday($member),
                'cell_phone' => (string) $member->getCellPhone(),
                'work_phone' => (string) $member->getWorkPhone(),
                'custom_fields' => $this->customFieldValues($member, $customFields),
            ];

            $memberGroups = $this->groupLabels($member);
            if ($memberGroups !== []) {
                $groups[] = ['name' => $member->getFullName(), 'groups' => implode(', ', $memberGroups)];
            }
        }

        return [
            'salutation' => $family->getSalutation(),
            'mailing_address' => $family->getMailingAddressLines(),
            'details' => array_map(static fn (array $row): array => ['label' => $row[0], 'value' => $row[1]], $details),
            'members' => $rows,
            'groups' => $groups,
        ];
    }

    /**
     * The church's wording and the sheets, in the shape the `confirm-report` template takes.
     *
     * @param list<array<string, mixed>> $sheets
     */
    private function document(array $sheets, string $letterhead): array
    {
        $graphic = $letterhead === 'graphic' ? MpdfRenderer::graphicLetterhead() : null;
        $closing = [];
        foreach (['sConfirm2', 'sConfirm3', 'sConfirm4', 'sConfirm5', 'sConfirm6'] as $key) {
            $text = trim((string) SystemConfig::getValue($key));
            if ($text !== '') {
                $closing[] = $text;
            }
        }
        $signer = trim((string) SystemConfig::getValue('sConfirmSigner'));

        return [
            'letterhead' => $graphic !== null ? 'graphic' : 'address',
            'letterhead_image' => $graphic,
            'intro' => trim((string) SystemConfig::getValue('sConfirm1')),
            'closing' => $closing,
            'sincerely' => $signer !== '' ? trim((string) SystemConfig::getValue('sConfirmSincerely')) . ',' : '',
            'signer' => $signer,
            'families' => $sheets,
        ];
    }

    private function birthday(Person $member): string
    {
        if (empty($member->getBirthMonth()) || empty($member->getBirthDay())) {
            return '';
        }
        try {
            return MiscUtils::formatBirthDate($member->getBirthYear(), (string) $member->getBirthMonth(), (string) $member->getBirthDay(), false);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * "Group (Role)" for each group the person belongs to.
     *
     * @return list<string>
     */
    private function groupLabels(Person $member): array
    {
        $labels = [];
        foreach ($member->getPerson2group2roleP2g2rs() as $membership) {
            $group = $membership->getGroup();
            if ($group === null) {
                continue;
            }
            $roleName = $membership->getRoleId()
                ? $this->getRoleName((int) $group->getRoleListId(), (int) $membership->getRoleId())
                : '';
            $labels[] = $roleName !== '' ? $group->getName() . ' (' . $roleName . ')' : (string) $group->getName();
        }

        return $labels;
    }

    /**
     * A person's values for the custom fields the signed-in user may see, in display order.
     *
     * @param list<\ChurchCRM\model\ChurchCRM\PersonCustomMaster> $fields as returned by CustomFieldService::getVisiblePersonFields()
     * @return list<array{label: string, value: string}>
     */
    private function customFieldValues(Person $member, array $fields): array
    {
        if ($fields === []) {
            return [];
        }

        $query = PersonCustomQuery::create();
        foreach ($fields as $field) {
            if (preg_match(self::CUSTOM_FIELD_PATTERN, (string) $field->getId())) {
                $query->withColumn($field->getId());
            }
        }
        $stored = $query->findOneByPerId($member->getId());
        $virtualColumns = $stored ? $stored->getVirtualColumns() : [];

        $values = [];
        foreach ($fields as $field) {
            $fieldId = (string) $field->getId();
            $raw = preg_match(self::CUSTOM_FIELD_PATTERN, $fieldId) ? trim((string) ($virtualColumns[$fieldId] ?? '')) : '';
            $values[] = [
                'label' => (string) $field->getName(),
                'value' => $raw === '' ? '' : (string) CustomFieldUtils::display($field->getTypeId(), $raw, $field->getSpecial()),
            ];
        }

        return $values;
    }

    /**
     * Return the role name for a group role, caching results to avoid N+1 queries.
     */
    private function getRoleName(int $roleListId, int $roleId): string
    {
        $key = $roleListId . ':' . $roleId;
        if (!array_key_exists($key, $this->roleNameCache)) {
            $roleObj = ListOptionQuery::create()
                ->filterById($roleListId)
                ->filterByOptionId($roleId)
                ->findOne();
            $this->roleNameCache[$key] = $roleObj ? $roleObj->getOptionName() : '';
        }

        return $this->roleNameCache[$key];
    }
}
