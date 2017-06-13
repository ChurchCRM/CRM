<?php

namespace ChurchCRM\Service;

use ChurchCRM\EventQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\PersonQuery;
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

        $perBirthDayThisYear = "STR_TO_DATE(CONCAT('1900-', 
      per.per_BirthMonth, '-', per.per_BirthDay), '%Y-%m-%d')";
        $perBirthDayNextYear = "STR_TO_DATE(CONCAT('1901-', 
      per.per_BirthMonth, '-', per.per_BirthDay), '%Y-%m-%d')";
        $dateRange = [
      $startDate->format($startYear.'-m-d'),
      $endDate->format($endYear.'-m-d'),
    ];

        $peopleWithBirthDays = PersonQuery::create('per')
      ->join('Family')
      ->condition('thisYear', $perBirthDayThisYear.' BETWEEN ? AND ?', $dateRange)
      ->condition('nextYear', $perBirthDayNextYear.' BETWEEN ? AND ?', $dateRange)
      ->combine(['thisYear', 'nextYear'], Criteria::LOGICAL_OR, 'birthDates')
      ->condition('greaterThanZero', 'per_BirthDay > 0 AND per_BirthMonth > 0')
      ->condition('active', 'Family.fam_DateDeactivated is null' )
      ->where(['greaterThanZero', 'birthDates', 'active'], Criteria::LOGICAL_AND)
      ->find();

        foreach ($peopleWithBirthDays as $person) {
            $year = $firstYear;
            if ($person->getBirthMonth() == 1 && $endsNextYear) {
                $year = $firstYear + 1;
            }

            $start = date_create($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());

            $event = $this->createCalendarItem('birthday',
        $person->getFullName(), $start->format(DATE_ATOM), '', $person->getViewURI());

            array_push($events, $event);
        }

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
        $evnt->getTitle(), $evnt->getStart('Y-m-d'), $evnt->getEnd('Y-m-d'), '');
            array_push($events, $event);
        }

        return $events;
    }

    public function createCalendarItem($type, $title, $start, $end, $uri)
    {
        $event = [];
        switch ($type) {
      case 'birthday':
        $event['backgroundColor'] = '#f56954';
        break;
      case 'event':
        $event['backgroundColor'] = '#f39c12';
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
        }
        if ($uri != '') {
            $event['url'] = $uri;
        }
        $event['allDay'] = true;

        return $event;
    }
}
