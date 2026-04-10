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
        $yearNow = (int) date('Y');
        $monthNow = (int) date('m');
        $fyMonth = SystemConfig::getIntValue('iFYMonth');
        
        $fyid = $yearNow - 1996;
        if ($monthNow >= $fyMonth && $fyMonth > 1) {
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
