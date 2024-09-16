<?php

namespace ChurchCRM\dto;

class ListColumn
{
    public string $name;
    public string $displayFunction;
    public string $emptyOrUnassigned;
    public string $visible;

    public function __construct(string $name, string $displayFunction, string $emptyOrUnassigned, string $visible)
    {
        $this->name = $name;
        $this->displayFunction = $displayFunction;
        $this->emptyOrUnassigned = $emptyOrUnassigned;
        $this->visible = $visible;
    }
}
