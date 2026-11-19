<?php

namespace ChurchCRM\Service;

use ChurchCRM\data\Countries;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use InvalidArgumentException;

/**
 * Year-end giving statements: the Finance report and the Member Portal build the same
 * document from here.
 */
class GivingStatementService
{
    private const FIRST_YEAR = 1990;

    private const CAD_SYMBOLS = ['$', 'CAD', 'CAD $', 'CAD$', '$ CAD', '$CAD', 'CA$', 'C$'];

    /** Sample data stays independent of the donor/payment query and model paths. */
    public static function sampleDocument(): array
    {
        $family = [
            'salutation' => 'Sample Family (fictional)',
            'address' => '123 Sample Street' . "\n" . 'Sample City',
            'envelope' => '',
            'total' => CurrencyFormatter::format(150),
            'goods_services' => CurrencyFormatter::format(0),
            'deductible' => CurrencyFormatter::format(150),
            'disclosure' => gettext('No goods or services were provided in exchange for these contributions, other than intangible religious benefits.'),
            'gifts' => [
                [
                    'date' => DateTimeUtils::formatDate('2025-01-12'),
                    'fund' => 'Sample General Fund',
                    'method' => gettext('Cash'),
                    'amount' => CurrencyFormatter::format(100),
                ],
                [
                    'date' => DateTimeUtils::formatDate('2025-06-15'),
                    'fund' => 'Sample Missions Fund',
                    'method' => gettext('Check') . ' #1234',
                    'amount' => CurrencyFormatter::format(50),
                ],
            ],
        ];
        $document = self::document([$family], '2025-01-01', '2025-12-31');
        $document['title'] = gettext('Giving Statement — Sample Only');

        return $document;
    }

    /**
     * Turn untrusted query values into the criteria every other method takes.
     *
     * @return array{dateStart: string, dateEnd: string, depositId: int, byDepositDate: bool, minimumCents: int, fundIds: int[], familyIds: int[], classificationIds: int[], remittance: bool}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $currentEndYear = FiscalYearUtils::endYearForId(FiscalYearUtils::getCurrentFiscalYearId());
        $year = InputUtils::filterInt(self::scalar($query['year'] ?? ''));
        $familyIds = self::intList($query['family'] ?? []);
        $familyId = InputUtils::filterInt(self::scalar($query['familyId'] ?? ''));
        if ($familyId > 0) {
            $familyIds[] = $familyId;
        }

        $dateStart = DateTimeUtils::parseIsoDate(DateTimeUtils::normalizeDate(self::scalar($query['DateStart'] ?? '')), self::FIRST_YEAR);
        $dateEnd = DateTimeUtils::parseIsoDate(DateTimeUtils::normalizeDate(self::scalar($query['DateEnd'] ?? '')), self::FIRST_YEAR);
        if ($year >= self::FIRST_YEAR && $year <= $currentEndYear) {
            $fy = FiscalYearUtils::getFiscalYearDatesForCalendarYear($year);
            $dateStart = $fy['startDate'];
            $dateEnd = $fy['endDate'];
        }
        if ($dateStart === '' && $dateEnd === '') {
            $fy = FiscalYearUtils::previousFiscalYearDates();
            $dateStart = $fy['startDate'];
            $dateEnd = $fy['endDate'];
        }
        $dateStart = $dateStart !== '' ? $dateStart : $dateEnd;
        $dateEnd = $dateEnd !== '' ? $dateEnd : $dateStart;
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        return [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'depositId' => max(0, InputUtils::filterInt(self::scalar($query['deposit'] ?? ''))),
            'byDepositDate' => self::scalar($query['datetype'] ?? 'Payment') === 'Deposit',
            'minimumCents' => max(0, (int) round(InputUtils::filterFloat(self::scalar($query['minimum'] ?? '')) * 100)),
            'fundIds' => self::intList($query['funds'] ?? []),
            'familyIds' => array_values(array_unique($familyIds)),
            'classificationIds' => self::intList($query['classList'] ?? []),
            'remittance' => self::scalar($query['remittance'] ?? 'no') === 'yes',
        ];
    }

    /**
     * Payments for a statement run, grouped by family id (0 for a payment with no family),
     * families in id order and each family's payments by date.
     *
     * Callers authorize: the Finance routes sit behind FinanceRoleAuthMiddleware and the Member
     * Portal only builds the statement of the signed-in member's own family.
     *
     * @return array<int, Pledge[]>
     */
    public static function paymentsByFamily(array $criteria): array
    {
        $byDeposit = $criteria['depositId'] > 0;
        $query = PledgeQuery::create()->filterForTaxReport(
            $byDeposit ? '' : $criteria['dateStart'],
            $byDeposit ? '' : $criteria['dateEnd'],
            $criteria['fundIds'],
            $criteria['familyIds'],
            $criteria['classificationIds'],
            $criteria['byDepositDate']
        );
        if ($byDeposit) {
            $query->filterByDepId($criteria['depositId']);
        }

        $groups = [];
        foreach ($query->find() as $pledge) {
            $groups[(int) $pledge->getFamId()][] = $pledge;
        }

        // The minimum applies to a family's total for the period, never to a single gift.
        if ($criteria['minimumCents'] > 0) {
            $groups = array_filter(
                $groups,
                static fn (array $payments): bool => self::totals($payments)['total'] >= $criteria['minimumCents']
            );
        }

        return $groups;
    }

