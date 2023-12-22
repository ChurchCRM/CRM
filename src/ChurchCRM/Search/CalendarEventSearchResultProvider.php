<?php

namespace ChurchCRM\Search;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\EventQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class CalendarEventSearchResultProvider extends BaseSearchResultProvider
{
    public function __construct()
    {
        $this->pluralNoun = 'Calendar Events';
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery)
    {
        if (SystemConfig::getBooleanValue('bSearchIncludeCalendarEvents')) {
            $this->addSearchResults($this->getCalendarEventSearchResultsByPartialName($SearchQuery));
        }

        return $this->formatSearchGroup();
    }

    /**
     * @return SearchResult[]
     */
    private function getCalendarEventSearchResultsByPartialName(string $SearchQuery): array
    {
        $searchResults = [];
        $id = 0;

        try {
            $events = EventQuery::create()
                ->filterByTitle("%$SearchQuery%", Criteria::LIKE)
                ->_or()
                ->filterByText("%$SearchQuery%", Criteria::LIKE)
                ->_or()
                ->filterByDesc("%$SearchQuery%", Criteria::LIKE)
                ->limit(SystemConfig::getValue('bSearchIncludeGroupsMax'))
                ->find();
            if (!empty($events)) {
                $id++;
                foreach ($events as $event) {
                    $searchResults[] = new SearchResult('event-name-' . $id, $event->getTitle(), $event->getViewURI());
                }
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }

        return $searchResults;
    }
}
