<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\PdfFilename;

/**
 * Print Deposit: the bank deposit ticket (positioned on the preprinted QuickBooks stock) and the
 * deposit summary. Callers authorize: the Finance routes sit behind FinanceRoleAuthMiddleware.
 */
class DepositReportService
{
    private const TICKET_VALUE_WIDTH = 40;

    public static function depositId(array $input): int
    {
        $id = $input['deposit'] ?? '';

        return is_scalar($id) ? InputUtils::filterInt($id) : 0;
    }

    /**
     * Null when the deposit does not exist or holds no payments.
     */
    public static function report(int $depositId): ?array
    {
        $deposit = $depositId > 0 ? DepositQuery::create()->findPk($depositId) : null;
        if ($deposit === null) {
            return null;
        }

        $pledges = PledgeQuery::create()
            ->filterByDepId($depositId)
            ->filterByPledgeOrPayment('Payment')
            ->leftJoinWithDonationFund()
            ->leftJoinWithFamily()
            ->orderById()
            ->find();
        if ($pledges->count() === 0) {
            return null;
        }

        $payments = [];
        $totalCents = 0;
        $byMethod = ['CHECK' => 0, 'CASH' => 0];
        $groups = ['CHECK' => [], 'CASH' => []];
        $byFund = [];
        foreach ($pledges as $pledge) {
            $cents = (int) round(((float) $pledge->getAmount()) * 100);
            $method = (string) $pledge->getMethod();
            $fund = $pledge->getDonationFund();
            $fundName = $fund !== null ? (string) $fund->getName() : gettext('Undesignated');
            $fundId = (int) $pledge->getFundId();
            $family = $pledge->getFamily();

            $totalCents += $cents;
            $byFund[$fundId]['name'] = $fundName;
            $byFund[$fundId]['cents'] = ($byFund[$fundId]['cents'] ?? 0) + $cents;
            if (isset($byMethod[$method])) {
                $byMethod[$method] += $cents;
                $groupKey = (string) ($pledge->getGroupKey() ?: 'plg' . $pledge->getId());
                $groups[$method][$groupKey] = [
                    'check_no' => (string) $pledge->getCheckNo(),
                    'cents' => ($groups[$method][$groupKey]['cents'] ?? 0) + $cents,
                ];
            }

            $payments[] = [
                'check_no' => (string) $pledge->getCheckNo(),
                'fund' => $fundName,
                'method' => $method,
                'from' => $family !== null ? $family->getName() : gettext('Anonymous'),
                'memo' => (string) $pledge->getComment(),
                'amount' => self::money($cents),
            ];
        }

        usort($byFund, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $funds = array_map(static fn (array $fund): array => [
            'name' => $fund['name'], 'total' => self::money($fund['cents']),
        ], $byFund);

        $otherCents = $totalCents - $byMethod['CHECK'] - $byMethod['CASH'];
        $date = $deposit->getDate('Y-m-d');
        $itemCount = (count($groups['CASH']) > 0 ? 1 : 0) + count($groups['CHECK']);

        return [
            'id' => $deposit->getId(),
            'date' => DateTimeUtils::formatDate($date),
            'comment' => (string) $deposit->getComment(),
            'payments' => $payments,
            'total' => self::money($totalCents),
            'checks_count' => count($groups['CHECK']),
            'checks_total' => self::money($byMethod['CHECK']),
            'cash_total' => self::money($byMethod['CASH']),
            'other_total' => $otherCents !== 0 ? self::money($otherCents) : '',
            'funds' => $funds,
            'bill_counts' => SystemConfig::getBooleanValue('bDisplayBillCounts'),
            'coins' => ['0.01', '0.05', '0.10', '0.25', '0.50', '1.00'],
            'bills' => ['$1', '$2', '$5', '$10', '$20', '$50', '$100'],
            'ticket' => self::ticket($date, $groups, $byMethod['CASH'], self::money($byMethod['CASH'] + $byMethod['CHECK']), $itemCount),
        ];
    }

    public static function fileLabel(int $depositId): string
    {
        return PdfFilename::dated('DepositSlip-' . $depositId);
    }

    /**
     * Values placed at fixed millimetre positions on the preprinted QuickBooks deposit ticket,
     * laid out from the sQBDTSettings coordinates. Null when the settings are unusable.
     *
     * @param array{CHECK: array<string, array{check_no: string, cents: int}>, CASH: array<string, array{check_no: string, cents: int}>} $groups
     */
    private static function ticket(string $date, array $groups, int $cashCents, string $totalText, int $itemCount): ?array
    {
        if (SystemConfig::getValue('sDepositSlipType') !== 'QBDT') {
            return null;
        }
        $settings = json_decode((string) SystemConfig::getValue('sQBDTSettings'), true);
        $paths = [
            'date1.x', 'date1.y', 'date2X', 'leftX', 'topY', 'perforationY', 'amountOffsetX', 'titleX',
            'lineItemInterval.x', 'lineItemInterval.y', 'max.x', 'numberOfItems.x', 'numberOfItems.y',
            'subTotal.x', 'subTotal.y', 'topTotal.x', 'topTotal.y',
        ];
        $s = [];
        foreach ($paths as $path) {
            $value = $settings;
            foreach (explode('.', $path) as $key) {
                $value = is_array($value) ? ($value[$key] ?? null) : null;
            }
            if (!is_numeric($value)) {
                return null;
            }
            $s[$path] = (float) $value;
        }

        $items = [];
        $items[] = ['left' => $s['date1.x'], 'top' => $s['date1.y'], 'text' => DateTimeUtils::formatDate($date), 'align' => 'left'];
        if ($cashCents > 0) {
            $items[] = self::rightAligned($s['leftX'] + $s['amountOffsetX'], $s['topY'], self::money($cashCents));
        }

        // The first column of the top row holds the cash line, so checks start in the second column.
        $x = $s['leftX'] + $s['lineItemInterval.x'];
        $y = $s['topY'];
        foreach ($groups['CHECK'] as $check) {
            $items[] = self::rightAligned($x, $y, $check['check_no']);
            $items[] = self::rightAligned($x + $s['amountOffsetX'], $y, self::money($check['cents']));
            $x += $s['lineItemInterval.x'];
            if ($x > $s['max.x']) {
                $x = $s['leftX'];
                $y += $s['lineItemInterval.y'];
            }
        }

        $items[] = self::rightAligned($s['subTotal.x'], $s['subTotal.y'], $totalText);
        $items[] = self::rightAligned($s['topTotal.x'], $s['topTotal.y'], $totalText);
        $items[] = self::rightAligned($s['numberOfItems.x'], $s['numberOfItems.y'], (string) $itemCount);

        return [
            'items' => $items,
            'date' => DateTimeUtils::formatDate($date),
            'perforation_y' => $s['perforationY'],
            'title_x' => $s['titleX'],
            'date_x' => $s['date2X'],
            'stub_x' => $s['date1.x'],
            'stub_y' => $s['perforationY'] + 2 * $s['lineItemInterval.y'],
            'totals_x' => $s['date1.x'] + 125,
        ];
    }

    private static function rightAligned(float $x, float $y, string $text): array
    {
        return [
            'left' => $x + 10 - self::TICKET_VALUE_WIDTH,
            'top' => $y,
            'width' => self::TICKET_VALUE_WIDTH,
            'text' => $text,
            'align' => 'right',
        ];
    }

    private static function money(int $cents): string
    {
        return CurrencyFormatter::format($cents / 100);
    }
}
