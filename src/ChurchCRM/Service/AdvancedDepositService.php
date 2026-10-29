<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Advanced Deposit Report: payments (never pledges, ADR 0004 section 6) filtered by date, deposit,
 * fund, family, classification and method, grouped by deposit, fund or family.
 * Callers authorize: the Finance routes sit behind FinanceRoleAuthMiddleware.
 */
class AdvancedDepositService
{
    private const SORTS = ['deposit', 'fund', 'family'];
    private const DETAIL_LEVELS = ['detail', 'medium', 'summary'];

    /** Check-column text for non-check payments; one entry per method that differs from its code. */
    private const METHOD_LABELS = ['CREDITCARD' => 'CREDIT', 'BANKDRAFT' => 'DRAFT'];

    /** Grouping levels per sort: the outer group, then the group inside it. */
    private const LEVELS = [
        'deposit' => ['depId', 'fundId'],
        'fund' => ['fundId', 'famId'],
        'family' => ['famId', 'fundId'],
    ];

    /**
     * @return array{sort: string, detail: string, datetype: string, dateStart: string, dateEnd: string, depositId: int, fundIds: int[], familyIds: int[], classIds: int[], methods: string[]}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $sort = self::scalar($query, 'sort');
        $detail = self::scalar($query, 'detail_level');
        $depositId = InputUtils::filterInt($query['deposit'] ?? '');

        $dateStart = self::validDate($query['DateStart'] ?? '');
        $dateEnd = self::validDate($query['DateEnd'] ?? '');
        if ($dateStart === '' && $dateEnd === '') {
            $dateStart = $dateEnd = DateTimeUtils::getTodayDate();
        }
        $dateStart = $dateStart !== '' ? $dateStart : $dateEnd;
        $dateEnd = $dateEnd !== '' ? $dateEnd : $dateStart;
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        // The form says a chosen deposit overrides the dates.
        if ($depositId > 0) {
            $dateStart = $dateEnd = '';
        }

        return [
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'deposit',
            'detail' => in_array($detail, self::DETAIL_LEVELS, true) ? $detail : 'detail',
            'datetype' => self::scalar($query, 'datetype') === 'Payment' ? 'Payment' : 'Deposit',
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'depositId' => $depositId,
            'fundIds' => self::ids($query['funds'] ?? []),
            'familyIds' => self::ids($query['family'] ?? []),
            'classIds' => self::ids($query['classList'] ?? []),
            'methods' => self::methods($query['method'] ?? []),
        ];
    }

    /**
     * One entry per payment, in the order the sort asks for.
     *
     * @return array<int, array<string, int|string>>
     */
    public static function records(array $criteria): array
    {
        $familyIds = $criteria['familyIds'];
        if ($criteria['classIds'] !== []) {
            $classified = array_map('intval', FamilyQuery::create()
                ->usePersonQuery()
                    ->filterByClsId($criteria['classIds'], Criteria::IN)
                ->endUse()
                ->select('Id')
                ->distinct()
                ->find()
                ->toArray());
            $familyIds = $familyIds === [] ? $classified : array_values(array_intersect($familyIds, $classified));
            if ($familyIds === []) {
                return [];
            }
        }

        $pledges = PledgeQuery::create()
            ->filterForAdvancedDeposit(
                $criteria['dateStart'],
                $criteria['dateEnd'],
                $criteria['fundIds'],
                $familyIds,
                $criteria['methods'],
                [],
                $criteria['datetype'],
                $criteria['sort']
            );
        if ($criteria['depositId'] > 0) {
            $pledges->filterByDepId($criteria['depositId']);
        }

        $records = [];
        foreach ($pledges->find() as $pledge) {
            $depositDate = (string) $pledge->getVirtualColumn('DepositDate');
            $records[] = [
                'famId' => (int) $pledge->getFamId(),
                'famName' => (string) ($pledge->getVirtualColumn('FamilyName') ?? '') ?: gettext('Anonymous'),
                'address1' => (string) $pledge->getVirtualColumn('FamilyAddress1'),
                'address2' => (string) $pledge->getVirtualColumn('FamilyAddress2'),
                'city' => (string) $pledge->getVirtualColumn('FamilyCity'),
                'state' => (string) $pledge->getVirtualColumn('FamilyState'),
                'zip' => (string) $pledge->getVirtualColumn('FamilyZip'),
                'country' => (string) $pledge->getVirtualColumn('FamilyCountry'),
                'date' => (string) $pledge->getDate('Y-m-d'),
                'cents' => (int) round(((float) $pledge->getAmount()) * 100),
                'method' => (string) $pledge->getMethod(),
                'memo' => (string) $pledge->getComment(),
                'depId' => (int) $pledge->getDepId(),
                'depDate' => $depositDate,
                'checkNo' => (string) $pledge->getCheckNo(),
                'fundId' => (int) $pledge->getFundId(),
                'fundName' => (string) ($pledge->getVirtualColumn('FundName') ?? '') ?: gettext('Undesignated'),
            ];
        }

        return $records;
    }

