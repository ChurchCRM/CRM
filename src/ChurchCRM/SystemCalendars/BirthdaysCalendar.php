<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\Interfaces\SystemCalendar;
use ChurchCRM\PersonQuery;
use Propel\Runtime\Collection\ObjectCollection;
use ChurchCRM\Event;
use ChurchCRM\Calendar;

class BirthdaysCalendar implements SystemCalendar {
 
  public function getAccessToken() {
    return false;
  }

  public function getBackgroundColor() {
    return "FF0000";
  }
  
  public function getForegroundColor() {
    return "000000";
  }

  public function getId() {
    return 0;
  }

  public function getName() {
    return gettext("Birthdays");
  }
    
  public function getEvents() {
    $events = new ObjectCollection();
    $events->setModel("ChurchCRM\\Event");
    $people = PersonQuery::create()
            ->filterByBirthDay('', \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
            ->find();
    Foreach($people as $person) {
      $birthday = new Event();
      $birthday->setId($person->getId());
      $birthday->setEditable(false);
      $birthday->setTitle(gettext("Birthday: ".$person->getFullName()));
      $year = date('Y');
      $birthday->setStart($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
      $events->push(clone $birthday);
      $year -= 1;
      $birthday->setStart($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
      $events->push($birthday);
    }
   
    return $events;
            
  }
  
  public function getEventById($Id) {
    $events = new ObjectCollection();
    $events->setModel("ChurchCRM\\Event");
    $people = PersonQuery::create()
            ->filterByBirthDay('', \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
            ->filterById($Id)
            ->find();
    Foreach($people as $person) {
      $birthday = new Event();
      $birthday->setId($person->getId());
      $birthday->setEditable(false);
      $birthday->setTitle(gettext("Birthday: ".$person->getFullName()));
      $year = date('Y');
      $birthday->setStart($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
      $events->push(clone $birthday);
      $year -= 1;
      $birthday->setStart($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
      $events->push($birthday);
    }
   
    return $events;
            
  }
}
