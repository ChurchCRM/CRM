<?php

namespace ChurchCRM\Search;

use ChurchCRM\GroupQuery;

use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;

class GroupSearchResultProvider extends BaseSearchResultProvider  {
    public function __construct()
    {
        $this->pluralNoun = "Groups";
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery) {
        if (SystemConfig::getBooleanValue("bSearchIncludeGroups")) {
            $this->addSearchResults($this->getPersonSearchResultsByPartialName($SearchQuery));
        }
        return $this->formatSearchGroup();
    }

    private function getPersonSearchResultsByPartialName(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $groups = GroupQuery::create()
                ->filterByName("%$SearchQuery%", Criteria::LIKE)
                ->limit(SystemConfig::getValue("bSearchIncludeGroupsMax"))
                ->find();
            if (!empty($groups)) {
                $id++;
                foreach ($groups as $group) {
                    array_push($searchResults, new SearchResult("group-name-".$id, $group->getName(),$group->getViewURI()));
                }
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }
        return $searchResults;
    }
}
