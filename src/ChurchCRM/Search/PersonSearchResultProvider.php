<?php

namespace ChurchCRM\Search;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class PersonSearchResultProvider extends BaseSearchResultProvider
{
    public function __construct()
    {
        $this->pluralNoun = 'Persons';
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery)
    {
        if (SystemConfig::getBooleanValue('bSearchIncludePersons')) {
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
            $searchLikeString = '%' . $SearchQuery . '%';
            $people = PersonQuery::create()->
                filterByFirstName($searchLikeString, Criteria::LIKE)->
                _or()->filterByMiddleName($searchLikeString, Criteria::LIKE)->
                _or()->filterByLastName($searchLikeString, Criteria::LIKE)->
                _or()->filterByEmail($searchLikeString, Criteria::LIKE)->
                _or()->filterByWorkEmail($searchLikeString, Criteria::LIKE)->
                _or()->filterByHomePhone($searchLikeString, Criteria::LIKE)->
                _or()->filterByCellPhone($searchLikeString, Criteria::LIKE)->
                _or()->filterByWorkPhone($searchLikeString, Criteria::LIKE)->
                limit(SystemConfig::getValue('bSearchIncludePersonsMax'))->find();

            if (!empty($people)) {
                $id++;
                foreach ($people as $person) {
                    $searchResults[] = new SearchResult('person-name-' . $id, $person->getFullName(), $person->getViewURI());
                }
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }

        return $searchResults;
    }
}
