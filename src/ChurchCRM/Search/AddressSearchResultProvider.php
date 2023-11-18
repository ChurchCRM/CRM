<?php

namespace ChurchCRM\Search;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

class AddressSearchResultProvider extends BaseSearchResultProvider
{
    public function __construct()
    {
        $this->pluralNoun = 'Address';
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery)
    {
        if (SystemConfig::getBooleanValue('bSearchIncludeAddresses')) {
            $this->addSearchResults($this->getPersonSearchResultsByPartialAddress($SearchQuery));
        }

        return $this->formatSearchGroup();
    }

    private function getPersonSearchResultsByPartialAddress(string $SearchQuery)
    {
        $searchResults = [];
        $id = 0;

        try {
            $searchLikeString = '%'.$SearchQuery.'%';
            $addresses = FamilyQuery::create()->
            filterByCity($searchLikeString, Criteria::LIKE)->
            _or()->filterByAddress1($searchLikeString, Criteria::LIKE)->
            _or()->filterByAddress2($searchLikeString, Criteria::LIKE)->
            _or()->filterByZip($searchLikeString, Criteria::LIKE)->
            _or()->filterByState($searchLikeString, Criteria::LIKE)->
            limit(SystemConfig::getValue('bSearchIncludeAddressesMax'))->find();

            if (!empty($addresses)) {
                $id++;
                foreach ($addresses as $address) {
                    array_push($searchResults, new SearchResult('person-address-'.$id, $address->getFamilyString(SystemConfig::getBooleanValue('bSearchIncludeFamilyHOH')), $address->getViewURI()));
                }
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }

        return $searchResults;
    }
}
