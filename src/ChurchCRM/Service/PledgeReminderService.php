<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Pledge reminder letters: one letter per family showing the fiscal year's pledges, payments
 * and what is still owed per fund. Callers authorize: the Finance routes sit behind
 * FinanceRoleAuthMiddleware.
 */
class PledgeReminderService
{
    /**
     * Turn untrusted form values into the criteria every other method takes.
     *
     * @return array{fiscalYearId: int, fundIds: int[], familyIds: int[], classificationIds: int[], onlyWithPledge: bool, onlyOwing: bool}
     */
    public static function criteriaFromInput(array $input): array
    {
        return [
            'fiscalYearId' => InputUtils::filterInt(self::scalar($input['FYID'] ?? '')) ?: FiscalYearUtils::getCurrentFiscalYearId(),
            'fundIds' => self::intList($input['funds'] ?? []),
            'familyIds' => self::intList($input['family'] ?? []),
            'classificationIds' => self::intList($input['classList'] ?? []),
            'onlyWithPledge' => self::scalar($input['pledge_filter'] ?? 'pledge') !== 'all',
            'onlyOwing' => self::scalar($input['only_owe'] ?? 'yes') !== 'no',
        ];
    }

    /**
     * The document the `pledge-reminders` template renders.
     *
     * @param array{fiscalYearId: int, fundIds: int[], familyIds: int[], classificationIds: int[], onlyWithPledge: bool, onlyOwing: bool} $criteria
     */
    public static function document(array $criteria): array
    {
        $funds = [];
        foreach (DonationFundQuery::create()->orderById()->find() as $fund) {
            $funds[(int) $fund->getId()] = (string) $fund->getName();
        }

        $selectedFunds = array_values(array_intersect($criteria['fundIds'], array_keys($funds)));
        $fundClause = self::fundClause($selectedFunds, $funds);

        $letters = [];
        foreach (self::eligibleFamilies($criteria) as $family) {
            $letter = self::letter($family, $criteria, $selectedFunds, $funds);
            if ($letter !== null) {
                $letters[] = $letter;
            }
        }

        return [
            'letterhead' => 'address',
            'opening' => trim(rtrim((string) SystemConfig::getValue('sReminder1')) . ' ' . FinancialService::formatFiscalYear($criteria['fiscalYearId']) . $fundClause . '.'),
            'no_pledge' => trim((string) SystemConfig::getValue('sReminderNoPledge') . ($fundClause !== '' ? ' (' . trim($fundClause) . ')' : '')),
            'no_payments' => (string) SystemConfig::getValue('sReminderNoPayments'),
            'sincerely' => (string) SystemConfig::getValue('sConfirmSincerely'),
            'signer' => (string) SystemConfig::getValue('sReminderSigner'),
            'letters' => $letters,
        ];
    }

    /**
     * @return \ChurchCRM\model\ChurchCRM\Family[]
     */
    private static function eligibleFamilies(array $criteria): array
    {
        $query = FamilyQuery::create()->orderById();
        if ($criteria['familyIds'] !== []) {
            $query->filterById($criteria['familyIds'], Criteria::IN);
        }

        $classIds = $criteria['classificationIds'];
        $allClassIds = [];
        foreach (Classification::getAll() as $option) {
            $allClassIds[] = (int) $option->getOptionId();
        }
        // Every class selected behaves as no class filter.
        if ($classIds !== [] && array_diff($allClassIds, $classIds) !== []) {
            // A family qualifies when it has a member in a selected class and no member outside them.
            $inSelected = self::familyIdsWithClass(PersonQuery::create()->filterByClsId($classIds, Criteria::IN));
            $outside = self::familyIdsWithClass(PersonQuery::create()->filterByClsId($classIds, Criteria::NOT_IN));
            $eligible = array_values(array_diff($inSelected, $outside));
            if ($eligible === []) {
                return [];
            }
            $query->filterById($eligible, Criteria::IN);
        }

        return iterator_to_array($query->find(), false);
    }

