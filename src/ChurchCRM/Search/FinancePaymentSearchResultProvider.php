<?php

namespace ChurchCRM\Search;

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\PledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;

class FinancePaymentSearchResultProvider implements iSearchResultProvider {

    public static function getSearchResults(string $SearchQuery) {
        if ($_SESSION['user']->isFinanceEnabled()) {
            $searchResults = array();
            if (SystemConfig::getBooleanValue("bSearchIncludePayments")) {
                $searchResults = self::getPaymentSearchResults($SearchQuery);
                if (count(explode("-",$SearchQuery)) == 2 ) {
                    $range = explode("-",$SearchQuery);
                    $searchResults = array_merge(self::getPaymentsWithValuesInRange((int)$range[0],(int)$range[1]));
                }
            }

            if (!empty($searchResults)) {
                return new SearchResultGroup(gettext('Payments') ." (". count($searchResults).")", $searchResults);
            }
        }
        return null;
    }

    private static function getPaymentsWithValuesInRange(int $min, int $max) {
        $searchResults = array();
        $id = 0;
        if ($max == 0) 
        {
            $max = PHP_INT_MAX;
        }
        try {
            $Payments = PledgeQuery::create()
            ->withColumn('SUM(Pledge.Amount)', 'GroupAmount')
            ->withColumn('CONCAT("#",Pledge.Id)', 'displayName')
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Pledge.Depid)', 'uri')
            #->limit(SystemConfig::getValue("bSearchIncludePaymentsMax")) // this can't be limited here due to how Propel ORM doesn't handle HAVING clause nicely, so we do it in PHP
            ->groupByGroupkey()
            ->find();

            if (!empty($Payments)) {
                $id++;
                foreach ($Payments as $Payment) {
                    // I can't seem to get the SQL HAVING clause to work through Propel ORM to use 
                    // both MIN and MAX value.  Just filter it in PHP 
                    if ($Payment->getVirtualColumn("GroupAmount") >= $min && $Payment->getVirtualColumn("GroupAmount") <= $max){
                        array_push($searchResults, new SearchResult("finance-payment-".$id,  "\$".$Payment->getVirtualColumn("GroupAmount")." Payment on Deposit " . $Payment->getDepid(), $Payment->getVirtualColumn('uri')));
                    }
                }
            }

        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }
        return array_slice($searchResults,0,SystemConfig::getValue("bSearchIncludePaymentsMax")); // since Propel ORM won't handle limit() nicely, do it in PHP
    }

    private static function getPaymentSearchResults(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $Payments = PledgeQuery::create()
            ->filterByCheckno("$SearchQuery", Criteria::EQUAL)
            ->withColumn('CONCAT("#",Pledge.Id)', 'displayName')
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Pledge.Depid)', 'uri')
            ->limit(SystemConfig::getValue("bSearchIncludePaymentsMax"))
            ->groupByGroupkey()
            ->find();

            if (!empty($Payments)) {
                $id++;
                foreach ($Payments as $Payment) {
                    array_push($searchResults, new SearchResult("finance-payment-".$id,  "Check ".$Payment->getCheckno()." on Deposit " . $Payment->getDepid(), $Payment->getVirtualColumn('uri')));
                }
            }

        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }
        return $searchResults;
    }
}