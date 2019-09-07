<?php

namespace ChurchCRM\Search;

use ChurchCRM\PersonQuery;
use ChurchCRM\Person;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;

class PersonSearchResultProvider implements iSearchResultProvider {

    public static function getSearchResults(string $SearchQuery) {
        if (SystemConfig::getBooleanValue("bSearchIncludePersons")) {
            $searchResults = self::getPersonSearchResultsByPartialName($SearchQuery);
        }

        if (!empty($searchResults)) {
            return new SearchResultGroup(gettext('Persons'), $searchResults);
        }
        return null;
    }

    private static function getPersonSearchResultsByPartialName(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $searchLikeString = '%' . $SearchQuery . '%';
            $people = PersonQuery::create()->
                filterByFirstName($searchLikeString, Criteria::LIKE)->
                _or()->filterByLastName($searchLikeString, Criteria::LIKE)->
                _or()->filterByEmail($searchLikeString, Criteria::LIKE)->
                _or()->filterByWorkEmail($searchLikeString, Criteria::LIKE)->
                _or()->filterByHomePhone($searchLikeString, Criteria::LIKE)->
                _or()->filterByCellPhone($searchLikeString, Criteria::LIKE)->
                _or()->filterByWorkPhone($searchLikeString, Criteria::LIKE)->
                limit(SystemConfig::getValue("bSearchIncludePersonsMax"))->find();

            if (!empty($people)) {
                $id++;
                foreach ($people as $person) {
                    array_push($searchResults, new SearchResult("person-name-".$id, $person->getFullName(),$person->getViewURI()));
                }
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }
        return $searchResults;
    }
}