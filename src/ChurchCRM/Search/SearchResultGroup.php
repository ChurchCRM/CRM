<?php

namespace ChurchCRM\Search;

class SearchResultGroup implements \JsonSerializable
{
    public string $groupName;
    public array $results;

    public function __construct(string $groupName, array $results)
    {
        $this->groupName = $groupName;
        $this->results = $results;
    }

    public function jsonSerialize(): array
    {
        return [
            'text'     => $this->groupName,
            'children' => $this->results,
        ];
    }
}
