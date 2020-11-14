<?php

namespace ChurchCRM\Search;

use ChurchCRM\Base\EventQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;

class CalendarEventSearchResultProvider extends BaseSearchResultProvider  {
    public function __construct()
    {
        $this->pluralNoun = "Calendar Events";
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery) {
        if (SystemConfig::getBooleanValue("bSearchIncludeCalendarEvents")) {
            $this->addSearchResults($this->getCalendarEventSearchResultsByPartialName($SearchQuery));
        }
        return $this->formatSearchGroup();
    }

    private function getCalendarEventSearchResultsByPartialName(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $events = EventQuery::create()
                ->filterByTitle("%$SearchQuery%", Criteria::LIKE)
                ->_or()
                ->filterByText("%$SearchQuery%", Criteria::LIKE)
                ->_or()
                ->filterByDesc("%$SearchQuery%", Criteria::LIKE)
                ->limit(SystemConfig::getValue("bSearchIncludeGroupsMax"))
                ->find();
            if (!empty($events)) {
                $id++;
                foreach ($events as $event) {
                    array_push($searchResults, new SearchResult("event-name-".$id, $event->getTitle(),$event->getViewURI()));
                }
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }
        return $searchResults;
    }
}
