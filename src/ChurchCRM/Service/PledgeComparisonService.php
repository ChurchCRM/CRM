<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class PledgeComparisonService
{
    /**
     * @return array<int, array{id: int, label: string}>
     */
    public static function fiscalYears(): array
    {
        $currentFyId = FiscalYearUtils::getCurrentFiscalYearId();
        $oldestPledge = PledgeQuery::create()
            ->orderByFyId()
            ->select(['FyId'])
            ->findOne();
        $oldestFyId = $oldestPledge !== null ? (int) $oldestPledge : $currentFyId;

        return FiscalYearUtils::buildFiscalYearList(min($oldestFyId, $currentFyId - 1));
    }

    /**
     * @return array{firstFyId: int, secondFyId: int}
     */
    public static function criteriaFromQuery(array $query): array
    {
        $currentFyId = FiscalYearUtils::getCurrentFiscalYearId();
        $availableFyIds = array_column(self::fiscalYears(), 'id');
        $firstFyId = InputUtils::filterInt($query['firstFY'] ?? '');
        $secondFyId = InputUtils::filterInt($query['secondFY'] ?? '');

        if (!in_array($firstFyId, $availableFyIds, true)) {
            $firstFyId = $currentFyId;
        }
        if (!in_array($secondFyId, $availableFyIds, true) || $secondFyId === $firstFyId) {
            $secondFyId = $firstFyId === $currentFyId
                ? ($currentFyId > 1 ? $currentFyId - 1 : $currentFyId + 1)
                : $currentFyId;
        }

        return ['firstFyId' => $firstFyId, 'secondFyId' => $secondFyId];
    }

    /**
     * @return array{
     *   rows: array<int, array{family: string, first: string, second: string}>,
     *   totals: array{first: string, second: string}
     * }
     */
    public static function report(int $firstFyId, int $secondFyId): array
    {
        $amounts = [];
        foreach (PledgeQuery::create()
            ->filterByPledgeOrPayment('Pledge')
            ->filterByFyId([$firstFyId, $secondFyId], Criteria::IN)
            ->find() as $pledge
        ) {
            $familyId = (int) $pledge->getFamId();
            $fiscalYearId = (int) $pledge->getFyId();
            $amounts[$familyId][$fiscalYearId] = ($amounts[$familyId][$fiscalYearId] ?? 0)
                + (int) round((float) $pledge->getAmount() * 100);
        }

        $familyIds = array_keys($amounts);
        $rows = [];
        $totals = ['first' => 0, 'second' => 0];
        if ($familyIds === []) {
            return [
                'rows' => [],
                'totals' => [
                    'first' => CurrencyFormatter::format(0),
                    'second' => CurrencyFormatter::format(0),
                ],
            ];
        }

        foreach (FamilyQuery::create()->filterById($familyIds, Criteria::IN)->orderByName()->find() as $family) {
            $familyAmounts = $amounts[(int) $family->getId()];
            $first = $familyAmounts[$firstFyId] ?? 0;
            $second = $familyAmounts[$secondFyId] ?? 0;
            if ($first === 0 && $second === 0) {
                continue;
            }

            $rows[] = [
                'family' => (string) $family->getName(),
                'first' => CurrencyFormatter::format($first / 100),
                'second' => CurrencyFormatter::format($second / 100),
            ];
            $totals['first'] += $first;
            $totals['second'] += $second;
        }

        return [
            'rows' => $rows,
            'totals' => [
                'first' => CurrencyFormatter::format($totals['first'] / 100),
                'second' => CurrencyFormatter::format($totals['second'] / 100),
            ],
        ];
    }
}
