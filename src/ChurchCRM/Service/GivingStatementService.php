<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Pledge;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\TaxYearUtils;

/**
 * Year-end giving statements: the Finance report and the Member Portal build the same
 * document from here.
 */
class GivingStatementService
{
    private const FIRST_YEAR = 1990;

    /**
     * Turn untrusted query values into the criteria every other method takes.
     *
     * @return array{dateStart: string, dateEnd: string, depositId: int, byDepositDate: bool, minimumCents: int, fundIds: int[], familyIds: int[], classificationIds: int[], remittance: bool}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $currentEndYear = TaxYearUtils::currentEndYear();
        $year = InputUtils::filterInt(self::scalar($query['year'] ?? ''));
        $familyIds = self::intList($query['family'] ?? []);
        $familyId = InputUtils::filterInt(self::scalar($query['familyId'] ?? ''));
        if ($familyId > 0) {
            $familyIds[] = $familyId;
        }

        $dateStart = self::validDate(self::scalar($query['DateStart'] ?? ''));
        $dateEnd = self::validDate(self::scalar($query['DateEnd'] ?? ''));
        if ($year >= self::FIRST_YEAR && $year <= $currentEndYear) {
            $fy = TaxYearUtils::datesForEndYear($year);
            $dateStart = $fy['startDate'];
            $dateEnd = $fy['endDate'];
        }
        if ($dateStart === '' && $dateEnd === '') {
            $fy = TaxYearUtils::datesForEndYear($currentEndYear - 1);
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
     * The giving-statement PDF: the one document the staff download, the emailed copy and the portal download all send.
     *
     * @param array<int, array<string, mixed>> $families as returned by {@see self::families()}
     */
    public static function pdf(array $families, string $dateStart, string $dateEnd, bool $remittance = false): string
    {
        return (new MpdfRenderer())->renderToString('tax-report', self::document($families, $dateStart, $dateEnd, $remittance));
    }

    /**
     * The data the giving-statement template renders.
     *
     * @param array<int, array<string, mixed>> $families as returned by {@see self::families()}
     */
    public static function document(array $families, string $dateStart, string $dateEnd, bool $remittance = false): array
    {
        $graphic = self::graphicLetterhead();
        $period = TaxYearUtils::describePeriod($dateStart, $dateEnd);

        return [
            'letterhead' => $graphic !== null ? 'graphic' : 'address',
            'letterhead_image' => $graphic,
            'title' => gettext('Giving Statement'),
            'date_range' => $period,
            'opening' => trim(SystemConfig::getValue('sTaxReport1') . ' ' . $period . '.'),
            'closing' => SystemConfig::getValue('sTaxReport2'),
            'questions' => SystemConfig::getValue('sTaxReport3'),
            'sincerely' => SystemConfig::getValue('sConfirmSincerely'),
            'signer' => SystemConfig::getValue('sTaxSigner'),
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

    private static function graphicLetterhead(): ?string
    {
        $path = trim((string) SystemConfig::getValue('bDirLetterHead'));
        if ($path !== '' && (is_readable($path) || preg_match('#^https://#i', $path))) {
            return $path;
        }

        return null;
    }

    /** Query values arrive as strings or arrays; anything that is not a scalar reads as empty. */
    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function validDate(string $value): string
    {
        $date = InputUtils::filterDate($value);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < self::FIRST_YEAR) {
            return '';
        }

        return $date;
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
