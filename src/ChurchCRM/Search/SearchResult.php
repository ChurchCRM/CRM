<?php

namespace ChurchCRM\Search;

class SearchResult {
    public $id;
    public $text;
    public $uri;
    public function __construct(string $id, string $text, string $uri) {
        $this->id = $id;
        $this->text = $text;
        $this->uri = $uri;
    }
}