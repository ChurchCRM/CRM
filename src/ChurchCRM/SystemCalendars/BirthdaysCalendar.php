<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\Interfaces\SystemCalendar;
use ChurchCRM\PersonQuery;
use Propel\Runtime\Collection\ObjectCollection;
use ChurchCRM\Event;
use ChurchCRM\Calendar;
use Propel\Runtime\ActiveQuery\Criteria;

class BirthdaysCalendar implements SystemCalendar {
 
  public static function isAvailable() {
    return true;
  }
  
  public function getAccessToken() {
    return false;
  }

  public function getBackgroundColor() {
    return "0000FF";
  }
  
  public function getForegroundColor() {
    return "FFFFFF";
  }

  public function getId() {
    return 0;
  }

  public function getName() {
    return gettext("Birthdays");
  }
    
  public function getEvents($start,$end) {
    $people = PersonQuery::create()
            ->filterByBirthDay('', Criteria::NOT_EQUAL)
            ->find();
    return $this->peopleCollectionToEvents($people);       
  }
  
  public function getEventById($Id) {
    $people = PersonQuery::create()
            ->filterByBirthDay('', Criteria::NOT_EQUAL)
            ->filterById($Id)
            ->find();
    return $this->peopleCollectionToEvents($people);  
  }
  
  private function peopleCollectionToEvents(ObjectCollection $People) {
    $events = new ObjectCollection();
    $events->setModel("ChurchCRM\\Event");
    Foreach($People as $person) {
      $birthday = new Event();
      $birthday->setId($person->getId());
      $birthday->setEditable(false);
      $year = date('Y');
      $birthday->setStart($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
      $age = $person->getAge($birthday->getStart());
      $birthday->setTitle(gettext("Birthday") . ": " . $person->getFullName() . ( $age ? " (".$age.")" : '' ));
      $birthday->setURL($person->getViewURI());
      $events->push($birthday);
    }
    return $events;
  }
}
