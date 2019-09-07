<?php

namespace ChurchCRM\Search;

use ChurchCRM\Base\EventQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;

class CalendarEventSearchResultProvider implements iSearchResultProvider {

    public static function getSearchResults(string $SearchQuery) {
        if (SystemConfig::getBooleanValue("bSearchIncludeCalendarEvents")) {
            $searchResults = self::getCalendarEventSearchResultsByPartialName($SearchQuery);
        }

        if (!empty($searchResults)) {
            return new SearchResultGroup(gettext('Calendar Events')." (". count($searchResults).")", $searchResults);
        }
        return null;
    }

    private static function getCalendarEventSearchResultsByPartialName(string $SearchQuery) {
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
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }
        return $searchResults;
    }
}