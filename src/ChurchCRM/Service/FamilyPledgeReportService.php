<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Classification;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Data for the "Pledge Summary By Family" PDF: one row per family and fund that has pledges in the
 * fiscal year, with what was paid against them and what is still owed (never below zero).
 */
class FamilyPledgeReportService
{
    /**
     * @param int[] $classificationIds families must have a member in one of these and none in the other classifications
     * @param int[] $familyIds         only these families
     * @param int[] $fundIds           only pledges and payments to these funds
     * @return array{rows: array<int, array<string, string>>, totals: array<string, string>, funds: string[]}
     */
    public static function report(int $fiscalYearId, array $classificationIds = [], array $familyIds = [], array $fundIds = []): array
    {
        $familyQuery = FamilyQuery::create()->orderByName();
        if ($familyIds !== []) {
            $familyQuery->filterById($familyIds, Criteria::IN);
        }
        $classifiedFamilyIds = self::familiesByClassification($classificationIds);
        if ($classifiedFamilyIds !== null) {
            $familyQuery->filterById($classifiedFamilyIds, Criteria::IN);
        }
        $families = [];
        foreach ($familyQuery->find() as $family) {
            $families[(int) $family->getId()] = $family;
        }

        $funds = [];
        foreach (DonationFundQuery::create()->orderById()->find() as $fund) {
            $funds[(int) $fund->getId()] = (string) $fund->getName();
        }

        $entryQuery = PledgeQuery::create()->filterByFyId($fiscalYearId)->orderByDate();
        if ($fundIds !== []) {
            $entryQuery->filterByFundId($fundIds, Criteria::IN);
        }

        $byFamilyFund = [];
        foreach ($entryQuery->find() as $entry) {
            $familyId = (int) $entry->getFamId();
            if (!isset($families[$familyId])) {
                continue;
            }
            $fundId = (int) $entry->getFundId();
            $byFamilyFund[$familyId][$fundId] ??= ['pledged' => 0.0, 'paid' => 0.0, 'method' => ''];
            if ($entry->getPledgeOrPayment() === 'Pledge') {
                $byFamilyFund[$familyId][$fundId]['pledged'] += (float) $entry->getAmount();
                $byFamilyFund[$familyId][$fundId]['method'] = (string) $entry->getMethod();
            } else {
                $byFamilyFund[$familyId][$fundId]['paid'] += (float) $entry->getAmount();
            }
        }

        $rows = [];
        $total = ['pledged' => 0.0, 'paid' => 0.0, 'owed' => 0.0];
        foreach ($families as $familyId => $family) {
            foreach ($funds as $fundId => $fundName) {
                $cell = $byFamilyFund[$familyId][$fundId] ?? null;
                if ($cell === null || $cell['pledged'] <= 0) {
                    continue;
                }
                $owed = max(0.0, $cell['pledged'] - $cell['paid']);
                $rows[] = [
                    'name' => (string) $family->getSalutation(),
                    'method' => $cell['method'],
                    'fund' => $fundName,
                    'pledged' => CurrencyFormatter::format($cell['pledged']),
                    'paid' => CurrencyFormatter::format($cell['paid']),
                    'owed' => CurrencyFormatter::format($owed),
                ];
                $total['pledged'] += $cell['pledged'];
                $total['paid'] += $cell['paid'];
                $total['owed'] += $owed;
            }
        }

        return [
            'rows' => $rows,
            'totals' => array_map(static fn (float $amount): string => CurrencyFormatter::format($amount), $total),
            'funds' => array_values(array_intersect_key($funds, array_flip($fundIds))),
        ];
    }

    /**
     * @param int[] $classificationIds
     * @return int[]|null null when no classification filter applies
     */
    private static function familiesByClassification(array $classificationIds): ?array
    {
        if ($classificationIds === []) {
            return null;
        }

        $otherClassificationIds = [];
        foreach (Classification::getAll() as $option) {
            $optionId = (int) $option->getOptionId();
            if (!in_array($optionId, $classificationIds, true)) {
                $otherClassificationIds[] = $optionId;
            }
        }

        $included = self::familiesWithClassification($classificationIds);
        $excluded = $otherClassificationIds === [] ? [] : self::familiesWithClassification($otherClassificationIds);

        return array_values(array_diff($included, $excluded));
    }

    /**
     * @param int[] $classificationIds
     * @return int[]
     */
    private static function familiesWithClassification(array $classificationIds): array
    {
        $ids = PersonQuery::create()
            ->filterByClsId($classificationIds, Criteria::IN)
            ->filterByFamId(0, Criteria::GREATER_THAN)
            ->select('FamId')
            ->distinct()
            ->find()
            ->toArray();

        return array_map('intval', $ids);
    }
}
