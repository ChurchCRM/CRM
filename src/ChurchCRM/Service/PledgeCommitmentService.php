<?php

namespace ChurchCRM\Service;

use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;

class PledgeCommitmentService
{
    public static function installmentCount(string $schedule, mixed $first, mixed $last, int $fiscalYearId): int
    {
        $start = DateTimeUtils::parseIsoDate($first);
        $end = DateTimeUtils::parseIsoDate($last);
        if ($start === '' || $end === '' || $start > $end || $fiscalYearId <= 0) {
            throw new \UnexpectedValueException(gettext('Enter valid first and last installment dates in order.'));
        }
        $fy = FiscalYearUtils::getFiscalYearDatesById($fiscalYearId);
        if ($start < $fy['startDate'] || $end > $fy['endDate']) {
            throw new \UnexpectedValueException(gettext('Installment dates must be within the selected fiscal year.'));
        }
        if (!in_array($schedule, ['Weekly', 'Monthly', 'Quarterly'], true)) {
            throw new \UnexpectedValueException(gettext('Choose Weekly, Monthly or Quarterly for an installment amount.'));
        }

        $anchor = new \DateTimeImmutable($start);
        $limit = new \DateTimeImmutable($end);
        $count = 0;
        do {
            $count++;
            if ($schedule === 'Weekly') {
                $next = $anchor->modify('+' . ($count * 7) . ' days');
            } else {
                $months = $count * ($schedule === 'Quarterly' ? 3 : 1);
                $month = $anchor->modify('first day of this month')->modify('+' . $months . ' months');
                // Retain the original day after a shorter month (January 31 → February 28 → March 31).
                $next = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min((int) $anchor->format('d'), (int) $month->format('t')));
            }
        } while ($next <= $limit);

        return $count;
    }

    /** Amounts remain full commitments so all giving totals share the same basis. */
    public static function allocations(object $payment, array $funds): array
    {
        $basis = $payment->amountBasis ?? 'total';
        if (!in_array($basis, ['total', 'installment'], true)) {
            throw new \UnexpectedValueException(gettext('Choose total commitment or installment amount.'));
        }
        if ($basis === 'total') {
            return $funds;
        }
        if (($payment->type ?? '') !== 'Pledge') {
            throw new \UnexpectedValueException(gettext('Installment amounts can only be used for pledges.'));
        }
        $count = self::installmentCount((string) ($payment->schedule ?? ''), $payment->firstInstallment ?? null, $payment->lastInstallment ?? null, (int) ($payment->FYID ?? 0));
        $result = [];
        foreach ($funds as $fund) {
            $amount = filter_var($fund->Amount ?? null, FILTER_VALIDATE_FLOAT);
            $nonDeductible = filter_var($fund->NonDeductible ?? 0, FILTER_VALIDATE_FLOAT);
            if ($amount === false || $nonDeductible === false || !is_finite($amount) || !is_finite($nonDeductible) || $amount < 0 || $amount > 999999.99 || $nonDeductible < 0 || $nonDeductible > $amount) {
                throw new \UnexpectedValueException(gettext('Enter valid installment amounts with at most two decimal places.'));
            }
            $cents = (int) round($amount * 100);
            $ndCents = (int) round($nonDeductible * 100);
            if (abs($amount * 100 - $cents) > 0.00001 || abs($nonDeductible * 100 - $ndCents) > 0.00001 || $cents * $count > 99999999) {
                throw new \UnexpectedValueException(gettext('The full commitment must not exceed 999999.99 per fund and amounts must use at most two decimal places.'));
            }
            $allocation = clone $fund;
            $allocation->InstallmentAmount = $cents / 100;
            $allocation->Amount = $cents * $count / 100;
            $allocation->NonDeductible = $ndCents * $count / 100;
            $result[] = $allocation;
        }

        return $result;
    }
}