    /**
     * Template data: groups with preformatted rows and totals.
     *
     * @param array<int, array<string, int|string>> $records
     */
    public static function document(array $records, array $criteria): array
    {
        [$outer, $inner] = self::LEVELS[$criteria['sort']];
        $detail = $criteria['detail'];

        $grouped = [];
        foreach ($records as $record) {
            $grouped[$record[$outer]][$record[$inner]][] = $record;
        }

        $groups = [];
        foreach ($grouped as $subgroups) {
            $subgroupViews = [];
            $groupRecords = [];
            foreach ($subgroups as $subRecords) {
                $groupRecords = array_merge($groupRecords, $subRecords);
                $subgroupViews[] = [
                    'rows' => $detail === 'detail' ? array_map(fn (array $r): array => self::row($r), $subRecords) : [],
                    'total' => self::total(self::label($inner, $subRecords[0]), $subRecords),
                ];
            }
            $groups[] = [
                'title' => self::label($outer, $groupRecords[0]),
                'subgroups' => $detail === 'summary' ? [] : $subgroupViews,
                'total' => self::total(self::label($outer, $groupRecords[0]) . ' – ' . gettext('Total'), $groupRecords),
            ];
        }

        $byFund = [];
        foreach ($records as $record) {
            $byFund[$record['fundId']]['name'] = $record['fundName'];
            $byFund[$record['fundId']]['cents'] = ($byFund[$record['fundId']]['cents'] ?? 0) + $record['cents'];
        }
        usort($byFund, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return [
            'summary_lines' => self::summaryLines($criteria),
            'columns' => self::columns($criteria['sort']),
            'detail' => $detail === 'detail',
            'groups' => $groups,
            'report_total' => self::total(gettext('Total'), $records),
            'fund_totals' => array_map(fn (array $fund): array => ['name' => $fund['name'], 'amount' => self::money($fund['cents'])], $byFund),
        ];
    }

    /**
     * @param array<int, array<string, int|string>> $records
     * @return array{0: string[], 1: array<int, array<int, int|string>>}
     */
    public static function csv(array $records): array
    {
        $headers = ['fam_ID', 'fam_Name', 'fam_Address1', 'fam_Address2', 'fam_City', 'fam_State', 'fam_Zip', 'fam_Country', 'plg_date', 'plg_amount', 'plg_method', 'plg_comment', 'plg_depID', 'plg_CheckNo', 'fun_ID', 'fun_Name', 'dep_Date'];
        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                $r['famId'], $r['famName'], $r['address1'], $r['address2'], $r['city'], $r['state'], $r['zip'], $r['country'],
                $r['date'], number_format($r['cents'] / 100, 2, '.', ''), $r['method'], $r['memo'], $r['depId'] ?: '', $r['checkNo'], $r['fundId'], $r['fundName'], $r['depDate'],
            ];
        }

