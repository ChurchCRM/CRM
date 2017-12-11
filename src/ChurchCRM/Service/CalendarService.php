<?php

/*******************************************************************************
 *
 *  filename    : CalendarService.php
 *  last change : 2017-11-16
 *  Copyright 2017 Logel Philippe
 *
 ******************************************************************************/

namespace ChurchCRM\Service;

use ChurchCRM\EventQuery;
use ChurchCRM\EventTypesQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Person;
use Propel\Runtime\ActiveQuery\Criteria;

class CalendarService
{
    public function getEventTypes()
    {
        $eventTypes = [];
        array_push($eventTypes, ['Name' => gettext('Event'), 'backgroundColor' =>'#f39c12']);
        array_push($eventTypes, ['Name' => gettext('Birthday'), 'backgroundColor' =>'#f56954']);
        array_push($eventTypes, ['Name' => gettext('Anniversary'), 'backgroundColor' =>'#0000ff']);

        return $eventTypes;
    }

    public function getEvents($start, $end)
    {
        $events = [];

        $startDate = date_create($start);
        $endDate = date_create($end);

        $startYear = $endYear = '1900';
        $endsNextYear = false;
        if ($endDate->format('Y') > $startDate->format('Y')) {
            $endYear = '1901';
            $endsNextYear = true;
        }

        $firstYear = $startDate->format('Y');
          
        $peopleWithBirthDays = PersonQuery::create()
          ->JoinWithFamily();
          
        // get the first and the last month
        $firstMonth = $startDate->format('m');
        $endMonth = $endDate->format('m');
        
        $month = $firstMonth;
          
        $peopleWithBirthDays->filterByBirthMonth($firstMonth);// the event aren't more than a month
        
        while ($month != $endMonth) {// we loop to have all the months from the first in the start to the end
          $month += 1;
          if ($month == 13) {
              $month = 1;
          }
          if ($month == 0) {
            $month = 1;
          }
          $peopleWithBirthDays->_or()->filterByBirthMonth($month);// the event aren't more than a month
        }
        
        $peopleWithBirthDays->find();

        foreach ($peopleWithBirthDays as $person) {
            $year = $firstYear;
            if ($person->getBirthMonth() == 1 && $endsNextYear) {
                $year = $firstYear + 1;
            }

            $start = date_create($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());

            $event = $this->createCalendarItem('birthday',
            $person->getFullName()." ".$person->getAge(), $start->format(DATE_ATOM), '', $person->getViewURI());

            array_push($events, $event);
        }
        
        // we search the Anniversaries
        $Anniversaries = FamilyQuery::create()
          ->filterByWeddingDate(['min' => '0001-00-00']) // a Wedding Date
          ->filterByDateDeactivated(null, Criteria::EQUAL) //Date Deactivated is null (active)
          ->find();
      
        $curYear = date('Y');
        $curMonth = date('m');
        foreach ($Anniversaries as $anniversary) {
            $year = $curYear;
            if ($anniversary->getWeddingMonth() < $curMonth) {
                $year = $year + 1;
            }
            $start = $year.'-'.$anniversary->getWeddingMonth().'-'.$anniversary->getWeddingDay();

            $event = $this->createCalendarItem('anniversary', $anniversary->getName(), $start, '', $anniversary->getViewURI());

            array_push($events, $event);
        }

        $activeEvents = EventQuery::create()
          ->filterByInActive('false')
          ->orderByStart()
          ->find();
        
        foreach ($activeEvents as $evnt) {
          $event = $this->createCalendarItem('event',
          $evnt->getTitle(), $evnt->getStart('Y-m-d H:i:s'), $evnt->getEnd('Y-m-d H:i:s'), $evnt->getEventURI(),$evnt->getID(),$evnt->getType(),$evnt->getGroupId());// only the event id sould be edited and moved and have custom color
          array_push($events, $event);
        }

        return $events;
    }
    
    private function toColor($n) {// so the color is now in function of the group
      $n = crc32($n);
      $n &= 0xffffffff;
      return ("#".substr("000000".dechex($n),-6));
    }

    public function createCalendarItem($type, $title, $start, $end, $uri,$eventID=0,$eventTypeID=0,$groupID=0)
    {
        $event = [];
        switch ($type) {
          case 'birthday':
            $event['backgroundColor'] = '#f56954';
            break;
          case 'event':
            $event['backgroundColor'] = $this->toColor($groupID);
            break;
          case 'anniversary':
            $event['backgroundColor'] = '#0000ff';
            break;
          default:
            $event['backgroundColor'] = '#eeeeee';
        }

        $event['title'] = $title;
        $event['start'] = $start;
        if ($end != '') {
            $event['end'] = $end;
            $event['allDay'] = false;
        }
        else 
        {
         $event['allDay'] = true;
        }
        if ($uri != '') {
            $event['url'] = $uri;
        }
        
        $event['type'] = $type;
        $event['eventID'] = $eventID;
        $event['eventTypeID'] = $eventTypeID;
        $event['groupID'] = $groupID;
        
        
        return $event;
    }
}