    /**
     * The dates a statement covers: the deposit's date when one deposit was chosen, else the chosen range.
     *
     * @return array{0: string, 1: string}
     */
    public static function period(array $criteria): array
    {
        if ($criteria['depositId'] > 0) {
            $deposit = DepositQuery::create()->findPk($criteria['depositId']);
            if ($deposit !== null) {
                return [$deposit->getDate('Y-m-d'), $deposit->getDate('Y-m-d')];
            }
        }

        return [$criteria['dateStart'], $criteria['dateEnd']];
    }

    /**
     * One entry per family in the shape the giving-statement template takes.
     *
     * @param array<int, Pledge[]> $groups
     */
    public static function families(array $groups, bool $withEnvelope = false): array
    {
        $families = [];
        foreach ($groups as $payments) {
            $family = $payments[0]->getFamily();
            $totals = self::totals($payments);

            $gifts = [];
            foreach ($payments as $pledge) {
                $gifts[] = [
                    'date' => DateTimeUtils::formatDate($pledge->getDate('Y-m-d')),
                    'fund' => $pledge->getDonationFund()?->getName() ?? gettext('Undesignated'),
                    'method' => trim($pledge->getMethod() . ($pledge->getCheckNo() ? ' #' . $pledge->getCheckNo() : '')),
                    'amount' => CurrencyFormatter::format($pledge->getAmount()),
                ];
            }

            $families[] = [
                'salutation' => $family instanceof Family ? $family->getName() : gettext('Dear Friend'),
                'address' => $family instanceof Family ? $family->getMailingAddressLines() : '',
                'envelope' => $withEnvelope && $family instanceof Family ? (string) $family->getEnvelope() : '',
                'total' => CurrencyFormatter::format($totals['total'] / 100),
                'goods_services' => CurrencyFormatter::format($totals['nonDeductible'] / 100),
                'deductible' => CurrencyFormatter::format(($totals['total'] - $totals['nonDeductible']) / 100),
                'disclosure' => $totals['nonDeductible'] > 0 ? '' : gettext('No goods or services were provided in exchange for these contributions, other than intangible religious benefits.'),
                'gifts' => $gifts,
            ];
        }

        return $families;
    }

