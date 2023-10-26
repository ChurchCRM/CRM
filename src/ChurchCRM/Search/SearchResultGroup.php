<?php

namespace ChurchCRM\Search;

class SearchResultGroup implements \JsonSerializable {
    public $groupName;
    public $results;

    public function __construct(string $groupName, array $results) {
        $this->groupName = $groupName;
        $this->results = $results;
    }

    public function jsonSerialize() {
        return @[
            "text" => $this->groupName,
            "children" => $this->results
        ];
    }
}