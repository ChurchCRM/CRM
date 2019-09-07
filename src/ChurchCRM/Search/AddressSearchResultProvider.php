<?php

namespace ChurchCRM\Search;

use ChurchCRM\FamilyQuery;
use ChurchCRM\Family;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;

class AddressSearchResultProvider implements iSearchResultProvider {

    public static function getSearchResults(string $SearchQuery) {
        if (SystemConfig::getBooleanValue("bSearchIncludeAddresses")) {
            $searchResults = self::getPersonSearchResultsByPartialAddress($SearchQuery);
        }

        if (!empty($searchResults)) {
            return new SearchResultGroup(gettext('Address')." (". count($searchResults).")", $searchResults);
        }
        return null;
    }

    private static function getPersonSearchResultsByPartialAddress(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {

            $searchLikeString = '%' . $SearchQuery . '%';
            $addresses = FamilyQuery::create()->
            filterByCity($searchLikeString, Criteria::LIKE)->
            _or()->filterByAddress1($searchLikeString, Criteria::LIKE)->
            _or()->filterByAddress2($searchLikeString, Criteria::LIKE)->
            _or()->filterByZip($searchLikeString, Criteria::LIKE)->
            _or()->filterByState($searchLikeString, Criteria::LIKE)->
            limit(SystemConfig::getValue("bSearchIncludeAddressesMax"))->find();

            if (!empty($addresses)) {
                $id++;
                foreach ($addresses as $address) {
                    array_push($searchResults, new SearchResult("person-address-".$id, $address->getFamilyString(SystemConfig::getBooleanValue("bSearchIncludeFamilyHOH")),$address->getViewURI()));
                }
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warn($e->getMessage());
        }

        return $searchResults;
    }


    


}