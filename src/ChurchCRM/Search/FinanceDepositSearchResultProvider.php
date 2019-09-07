<?php

namespace ChurchCRM\Search;

use ChurchCRM\DepositQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class FinanceDepositSearchResultProvider implements iSearchResultProvider {

    public static function getSearchResults(string $SearchQuery) {
        if ($_SESSION['user']->isFinanceEnabled()) {
            if (SystemConfig::getBooleanValue("bSearchIncludeDeposits")) {
                $searchResults = self::getDepositSearchResults($SearchQuery);
            }

            if (!empty($searchResults)) {
                return new SearchResultGroup(gettext('Deposits'), $searchResults);
            }
        }
        return null;
    }

    private static function getDepositSearchResults(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $Deposits = DepositQuery::create()->filterByComment("%$SearchQuery%", Criteria::LIKE)
                ->_or()
                ->filterById($SearchQuery)
                ->withColumn('CONCAT("#",Deposit.Id," ",Deposit.Comment)', 'displayName')
                ->withColumn('CONCAT("' . SystemURLs::getRootPath() . '/DepositSlipEditor.php?DepositSlipID=",Deposit.Id)', 'uri')
                ->limit(SystemConfig::getValue("bSearchIncludeDepositsMax"))->find();

            if (!empty($Deposits)) {
                $id++;
                foreach ($Deposits->toArray() as $Deposit) {
                    array_push($searchResults, new SearchResult("finance-deposit-".$id, $Deposit['displayName'], $Deposit['uri']));
                }
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }
        return $searchResults;
    }
}