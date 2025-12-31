<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

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
        $fyMonth = (int) SystemConfig::getValue('iFYMonth');
        
        $fyid = $yearNow - 1996;
        if ($monthNow >= $fyMonth && $fyMonth > 1) {
            $fyid += 1;
        }

        return $fyid;
    }
}