    /**
     * @return int[]
     */
    private static function familyIdsWithClass(PersonQuery $people): array
    {
        $ids = $people->filterByFamId(0, Criteria::GREATER_THAN)->select('FamId')->distinct()->find()->toArray();

        return array_map('intval', $ids);
    }

    /**
     * @param \ChurchCRM\model\ChurchCRM\Family $family
     * @param int[] $selectedFunds
     * @param array<int, string> $funds
     */
    private static function letter($family, array $criteria, array $selectedFunds, array $funds): ?array
    {
        $pledges = [];
        $payments = [];
        $query = PledgeQuery::create()
            ->filterByFamId($family->getId())
            ->filterByFyId($criteria['fiscalYearId'])
            ->orderByDate()
            ->orderById();
        if ($selectedFunds !== []) {
            $query->filterByFundId($selectedFunds, Criteria::IN);
        }
        foreach ($query->find() as $row) {
            if ($row->getPledgeOrPayment() === 'Pledge') {
                $pledges[] = $row;
            } else {
                $payments[] = $row;
            }
        }

        if ($pledges === [] && $payments === []) {
            return null;
        }
        if ($criteria['onlyWithPledge'] && $pledges === []) {
            return null;
        }

        $pledgedByFund = self::centsByFund($pledges);
        $paidByFund = self::centsByFund($payments);

        if ($criteria['onlyOwing']) {
            $owes = false;
            foreach (array_keys($pledgedByFund + $paidByFund) as $fundId) {
                if (($pledgedByFund[$fundId] ?? 0) > ($paidByFund[$fundId] ?? 0)) {
                    $owes = true;
                }
            }
            if (!$owes) {
                return null;
            }
        }

        $fundName = static fn (?int $fundId): string => $funds[(int) $fundId] ?? gettext('Undesignated');

        $due = [];
        foreach ($funds as $fundId => $name) {
            if (($pledgedByFund[$fundId] ?? 0) > 0) {
                $due[] = [
                    'fund' => $name,
                    'amount' => CurrencyFormatter::format(max(0, $pledgedByFund[$fundId] - ($paidByFund[$fundId] ?? 0)) / 100),
                ];
            }
        }

        return [
            'salutation' => $family->getSalutation(),
            'address' => $family->getMailingAddressLines(),
            'pledges' => array_map(static fn ($pledge): array => [
                'date' => $pledge->getDate('Y-m-d'),
                'fund' => $fundName($pledge->getFundId()),
                'amount' => CurrencyFormatter::format($pledge->getAmount()),
            ], $pledges),
            'pledge_total' => count($pledges) > 1 ? CurrencyFormatter::format(array_sum($pledgedByFund) / 100) : '',
            'payments' => array_map(static fn ($payment): array => [
                'date' => $payment->getDate('Y-m-d'),
                'check_no' => (string) ($payment->getCheckNo() ?? ''),
                'method' => (string) $payment->getMethod(),
                'fund' => $fundName($payment->getFundId()),
                'memo' => (string) $payment->getComment(),
                'amount' => CurrencyFormatter::format($payment->getAmount()),
            ], $payments),
            'payment_total' => count($payments) > 1 ? CurrencyFormatter::format(array_sum($paidByFund) / 100) : '',
            'due' => $due,
        ];
    }

    /**
     * @param \ChurchCRM\model\ChurchCRM\Pledge[] $rows
     * @return array<int, int> cents per fund id
     */
    private static function centsByFund(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $fundId = (int) $row->getFundId();
            $totals[$fundId] = ($totals[$fundId] ?? 0) + (int) round(((float) $row->getAmount()) * 100);
        }

        return $totals;
    }

    /**
     * " for fund X" or " for funds X, Y"; empty when every fund is in scope.
     *
     * @param int[] $selectedFunds
     * @param array<int, string> $funds
     */
    private static function fundClause(array $selectedFunds, array $funds): string
    {
        if ($selectedFunds === []) {
            return '';
        }
        $names = array_map(static fn (int $id): string => $funds[$id], $selectedFunds);

        return ' ' . (count($names) === 1 ? gettext('for fund') : gettext('for funds')) . ' ' . implode(', ', $names);
    }

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
            $id = InputUtils::filterInt(self::scalar($value));
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
