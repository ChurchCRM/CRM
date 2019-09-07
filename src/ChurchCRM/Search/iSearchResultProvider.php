<?php

namespace ChurchCRM\Search;

interface iSearchResultProvider {
    public static function getSearchResults(string $SearchQuery);
}