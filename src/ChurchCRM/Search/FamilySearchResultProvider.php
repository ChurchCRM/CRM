<?php

namespace ChurchCRM\Search;

use ChurchCRM\FamilyQuery;
use ChurchCRM\FamilyCustomMasterQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Search\SearchResult;
use ChurchCRM\Search\SearchResultGroup;
use ChurchCRM\dto\SystemConfig;

class FamilySearchResultProvider extends BaseSearchResultProvider  {
    public function __construct()
    {
        $this->pluralNoun = "Families";
        parent::__construct();
    }

    public function getSearchResults(string $SearchQuery) {
        $searchResults = array();
        if (SystemConfig::getBooleanValue("bSearchIncludeFamilies")) {
            $this->addSearchResults($this->getFamilySearchResultsByPartialName($SearchQuery));
        }
        if (SystemConfig::getBooleanValue("bSearchIncludeFamilyCustomProperties")) {
            $this->addSearchResults($this->getFamilySearchResultsByCustomProperties($SearchQuery));
        }

        return $this->formatSearchGroup();
    }

    private function getFamilySearchResultsByPartialName(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $families = FamilyQuery::create()->
            filterByName("%$SearchQuery%", Criteria::LIKE)->
            _or()->filterByHomePhone("%$SearchQuery%", Criteria::LIKE)->
            _or()->filterByEmail("%$SearchQuery%", Criteria::LIKE)->
            _or()->filterByCellPhone("%$SearchQuery%", Criteria::LIKE)->
            _or()->filterByWorkPhone("%$SearchQuery%", Criteria::LIKE)->
            limit(SystemConfig::getValue("bSearchIncludeFamiliesMax"))->find();



            if (!empty($families)) {
                $id++;
                foreach ($families as $family) {
                    array_push($searchResults, new SearchResult("family-name-".$id, $family->getFamilyString(SystemConfig::getBooleanValue("bSearchIncludeFamilyHOH")),$family->getViewURI()));
                }
            }

            return $searchResults;
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }
    }
    private function getFamilySearchResultsByCustomProperties(string $SearchQuery) {
        $searchResults = array();
        $id = 0;
        try {
            $customFields = FamilyCustomMasterQuery::create()->find();
            $familyQuery = FamilyQuery::create()
                    ->joinFamilyCustom()
                    ->useFamilyCustomQuery();
            foreach($customFields as $customField)
            {
                // search the `family_custom` table for the supplied query using all available `c_` fields obtained from `family_custom_master`
                $familyQuery->where($customField->getField()." LIKE ?","%$SearchQuery%",\PDO::PARAM_STR );
                $familyQuery->_or();
            }
            $families = $familyQuery->endUse()->find();
            foreach ($families as $family) {
                $id++;
                array_push($searchResults, new SearchResult("family-custom-prop-".$id, $family->getFamilyString(SystemConfig::getBooleanValue("bSearchIncludeFamilyHOH")),$family->getViewURI()));
            }
        } catch (Exception $e) {
            LoggerUtils::getAppLogger()->warning($e->getMessage());
        }
        return $searchResults;
    }
}
