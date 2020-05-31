<?php

namespace ChurchCRM\Search;

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\PledgeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class FinancePaymentSearchResultProvider extends BaseSearchResultProvider  {
    public function __construct()
    {
        $this->pluralNoun = "Payments";
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery) {
        if (AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
            if (SystemConfig::getBooleanValue("bSearchIncludePayments")) {
                $this->addSearchResults($this->getPaymentSearchResults($SearchQuery));
                if (count(explode("-",$SearchQuery)) == 2 ) {
                    $range = explode("-",$SearchQuery);
                    $this->addSearchResults($this->getPaymentsWithValuesInRange((int)$range[0],(int)$range[1]));
                }
            }
        }
        return $this->formatSearchGroup();
    }

    private function getPaymentsWithValuesInRange(int $min, int $max) {
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
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Pledge.DepId)', 'uri')
            #->limit(SystemConfig::getValue("bSearchIncludePaymentsMax")) // this can't be limited here due to how Propel ORM doesn't handle HAVING clause nicely, so we do it in PHP
            ->groupByGroupKey()
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

    private function getPaymentSearchResults(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $Payments = PledgeQuery::create()
            ->filterByCheckNo("$SearchQuery", Criteria::EQUAL)
            ->withColumn('CONCAT("#",Pledge.Id)', 'displayName')
            ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Pledge.DepId)', 'uri')
            ->limit(SystemConfig::getValue("bSearchIncludePaymentsMax"))
            ->groupByGroupKey()
            ->find();

            if (!empty($Payments)) {
                $id++;
                foreach ($Payments as $Payment) {
                    array_push($searchResults, new SearchResult("finance-payment-".$id,  "Check ".$Payment->getCheckNo()." on Deposit " . $Payment->getDepId(), $Payment->getVirtualColumn('uri')));
                }
            }

        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }
        return $searchResults;
    }
}