        return [$headers, $rows];
    }

    public static function contentDisposition(): string
    {
        $disposition = SystemConfig::getIntValue('iPDFOutputType') === 1 ? 'attachment' : 'inline';

        return $disposition . '; filename="DepositReport-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf"';
    }

    /**
     * @return array<int, array{key: string, label: string, align: string}>
     */
    private static function columns(string $sort): array
    {
        $second = $sort === 'deposit'
            ? ['key' => 'fund', 'label' => gettext('Fund')]
            : ['key' => 'deposit', 'label' => gettext('Deposit')];
        $third = $sort === 'family'
            ? ['key' => 'fund', 'label' => gettext('Fund')]
            : ['key' => 'family', 'label' => gettext('Family')];

        return [
            ['key' => 'check', 'label' => gettext('Check #'), 'align' => 'right'],
            $second + ['align' => 'left'],
            $third + ['align' => 'left'],
            ['key' => 'memo', 'label' => gettext('Comment'), 'align' => 'left'],
            ['key' => 'amount', 'label' => gettext('Amount'), 'align' => 'right'],
        ];
    }

    /**
     * @param array<string, int|string> $r
     * @return array<string, string>
     */
    private static function row(array $r): array
    {
        $isCheck = $r['method'] === 'CHECK';

        return [
            'check' => $isCheck ? (string) $r['checkNo'] : (self::METHOD_LABELS[$r['method']] ?? (string) $r['method']),
            'fund' => (string) $r['fundName'],
            'deposit' => $r['depId'] > 0 ? sprintf(gettext('Deposit #%d'), $r['depId']) . ' ' . $r['depDate'] : '',
            'family' => self::label('famId', $r),
            'memo' => (string) $r['memo'],
            'amount' => self::money($r['cents']),
        ];
    }

    /**
     * @param array<string, int|string> $r
     */
    private static function label(string $level, array $r): string
    {
        if ($level === 'depId') {
            return $r['depId'] > 0 ? sprintf(gettext('Deposit #%d'), $r['depId']) . ' (' . $r['depDate'] . ')' : gettext('Deposit');
        }
        if ($level === 'fundId') {
            return (string) $r['fundName'];
        }

        return $r['address1'] !== '' ? $r['famName'] . ' - ' . $r['address1'] : (string) $r['famName'];
    }

    /**
     * @param array<int, array<string, int|string>> $records
     * @return array{label: string, count: string, amount: string}
     */
    private static function total(string $label, array $records): array
    {
        $count = count($records);

        return [
            'label' => $label,
            'count' => $count . ' ' . ($count > 1 ? gettext('items') : gettext('item')),
            'amount' => self::money(array_sum(array_column($records, 'cents'))),
        ];
    }

    /**
     * @return string[]
     */
    private static function summaryLines(array $criteria): array
    {
        $sortLabels = ['deposit' => gettext('Deposit'), 'fund' => gettext('Fund'), 'family' => gettext('Family')];
        $lines = [gettext('Sort Data by') . ': ' . $sortLabels[$criteria['sort']]];

        if ($criteria['depositId'] === 0) {
            $dateLabel = $criteria['datetype'] === 'Payment' ? gettext('Payment Date') : gettext('Deposit Date');
            $lines[] = $dateLabel . ': ' . DateTimeUtils::formatDate($criteria['dateStart']) . ' – ' . DateTimeUtils::formatDate($criteria['dateEnd']);
        }

        $filters = [];
        if ($criteria['depositId'] > 0) {
            $filters[] = gettext('Filter by Deposit') . ': #' . $criteria['depositId'];
        }
        if ($criteria['classIds'] !== []) {
            $filters[] = gettext('Classification');
        }
        if ($criteria['familyIds'] !== []) {
            $filters[] = gettext('Filter by Family');
        }
        if ($criteria['fundIds'] !== []) {
            $filters[] = gettext('Filter by Fund');
        }
        if ($criteria['methods'] !== []) {
            $filters[] = gettext('Filter by Payment Type');
        }
        if ($filters !== []) {
            $lines[] = implode(', ', $filters);
        }

        return $lines;
    }

    private static function money(int $cents): string
    {
        return CurrencyFormatter::format($cents / 100);
    }

    private static function scalar(array $query, string $key): string
    {
        return is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';
    }

    /**
     * @return int[]
     */
    private static function ids(mixed $values): array
    {
        $ids = array_map(fn (mixed $v): int => is_scalar($v) ? InputUtils::filterInt((string) $v) : 0, is_array($values) ? $values : []);

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /**
     * @return string[]
     */
    private static function methods(mixed $values): array
    {
        $methods = [];
        foreach (is_array($values) ? $values : [] as $value) {
            // '0' is the "All Methods" option on the form.
            if (is_string($value) && preg_match('/^[A-Z_]{1,20}$/', $value) === 1) {
                $methods[] = $value;
            }
        }

        return array_values(array_unique($methods));
    }

    private static function validDate(mixed $value): string
    {
        $date = InputUtils::filterDate(is_scalar($value) ? (string) $value : '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1900) {
            return '';
        }

        return $date;
    }
}
