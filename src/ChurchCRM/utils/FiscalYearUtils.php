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
