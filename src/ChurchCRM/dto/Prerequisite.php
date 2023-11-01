<?php

namespace ChurchCRM\dto;

use ChurchCRM\Utils\MiscUtils;

class Prerequisite implements \JsonSerializable
{
    private string $name;
    private $testFunction;
    private ?bool $savedTestResult = null;

    public function __construct(string $name, callable $testFunction)
    {
        $this->name = $name;
        $this->testFunction = $testFunction;
    }

    public function isPrerequisiteMet()
    {
        $callable = $this->testFunction;
        if ($this->savedTestResult === null) {
            $this->savedTestResult = (bool)$callable();
        }
        return $this->savedTestResult;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getWikiLink()
    {
        return 'https://github.com/ChurchCRM/CRM/wiki/ChurchCRM-Application-Platform-Prerequisites#' . MiscUtils::getGitHubWikiAnchorLink($this->name);
    }
    public function getStatusText()
    {
        if ($this->isPrerequisiteMet()) {
            return gettext("Passed");
        }
        return gettext("Failed");
    }

    public function jsonSerialize(): array
    {
         return [
           'Name' => $this->getName(),
           'WikiLink' => $this->getWikiLink(),
           'Satisfied' => $this->isPrerequisiteMet()
         ];
    }
}
