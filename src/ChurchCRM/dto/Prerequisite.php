<?php

namespace ChurchCRM\dto;
use ChurchCRM\Utils\MiscUtils;

class Prerequisite implements \JsonSerializable {
  private $name;
  private $testFunction;
  private $savedTestResult;

  public function __construct(string $name, callable $testFunction) {
    $this->name = $name;
    $this->testFunction = $testFunction;
    $this->savedTestResult = null;
  }
  
  public function IsPrerequisiteMet(){
    $callable = $this->testFunction;
    if ( $this->savedTestResult === null) {
       $this->savedTestResult = (bool)$callable();
    }
    return $this->savedTestResult;
  }
  
  public function GetName() {
    return $this->name;
  }
  
  public function GetWikiLink() {
    return 'https://github.com/ChurchCRM/CRM/wiki/ChurchCRM-Application-Platform-Prerequisites#' . MiscUtils::GetGitHubWikiAnchorLink($this->name);
  }
  public function GetStatusText() {
    if ($this->IsPrerequisiteMet()){
      return gettext("Passed");
    }
    return gettext("Failed");
  }
  public function jsonSerialize() {
       return [
           'Name' => $this->GetName(),
           'WikiLink' => $this->GetWikiLink(),
           'Satisfied' => $this->IsPrerequisiteMet()
       ];
   }
}
