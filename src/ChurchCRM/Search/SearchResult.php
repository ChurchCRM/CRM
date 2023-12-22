<?php

namespace ChurchCRM\Search;

class SearchResult
{
    /**
     * @var string
     */
    public $id;
    /**
     * @var string
     */
    public $text;
    /**
     * @var string
     */
    public $uri;

    public function __construct(string $id, string $text, string $uri)
    {
        $this->id = $id;
        $this->text = $text;
        $this->uri = $uri;
    }
}
