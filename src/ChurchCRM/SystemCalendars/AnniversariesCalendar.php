<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\Interfaces\SystemCalendar;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\Collection\ObjectCollection;
use ChurchCRM\Event;
use ChurchCRM\Calendar;
use Propel\Runtime\ActiveQuery\Criteria;

class AnniversariesCalendar implements SystemCalendar {
 
  public static function isAvailable() {
    return true;
  }
  
  public function getAccessToken() {
    return false;
  }

  public function getBackgroundColor() {
    return "000000";
  }
  
  public function getForegroundColor() {
    return "FFFFFF";
  }

  public function getId() {
    return 1;
  }

  public function getName() {
    return gettext("Anniversaries");
  }
    
  public function getEvents($start,$end) {
    $families = FamilyQuery::create()
            ->filterByWeddingdate('', Criteria::NOT_EQUAL)
            ->find();
    return $this->familyCollectionToEvents($families);       
  }
  
  public function getEventById($Id) {
    $families = FamilyQuery::create()
            ->filterByWeddingdate('', Criteria::NOT_EQUAL)
            ->filterById($Id)
            ->find();
    return $this->familyCollectionToEvents($families);  
  }
  
  private function familyCollectionToEvents(ObjectCollection $Families){
    $events = new ObjectCollection();
    $events->setModel("ChurchCRM\\Event");
    Foreach($Families as $family) {
      $anniversary = new Event();
      $anniversary->setId($family->getId());
      $anniversary->setEditable(false);
      $anniversary->setTitle(gettext("Anniversary").": ".$family->getFamilyString());
      $year = date('Y');
      $anniversary->setStart($year.'-'.$family->getWeddingMonth().'-'.$family->getWeddingDay());
      $anniversary->setURL($family->getViewURI());
      $events->push($anniversary);
    }
    return $events;
  }
}
