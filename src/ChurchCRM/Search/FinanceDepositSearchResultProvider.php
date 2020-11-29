<?php

namespace ChurchCRM\Search;

use ChurchCRM\DepositQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;

class FinanceDepositSearchResultProvider extends BaseSearchResultProvider  {
    public function __construct()
    {
        $this->pluralNoun = "Deposits";
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery) {
        if (AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
            if (SystemConfig::getBooleanValue("bSearchIncludeDeposits")) {
                $this->addSearchResults($this->getDepositSearchResults($SearchQuery));
            }

        }
        return $this->formatSearchGroup();
    }

    private function getDepositSearchResults(string $SearchQuery) {
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
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }
        return $searchResults;
    }
}