    /**
     * A statement with no gifts, for a family whose year has none.
     */
    public static function emptyStatement(Family $family, string $salutation): array
    {
        return [
            'salutation' => $salutation,
            'address' => $family->getMailingAddressLines(),
            'envelope' => '',
            'total' => CurrencyFormatter::format(0),
            'goods_services' => CurrencyFormatter::format(0),
            'deductible' => CurrencyFormatter::format(0),
            'disclosure' => gettext('No goods or services were provided in exchange for these contributions, other than intangible religious benefits.'),
            'gifts' => [],
        ];
    }

    /**
     * The data the giving-statement template renders.
     *
     * @param array<int, array<string, mixed>> $families as returned by {@see self::families()}
     */
    public static function document(array $families, string $dateStart, string $dateEnd, bool $remittance = false): array
    {
        $graphic = MpdfRenderer::graphicLetterhead();
        $start = DateTimeUtils::formatDate($dateStart);
        $end = DateTimeUtils::formatDate($dateEnd);

        return [
            'letterhead' => $graphic !== null ? 'graphic' : 'address',
            'letterhead_image' => $graphic,
            'title' => self::isCanadianChurch() ? gettext('Giving statement — not an official donation receipt') : gettext('Giving Statement'),
            'date_range' => $dateStart === $dateEnd ? $start : sprintf(gettext('%s through %s'), $start, $end),
            'opening' => trim(SystemConfig::getValue('sGivingStatementOpening') . ' ' . ($dateStart === $dateEnd ? $start : sprintf(gettext('%s through %s'), $start, $end)) . '.'),
            'closing' => SystemConfig::getValue('sGivingStatementClosing'),
            'questions' => SystemConfig::getValue('sGivingStatementQuestions'),
            'sincerely' => SystemConfig::getValue('sConfirmSincerely'),
            'signer' => SystemConfig::getValue('sTaxSigner'),
            'signature' => TaxSignatureService::hasUploaded() && ($image = TaxSignatureService::image()) !== null ? 'data:image/png;base64,' . base64_encode($image) : null,
            'empty_message' => gettext('No contributions were recorded for this period.'),
            'remittance' => $remittance,
            'families' => $families,
        ];
    }

    /**
     * Rows for the CSV export. Address columns are the mailing address (the flagged
     * second address when there is one) so the export matches the letter that is mailed.
     *
     * @param array<int, Pledge[]> $groups
     * @return array{0: string[], 1: array<int, array<int, string|int|float|null>>}
     */
    public static function csv(array $groups): array
    {
        $headers = [
            'fam_ID', 'fam_Name', 'fam_Address1', 'fam_Address2', 'fam_City', 'fam_State', 'fam_Zip', 'fam_Country', 'fam_envelope',
            'plg_date', 'plg_amount', 'plg_method', 'plg_comment', 'plg_CheckNo', 'fun_Name', 'plg_PledgeOrPayment', 'plg_NonDeductible',
        ];

        $rows = [];
        foreach ($groups as $payments) {
            foreach ($payments as $pledge) {
                $family = $pledge->getFamily();
                $mailing = $family instanceof Family ? $family->getMailingAddressParts() : [];
                $rows[] = [
                    $pledge->getFamId(),
                    $family?->getName() ?? 'Unassigned',
                    $mailing['Address1'] ?? '',
                    $mailing['Address2'] ?? '',
                    $mailing['City'] ?? '',
                    $mailing['State'] ?? '',
                    $mailing['Zip'] ?? '',
                    $mailing['Country'] ?? '',
                    $family?->getEnvelope() ?? '',
                    $pledge->getDate('Y-m-d'),
                    $pledge->getAmount(),
                    $pledge->getMethod(),
                    $pledge->getComment(),
                    $pledge->getCheckNo() ?? '',
                    $pledge->getDonationFund()?->getName() ?? 'Undesignated',
                    $pledge->getPledgeOrPayment(),
                    $pledge->getNondeductible() ?? 0,
                ];
            }
        }

        return [$headers, $rows];
    }

