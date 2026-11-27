<?php

namespace ChurchCRM\dto;

use ChurchCRM\Utils\MiscUtils;

class Prerequisite implements \JsonSerializable
{
    private string $name;
    private $testFunction;
    private ?bool $savedTestResult = null;

    public function __construct(string $name, callable $testFunction, private readonly bool $soft = false)
    {
        $this->name = $name;
        $this->testFunction = $testFunction;
    }

    /** A soft prerequisite is reported when it fails but never blocks the app. */
    public function isSoft(): bool
    {
        return $this->soft;
    }

    public function isBlocking(): bool
    {
        return !$this->soft && !$this->isPrerequisiteMet();
    }

    public function isPrerequisiteMet(): ?bool
    {
        $callable = $this->testFunction;
        if ($this->savedTestResult === null) {
            $this->savedTestResult = (bool) $callable();
        }

        return $this->savedTestResult;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWikiLink(): string
    {
        return SystemURLs::attributed('https://docs.churchcrm.io/installation/system-requirements#' . MiscUtils::getGitHubWikiAnchorLink($this->name), 'prerequisite');
    }

    public function getStatusText(): string
    {
        if ($this->isPrerequisiteMet()) {
            return gettext('Passed');
        }

        return $this->soft ? gettext('Warning') : gettext('Failed');
    }

    public function jsonSerialize(): array
    {
        return [
            'Name'      => $this->getName(),
            'WikiLink'  => $this->getWikiLink(),
            'Satisfied' => $this->isPrerequisiteMet(),
            'Soft'      => $this->soft,
        ];
    }
}
