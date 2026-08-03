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
     * Runs a parameterized prepared SQL query using MySQLi.
     * Prevents SQL injection by binding parameters rather than string-concatenating them.
     * The SQL string MUST use `?` placeholders for all user-supplied values.
     *
     * @param string $sSQL   SQL query with `?` placeholders
     * @param string $types  MySQLi bind types string (e.g. 'iiss' for int,int,str,str)
     * @param array  $params Array of parameter values matching the placeholders
     * @param bool   $bStopOnError Whether to throw exception on error (default: true)
     * @return \mysqli_result|bool mysqli_result for SELECT queries, true for write queries, false on non-fatal error
     * @throws \Exception
     */
    public static function runPreparedQuery(string $sSQL, string $types = '', array $params = [], bool $bStopOnError = true)
    {
        global $cnInfoCentral;

        mysqli_query($cnInfoCentral, "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        $stmt = mysqli_prepare($cnInfoCentral, $sSQL);
        if ($stmt === false) {
            if ($bStopOnError) {
                LoggerUtils::getAppLogger()->error(gettext('Cannot prepare query.') . ' ' . $sSQL . ' -|- ' . mysqli_error($cnInfoCentral));
                if (SystemConfig::getValue('sLogLevel') == '100') {
                    throw new \Exception(gettext('Cannot prepare query.') . "<p>$sSQL<p>" . mysqli_error($cnInfoCentral));
                } else {
                    throw new \Exception('Database error or invalid data, change sLogLevel to debug to see more.');
                }
            }
            return false;
        }

        if ($types !== '' && $params !== []) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $errMsg = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if ($bStopOnError) {
                LoggerUtils::getAppLogger()->error(gettext('Cannot execute query.') . ' ' . $sSQL . ' -|- ' . $errMsg);
                if (SystemConfig::getValue('sLogLevel') == '100') {
                    throw new \Exception(gettext('Cannot execute query.') . "<p>$sSQL<p>" . $errMsg);
                } else {
                    throw new \Exception('Database error or invalid data, change sLogLevel to debug to see more.');
                }
            }
            return false;
        }

        if (mysqli_stmt_field_count($stmt) > 0) {
            $result = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            if ($result === false) {
                if ($bStopOnError) {
                    LoggerUtils::getAppLogger()->error('mysqli_stmt_get_result failed: ' . $sSQL);
                    throw new \Exception('Database error or invalid data, change sLogLevel to debug to see more.');
                }
                return false;
            }
            return $result;
        }

        mysqli_stmt_close($stmt);
        return true;
    }

    /**
     * Generates a unique group key for pledge payments.
     * Migrated from genGroupKey() in Functions.php.
     */
    public static function genGroupKey(string $methodSpecificID, string $famID, string $fundIDs, string $date)
    {
        global $cnInfoCentral;

        $uniqueNum = 0;
        while (1) {
            $GroupKey = $methodSpecificID . '|' . $uniqueNum . '|' . $famID . '|' . $fundIDs . '|' . $date;
            $escapedGroupKey = mysqli_real_escape_string($cnInfoCentral, $GroupKey);
            $sSQL = "SELECT COUNT(plg_GroupKey) FROM pledge_plg WHERE plg_PledgeOrPayment='Payment' AND plg_GroupKey='" . $escapedGroupKey . "'";
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