    /**
     * Summary the preview page shows before anything is downloaded.
     *
     * @param array<int, Pledge[]> $groups
     * @return array{families: int, gifts: int, total: string}
     */
    public static function summary(array $groups): array
    {
        $gifts = 0;
        $cents = 0;
        foreach ($groups as $payments) {
            $gifts += count($payments);
            $cents += self::totals($payments)['total'];
        }

        return ['families' => count($groups), 'gifts' => $gifts, 'total' => CurrencyFormatter::format($cents / 100)];
    }

    public static function canadianSampleDocument(): array
    {
        $document = self::sampleDocument();
        $family = $document['families'][0];
        $family['salutation'] = 'Élodie Marie Tremblay (fictional)';
        $family['address'] = "123 rue Exemple\nMontréal, QC H2X 1Y4\nCanada";
        $family['serial'] = '2025-459';
        $family['total'] = CurrencyFormatter::format(150);
        foreach ($family['gifts'] as $index => &$gift) {
            $gift['memo'] = '';
            $gift['amount'] = CurrencyFormatter::format($index === 0 ? 100 : 50);
        }
        unset($gift);
        return array_merge($document, [
            'canadian' => true, 'sample' => true, 'families' => [$family],
            'title' => gettext('Official Receipt / Reçu officiel'),
            'legal_name' => 'Sample Canadian Charity (fictional)',
            'registered_address' => "456 Sample Street\nOttawa, ON K1A 0A1\nCanada",
            'registration' => '123456789RR0001', 'locality' => 'Ottawa',
            'gift_year' => 2025, 'issue_date' => DateTimeUtils::formatDate('2026-01-15'),
            'advantage' => CurrencyFormatter::format(0),
            'duplicate' => false, 'signature' => null, 'sincerely' => gettext('Sincerely'),
            'signer' => 'Sample Authorized Signer (fictional)',
            'setup_problems' => self::canadianSetupProblems(),
        ]);
    }

    /** What a Canadian church still has to fill in before official receipts can be generated. */
    public static function canadianSetupProblems(): array
    {
        $details = self::charityDetails();
        $problems = [];
        foreach (['legal_name' => gettext('Charity Legal Name'), 'registered_address' => gettext('Charity Registered Address'), 'locality' => gettext('Receipt Issue Locality')] as $key => $label) {
            if ($details[$key] === '') {
                $problems[] = $label;
            }
        }
        $registration = strtoupper(preg_replace('/\s+/u', '', $details['registration']));
        if ($registration === '') {
            $problems[] = gettext('Charity Registration Number');
        } elseif (!preg_match('/^[0-9]{9}RR[0-9]{4}$/D', $registration)) {
            $problems[] = gettext('Registration number in the form 123456789RR0001');
        }
        if (trim(SystemConfig::getValue('sTaxSigner')) === '') {
            $problems[] = gettext('Signer');
        }
        if (!TaxSignatureService::hasUploaded()) {
            $problems[] = gettext('Signature image (Admin, Report Settings)');
        }
        if (!in_array(strtoupper(trim(SystemConfig::getValue('sCurrencySymbol'))), self::CAD_SYMBOLS, true)) {
            $problems[] = gettext('Currency symbol $ or CAD');
        }

        return $problems;
    }

    /** Canadian receipts apply when Church Info names Canada as the church country. */
    public static function isCanadianChurch(): bool
    {
        return Countries::toISO(ChurchMetaData::getChurchCountry()) === 'CA';
    }

    /** Charity fields on Statement Settings. The legal name is its own fact (not the church's display name); the rest fall back to Church Info. */
    public static function charityDetails(): array
    {
        $pick = static fn (string $key, string $fallback): string => trim(SystemConfig::getValue($key)) ?: trim($fallback);

        return [
            'legal_name' => trim(SystemConfig::getValue('sCharityLegalName')),
            'registered_address' => $pick('sCharityRegisteredAddress', ChurchMetaData::getChurchFullAddress()),
            'registration' => $pick('sCharityRegistrationNumber', ChurchMetaData::getChurchTaxId()),
            'locality' => $pick('sReceiptIssueLocality', ChurchMetaData::getChurchCity()),
        ];
    }

