<?php

namespace ChurchCRM\Search;

class SearchResultGroup implements \JsonSerializable
{
    private string $groupName;
    private array $results;

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
