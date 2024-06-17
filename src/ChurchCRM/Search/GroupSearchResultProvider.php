<?php

namespace ChurchCRM\Search;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class GroupSearchResultProvider extends BaseSearchResultProvider
{
    public function __construct()
    {
        $this->pluralNoun = 'Groups';
    }

    public function getSearchResults(string $SearchQuery): SearchResultGroup
    {
        if (SystemConfig::getBooleanValue('bSearchIncludeGroups')) {
            $this->addSearchResults($this->getPersonSearchResultsByPartialName($SearchQuery));
        }

        return $this->formatSearchGroup();
    }

    /**
     * @return SearchResult[]
     */
    private function getPersonSearchResultsByPartialName(string $SearchQuery): array
    {
        $searchResults = [];
        $id = 0;

        try {
            $groups = GroupQuery::create()
                ->filterByName("%$SearchQuery%", Criteria::LIKE)
                ->limit(SystemConfig::getValue('bSearchIncludeGroupsMax'))
                ->find();
            if ($groups->count() > 0) {
                $id++;
                foreach ($groups as $group) {
                    $searchResults[] = new SearchResult('group-name-' . $id, $group->getName(), $group->getViewURI());
                }
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }

        return $searchResults;
    }
}
