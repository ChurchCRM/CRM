<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;

class FiscalYearUtils
{
    /**
     * Get the current fiscal year ID based on system date and iFYMonth setting
     * 
     * Fiscal Year IDs are calculated as: currentYear - 1996
     * If the current month is >= iFYMonth (and iFYMonth > 1), add 1 to move to next FY
     * 
     * Examples:
     * - If today is Jan 15, 2025 and iFYMonth is 7: FY = (2025-1996) = 29
     * - If today is Aug 15, 2025 and iFYMonth is 7: FY = (2025-1996) + 1 = 30
     * 
     * @return int Current fiscal year ID
     */
    public static function getCurrentFiscalYearId(): int
    {
        return self::calculateFiscalYearId((int) date('Y'), (int) date('m'));
    }

    /**
     * Compute the fiscal year ID for an arbitrary calendar date string.
     *
     * Falls back to the current fiscal year when $date is empty or unparseable.
     *
     * @param string $date A date string parseable by strtotime (e.g. 'YYYY-MM-DD')
     * @return int Fiscal year ID
     */
    public static function getFiscalYearIdForDate(string $date): int
    {
        if ($date === '') {
            return self::getCurrentFiscalYearId();
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return self::getCurrentFiscalYearId();
        }
        return self::calculateFiscalYearId((int) date('Y', $ts), (int) date('m', $ts));
    }

    /**
     * Core fiscal-year-ID formula shared by all date-aware callers.
     *
     * Fiscal Year IDs are calculated as: year - 1996.
     * If month >= iFYMonth (and iFYMonth > 1), add 1 to move to the next FY.
     */
    private static function calculateFiscalYearId(int $year, int $month): int
    {
        $fyMonth = SystemConfig::getIntValue('iFYMonth');
        $fyid = $year - 1996;
        if ($month >= $fyMonth && $fyMonth > 1) {
            $fyid += 1;
        }
        return $fyid;
    }

    /**
     * Compute the date range (startDate, endDate, label, month) for a given fiscal year ID.
     *
     * This is the inverse of calculateFiscalYearId:
     *   - Calendar year (iFYMonth == 1): fyid == year - 1996, so year == fyid + 1996
     *   - Non-calendar year (iFYMonth > 1): fyStartYear == fyid + 1995, fyEndYear == fyid + 1996
     *
     * @param int $fyid Fiscal year ID
     * @return array{startDate: string, endDate: string, label: string, month: int}
     */
    public static function getFiscalYearDatesById(int $fyid): array
    {
        $iFYMonth = SystemConfig::getIntValue('iFYMonth');

        if ($iFYMonth === 1) {
            // Calendar year fiscal year: fyid == year - 1996 => year == fyid + 1996
            $year = $fyid + 1996;
            return [
                'startDate' => $year . '-01-01',
                'endDate'   => $year . '-12-31',
                'label'     => (string) $year,
                'month'     => 1,
            ];
        }

        // Non-calendar fiscal year: FY starts in fyStartYear at iFYMonth and ends the month before in fyEndYear
        $fyStartYear = $fyid + 1995;
        $fyEndYear   = $fyid + 1996;
        $fyStartDate = $fyStartYear . '-' . str_pad((string) $iFYMonth, 2, '0', STR_PAD_LEFT) . '-01';
        $endMonth    = $iFYMonth - 1 === 0 ? 12 : $iFYMonth - 1;
        $fyEndDate   = $fyEndYear . '-' . str_pad((string) $endMonth, 2, '0', STR_PAD_LEFT) . '-'
            . date('t', strtotime($fyEndYear . '-' . $endMonth . '-01'));
        $label       = $fyStartYear . '/' . mb_substr((string) $fyEndYear, 2, 2);

        return [
            'startDate' => $fyStartDate,
            'endDate'   => $fyEndDate,
            'label'     => $label,
            'month'     => $iFYMonth,
        ];
    }

    /**
     * Build a sorted (newest-first) list of fiscal year options from $oldestFyId to the next FY.
     *
     * Each entry is ['id' => int, 'label' => string].
     * Callers obtain $oldestFyId by querying their own data source (pledges, deposits, etc.).
     * This helper is intentionally ORM-free so it can be used anywhere.
     *
     * @param int $oldestFyId Oldest fiscal year ID to include (defaults to current FY only)
     * @return array<int, array{id: int, label: string}>
     */
    public static function buildFiscalYearList(int $oldestFyId = 0): array
    {
        $currentFyId = self::getCurrentFiscalYearId();
        if ($oldestFyId <= 0) {
            $oldestFyId = $currentFyId;
        }
        $nextFyId = $currentFyId + 1;
        $years    = [];
        for ($fyid = max(1, $oldestFyId); $fyid <= $nextFyId; $fyid++) {
            $years[] = [
                'id'    => $fyid,
                'label' => FinancialService::formatFiscalYear($fyid),
            ];
        }
        // newest first
        return array_reverse($years);
    }

    /**
     * Renders an HTML <select> dropdown for fiscal year selection.
     * Migrated from PrintFYIDSelect() in Functions.php.
     */
    public static function renderYearSelect(string $selectName, ?int $iFYID = null): void
    {
        echo sprintf('<select class="form-select" name="%s">', $selectName);

        $hasSelected = false;
        $selectableOptions = [];
        for ($fy = 1; $fy < self::getCurrentFiscalYearId() + 2; $fy++) {
            $selectedTag = '';
            if ($iFYID === $fy) {
                $hasSelected = true;
                $selectedTag = ' selected';
            }

            $selectableOptions[] = sprintf('<option value="%s"', $fy) . $selectedTag . '>' . FinancialService::formatFiscalYear((int) $fy) . '</option>';
        }

        $selectableOptions = [
            '<option disabled value="0"' . (!$hasSelected ? ' selected' : '') . '>' . gettext('Select Fiscal Year') . '</option>',
            ...$selectableOptions
        ];

        echo implode('', $selectableOptions);

        echo '</select>';
    }
}