    public static function canadianOptions(array $input): array
    {
        if (!self::isCanadianChurch()) {
            throw new InvalidArgumentException(gettext('Canadian official receipts require Canada as the church country on Church Info.'));
        }
        foreach (['funds', 'classList', 'DateStart', 'DateEnd', 'deposit'] as $key) {
            if (!empty($input[$key])) {
                throw new InvalidArgumentException(gettext('Canadian receipts require the entire calendar year and all funds, without other filters.'));
            }
        }
        if (self::exactCents(self::scalar($input['minimum'] ?? '0')) !== 0 || self::scalar($input['datetype'] ?? 'Payment') !== 'Payment'
            || self::scalar($input['remittance'] ?? 'no') !== 'no' || self::scalar($input['output'] ?? 'pdf') !== 'pdf') {
            throw new InvalidArgumentException(gettext('Canadian receipts require the entire calendar year and all funds, without other filters.'));
        }
        $year = filter_var($input['calendarYear'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 1991 || $year > (int) DateTimeUtils::getToday()->format('Y')) {
            throw new InvalidArgumentException(gettext('Choose a valid donation calendar year.'));
        }
        $ids = self::intList($input['family'] ?? []);
        if ($ids === []) {
            throw new InvalidArgumentException(gettext('Select the families to review.'));
        }
        $copy = self::scalar($input['copyKind'] ?? 'original');
        if (!in_array($copy, ['original', 'duplicate'], true)) {
            throw new InvalidArgumentException(gettext('Choose original or duplicate.'));
        }
        return ['year' => $year, 'familyIds' => $ids, 'copyKind' => $copy];
    }

    /**
     * The selected families with everything the review step shows: the address,
     * a suggested donor name, the gift count and total, and any problem that
     * would stop that family's receipt. The issue date is not known yet, so the
     * date check waits for the download.
     */
    public static function canadianReview(array $options): array
    {
        $groups = self::canadianPayments($options['year'], $options['familyIds']);
        $found = self::canadianFamilies($options['familyIds']);
        $holders = self::envelopeHolders($found);
        $families = [];
        foreach ($options['familyIds'] as $id) {
            $family = $found[$id] ?? null;
            if (!$family instanceof Family) {
                throw new InvalidArgumentException(gettext('A selected family no longer exists.'));
            }
            $payments = $groups[$id] ?? [];
            $cents = 0;
            foreach ($payments as $payment) {
                try {
                    $cents += self::exactCents($payment->getAmount());
                } catch (InvalidArgumentException) {
                    // canadianFamilyProblems() reports the invalid amount.
                }
            }
            $families[] = [
                'id' => $id,
                'name' => $family->getName(),
                'inactive' => !$family->isActive(),
                'snapshot' => self::canadianSnapshot($family, $payments),
                'address' => $family->getMailingAddressLines(),
                'suggestedName' => self::canadianSuggestedName($family),
                'gifts' => count($payments),
                'total' => CurrencyFormatter::format($cents / 100),
                'problems' => self::canadianFamilyProblems($family, $payments, null, $holders),
            ];
        }
        return $families;
    }

    /** @return array<int, Family> the selected families by ID, in one query */
    private static function canadianFamilies(array $ids): array
    {
        $families = [];
        foreach (FamilyQuery::create()->filterById($ids)->find() as $family) {
            $families[(int) $family->getId()] = $family;
        }

        return $families;
    }

    /**
     * How many families hold each selected envelope number, across all families,
     * in one grouped query.
     *
     * @param array<int, Family> $families
     *
     * @return array<int, int>
     */
    private static function envelopeHolders(array $families): array
    {
        $envelopes = array_values(array_filter(array_map(static fn (Family $family): int => (int) $family->getEnvelope(), $families), static fn (int $envelope): bool => $envelope > 0));
        $counts = [];
        if ($envelopes === []) {
            return $counts;
        }
        foreach (FamilyQuery::create()->filterByEnvelope($envelopes)->select(['Envelope'])->withColumn('COUNT(*)', 'Holders')->groupByEnvelope()->find() as $row) {
            $counts[(int) $row['Envelope']] = (int) $row['Holders'];
        }

        return $counts;
    }

    /** Fingerprint of what the treasurer reviewed, so a download can tell the gifts or address changed since. */
    private static function canadianSnapshot(Family $family, array $payments): string
    {
        return hash('sha256', json_encode([
            $family->getMailingAddressLines(),
            array_map(static fn (Pledge $payment): array => [$payment->getId(), $payment->getAmount(), $payment->getNondeductible(), $payment->getDate('Y-m-d')], $payments),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Living heads of household and spouses, as the treasurer's starting point
     * for the verified donor name. The treasurer can edit it.
     */
    public static function canadianSuggestedName(Family $family): string
    {
        $names = [];
        foreach (array_merge($family->getHeadPeople(), $family->getSpousePeople()) as $person) {
            $name = trim($person->getFullName());
            if (!$person->isDeceased() && $name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return implode(' & ', $names);
    }

    /**
     * Why this family cannot get a receipt, as readable sentences. Empty when it can.
     *
     * @param Pledge[] $payments the family's payments in the receipt year
     */
    public static function canadianFamilyProblems(Family $family, array $payments, ?string $issueDate = null, ?array $envelopeHolders = null): array
    {
        $problems = [];
        if ($payments === []) {
            $problems[] = gettext('There are no payments in the selected calendar year.');
        }
        $parts = $family->getMailingAddressParts();
        if (trim((string) ($parts['Country'] ?? '')) === '') {
            $parts['Country'] = trim(SystemConfig::getValue('sDefaultCountry'));
        }
        foreach (['Address1', 'City', 'State', 'Zip', 'Country'] as $part) {
            if (trim((string) ($parts[$part] ?? '')) === '') {
                $problems[] = gettext('The mailing address is incomplete. A street, city, province, and postal code are required.');
                break;
            }
        }
        $envelope = (int) $family->getEnvelope();
        $holders = $envelopeHolders[$envelope] ?? ($envelopeHolders === null && $envelope > 0 ? FamilyQuery::create()->filterByEnvelope($envelope)->count() : 0);
        if ($envelope > 0 && $holders > 1) {
            $problems[] = gettext('Shared-envelope conflict: another family currently has this envelope number.');
        }
        foreach ($payments as $payment) {
            try {
                $cents = self::exactCents($payment->getAmount());
                $advantage = self::exactCents($payment->getNondeductible());
            } catch (InvalidArgumentException $error) {
                $problems[] = $error->getMessage();
                continue;
            }
            if ($cents <= 0) {
                $problems[] = gettext('A payment is zero or negative.');
            }
            if ($advantage !== 0) {
                $problems[] = gettext('A payment has a non-deductible amount. Canadian receipts here support gifts with no advantage only.');
            }
            if ($payment->getProblem()) {
                $problems[] = gettext('A payment is marked as a problem.');
            }
            if (in_array($payment->getMethod(), ['CREDITCARD', 'BANKDRAFT'], true) && !self::depositClosed($payment)) {
                $problems[] = gettext('A credit card or bank draft payment is in a deposit that is not closed yet. Close the deposit once the payment has cleared.');
            }
            $paidOn = $payment->getDate('Y-m-d');
            if (!is_string($paidOn)) {
                $problems[] = gettext('A payment has no date.');
            } elseif ($issueDate !== null && $paidOn > $issueDate) {
                $problems[] = gettext('A payment is dated after the issue date.');
            }
        }
        return array_values(array_unique($problems));
    }

    /**
     * Card and bank-draft gifts count as cleared once their deposit is closed.
     */
    private static function depositClosed(Pledge $payment): bool
    {
        $deposit = $payment->getDepId() ? DepositQuery::create()->findPk($payment->getDepId()) : null;

        return $deposit !== null && (bool) $deposit->getClosed();
    }

    /**
     * @return array<int, Pledge[]> the year's payments for the families, by family ID
     */
    private static function canadianPayments(int $year, array $familyIds): array
    {
        $groups = [];
        foreach (PledgeQuery::create()->filterForTaxReport("$year-01-01", "$year-12-31", [], $familyIds)->find() as $payment) {
            $groups[(int) $payment->getFamId()][] = $payment;
        }
        return $groups;
    }

    public static function canadianDocument(array $input): array
    {
        $options = self::canadianOptions($input);
        foreach (['donorNames', 'donorVerified', 'mixedDonors'] as $key) {
            if (!is_array($input[$key] ?? null)) {
                throw new InvalidArgumentException(gettext('Verify the full donor name and address for every family. Mixed-donor payment sets must be handled outside this feature.'));
            }
        }
        $issueDate = DateTimeUtils::parseIsoDate(self::scalar($input['issueDate'] ?? ''), 1991);
        if ($issueDate === '' || $issueDate > DateTimeUtils::getTodayDate()) {
            throw new InvalidArgumentException(gettext('Supply an issue date no later than today.'));
        }
        foreach (['signerAuthorized', 'monetaryOnly', 'receivedDatesVerified', 'noOverlap', 'retainOriginal'] as $key) {
            if (($input[$key] ?? '') !== '1') {
                throw new InvalidArgumentException(gettext('Complete every receipt acknowledgment before downloading.'));
            }
        }
        if ($options['copyKind'] === 'duplicate' && ($input['duplicateCompared'] ?? '') !== '1') {
            throw new InvalidArgumentException(gettext('Compare the duplicate with the retained original and confirm all receipt information is unchanged.'));
        }
        $currency = strtoupper(trim(SystemConfig::getValue('sCurrencySymbol')));
        // canadianOptions() already requires a Canadian church, so a plain $ means CAD here.
        if (!in_array($currency, self::CAD_SYMBOLS, true)) {
            throw new InvalidArgumentException(gettext('The system currency symbol must be $ or identify CAD for Canadian receipts.'));
        }
        $legal = self::charityDetails();
        $legal['signer'] = trim(SystemConfig::getValue('sTaxSigner'));
        if (in_array('', $legal, true)) {
            throw new InvalidArgumentException(gettext('Complete the charity legal name, registered address, registration number, issue locality, and printed signer.'));
        }
        $legal['registration'] = strtoupper(preg_replace('/\s+/u', '', $legal['registration']));
        if (!preg_match('/^[0-9]{9}RR[0-9]{4}$/D', $legal['registration'])) {
            throw new InvalidArgumentException(gettext('The Canadian registration number must have the format 123456789RR0001. This does not verify registration status.'));
        }
        if (!TaxSignatureService::hasUploaded() || ($image = TaxSignatureService::image()) === null) {
            throw new InvalidArgumentException(gettext('Upload the authorized tax signature in Report Settings.'));
        }
        $year = $options['year'];
        $groups = self::canadianPayments($year, $options['familyIds']);
        $found = self::canadianFamilies($options['familyIds']);
        $holders = self::envelopeHolders($found);
        $families = [];
        foreach (self::canadianReview($options) as $review) {
            $id = $review['id'];
            $name = trim(self::scalar($input['donorNames'][$id] ?? ''));
            if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 1 || mb_strlen($name) > 300
                || ($input['donorVerified'][$id] ?? '') !== '1' || ($input['mixedDonors'][$id] ?? '') !== 'no') {
                throw new InvalidArgumentException(sprintf(gettext('%s: enter the verified full donor name(s), confirm the donors, and confirm one donor set. Mixed-donor payment sets must be handled outside this feature.'), $review['name']));
            }
            $payments = $groups[$id] ?? [];
            $family = $found[$id];
            if (!is_array($input['snapshot'] ?? null) || ($input['snapshot'][$id] ?? null) !== $review['snapshot']) {
                throw new InvalidArgumentException(sprintf(gettext('%s: the gifts or mailing address changed since the review. Choose Check Again and confirm the donor again.'), $review['name']));
            }
            $problems = self::canadianFamilyProblems($family, $payments, $issueDate, $holders);
            if ($problems !== []) {
                throw new InvalidArgumentException(sprintf(gettext('%s: %s No receipt was generated.'), $review['name'], implode(' ', $problems)));
            }
            $envelope = (int) $family->getEnvelope();
            $total = 0;
            $gifts = [];
            foreach ($payments as $payment) {
                $cents = self::exactCents($payment->getAmount());
                $paidOn = $payment->getDate('Y-m-d');
                $total += $cents;
                $gifts[] = ['date' => DateTimeUtils::formatDate($paidOn),
                    'fund' => $payment->getDonationFund()?->getName() ?? gettext('Undesignated'),
                    'memo' => ($input['includeMemo'] ?? '') === '1' ? $payment->getComment() : '',
                    'amount' => CurrencyFormatter::format($cents / 100)];
            }
            $families[] = ['salutation' => $name, 'address' => $review['address'],
                'serial' => $year . '-' . ($envelope > 0 ? $envelope : 'F' . $id),
                'total' => CurrencyFormatter::format($total / 100), 'gifts' => $gifts];
        }
        return array_merge(self::document($families, "$year-01-01", "$year-12-31"), [
            'canadian' => true, 'title' => gettext('Official Receipt / Reçu officiel'),
            'legal_name' => $legal['legal_name'], 'registered_address' => $legal['registered_address'],
            'registration' => $legal['registration'], 'locality' => $legal['locality'],
            'gift_year' => $year, 'issue_date' => DateTimeUtils::formatDate($issueDate),
            'advantage' => CurrencyFormatter::format(0),
            'duplicate' => $options['copyKind'] === 'duplicate', 'sincerely' => SystemConfig::getValue('sReceiptClosing'),
            'signature' => 'data:image/png;base64,' . base64_encode($image), 'generated_on' => '',
        ]);
    }

    private static function exactCents(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^(-?)([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D', $value, $match)) {
            throw new InvalidArgumentException(gettext('A payment has an invalid monetary amount.'));
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return ($match[1] === '-' ? -1 : 1) * $cents;
    }

    public static function fileLabel(int $year, string $familyName = ''): string
    {
        return $familyName === '' ? 'GivingStatements-' . $year : 'GivingStatement-' . $year . '-' . $familyName;
    }

    /**
     * Amounts are summed in cents so a long list of gifts never drifts by a fraction of a cent.
     *
     * @param Pledge[] $payments
     * @return array{total: int, nonDeductible: int}
     */
    private static function totals(array $payments): array
    {
        $total = 0;
        $nonDeductible = 0;
        foreach ($payments as $pledge) {
            $total += (int) round((float) $pledge->getAmount() * 100);
            $nonDeductible += (int) round((float) $pledge->getNondeductible() * 100);
        }

        return ['total' => $total, 'nonDeductible' => $nonDeductible];
    }

    /** Query values arrive as strings or arrays; anything that is not a scalar reads as empty. */
    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }


    /**
     * @return int[]
     */
    private static function intList(mixed $values): array
    {
        $ids = [];
        foreach ((array) $values as $value) {
            foreach (explode(',', self::scalar($value)) as $part) {
                $id = InputUtils::filterInt($part);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }
}
