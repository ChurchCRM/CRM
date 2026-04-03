<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

class FunctionsUtils
{
    /**
     * Runs an SQL query. Returns the result resource.
     * By default stop on error, unless a second (optional) argument is passed as false.
     *
     * @param string $sSQL SQL query to execute
     * @param bool $bStopOnError Whether to throw exception on error (default: true)
     * @return mixed Query result resource or false
     * @throws \Exception
     */
    public static function runQuery(string $sSQL, bool $bStopOnError = true)
    {
        global $cnInfoCentral;
        
        mysqli_query($cnInfoCentral, "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
        
        if ($result = mysqli_query($cnInfoCentral, $sSQL)) {
            return $result;
        } elseif ($bStopOnError) {
            LoggerUtils::getAppLogger()->error(gettext('Cannot execute query.') . " " . $sSQL . " -|- " . mysqli_error($cnInfoCentral));
            if (SystemConfig::getValue('sLogLevel') == "100") { // debug level
                throw new \Exception(gettext('Cannot execute query.') . "<p>$sSQL<p>" . mysqli_error($cnInfoCentral));
            } else {
                throw new \Exception('Database error or invalid data, change sLogLevel to debug to see more.');
            }
        } else {
            return false;
        }
    }

    /**
     * Generates a unique group key for pledge payments.
     * Migrated from genGroupKey() in Functions.php.
     */
    public static function genGroupKey(string $methodSpecificID, string $famID, string $fundIDs, string $date)
    {
        $uniqueNum = 0;
        while (1) {
            $GroupKey = $methodSpecificID . '|' . $uniqueNum . '|' . $famID . '|' . $fundIDs . '|' . $date;
            $sSQL = "SELECT COUNT(plg_GroupKey) FROM pledge_plg WHERE plg_PledgeOrPayment='Payment' AND plg_GroupKey='" . $GroupKey . "'";
            $rsResults = self::runQuery($sSQL);
            [$numGroupKeys] = mysqli_fetch_row($rsResults);
            if ($numGroupKeys) {
                ++$uniqueNum;
            } else {
                return $GroupKey;
            }
        }
    }
}
