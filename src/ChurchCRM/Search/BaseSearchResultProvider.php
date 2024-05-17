<?php

namespace ChurchCRM\Search;

abstract class BaseSearchResultProvider
{
    protected string $pluralNoun;

    /* @var SearchResult[] */
    protected array $searchResults = [];

    abstract public function getSearchResults(string $SearchQuery): SearchResultGroup;

    protected function formatSearchGroup(): SearchResultGroup
    {
        return new SearchResultGroup(gettext($this->pluralNoun) . ' (' . count($this->searchResults) . ')', $this->searchResults);
    }

    protected function addSearchResults(array $results): void
    {
        $this->searchResults = array_merge($this->searchResults, $results);
    }
}
