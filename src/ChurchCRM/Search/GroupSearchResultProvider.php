<?php

namespace ChurchCRM\Search;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\GroupQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class GroupSearchResultProvider extends BaseSearchResultProvider
{
    public function __construct()
    {
        $this->pluralNoun = 'Groups';
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery)
    {
        if (SystemConfig::getBooleanValue('bSearchIncludeGroups')) {
            $this->addSearchResults($this->getPersonSearchResultsByPartialName($SearchQuery));
        }

        return $this->formatSearchGroup();
    }

    private function getPersonSearchResultsByPartialName(string $SearchQuery)
    {
        $searchResults = [];
        $id = 0;

        try {
            $groups = GroupQuery::create()
                ->filterByName("%$SearchQuery%", Criteria::LIKE)
                ->limit(SystemConfig::getValue('bSearchIncludeGroupsMax'))
                ->find();
            if (!empty($groups)) {
                $id++;
                foreach ($groups as $group) {
                    array_push($searchResults, new SearchResult('group-name-'.$id, $group->getName(), $group->getViewURI()));
                }
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }

        return $searchResults;
    }
}
