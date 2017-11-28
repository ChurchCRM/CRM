<?php
/*
 * File : MenuEventsCount.php
 *
 * Created by : Philippe by Hand.
 * User: Philippe Logel
 * Date: 11/26/2017
 * Time: 3:00 AM.
 */

namespace ChurchCRM\dto;
 
use ChurchCRM\EventQuery;
use ChurchCRM\Event;
use ChurchCRM\PersonQuery;
use ChurchCRM\Person;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Family;
use Propel\Runtime\ActiveQuery\Criteria;


class MenuEventsCount
{
    public static function getEventsOfToday()
    {
        $start_date = date("Y-m-d ")." 00:00:00";
        $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 day'));

        $activeEvents = EventQuery::create()
            ->where("event_start <= '".$start_date ."' AND event_end >= '".$end_date."'") /* the large events */
            ->_or()->where("event_start>='".$start_date."' AND event_end <= '".$end_date."'") /* the events of the day */
            ->find();

        return  $activeEvents;
    }

    public static function getNumberEventsOfToday()
    {
        return count(self::getEventsOfToday());
    }

    public static function getBirthDates()
    {
        $peopleWithBirthDays = PersonQuery::create()
            ->filterByBirthMonth(date('m'))
            ->filterByBirthDay(date('d'))
            ->find();
        
        return $peopleWithBirthDays;
    }

    public static function getNumberBirthDates()
    {
        return count(self::getBirthDates());
    }

    public static function getAnniversaries()
    {
        $Anniversaries = FamilyQuery::create()
              ->filterByWeddingDate(['min' => '0001-00-00']) // a Wedding Date
              ->filterByDateDeactivated(null, Criteria::EQUAL) //Date Deactivated is null (active)
              ->find();
      
        $curDay = date('d');
        $curMonth = date('m');
  
        $families = [];
        foreach ($Anniversaries as $anniversary) {
            if ($anniversary->getWeddingMonth() == $curMonth && $curDay == $anniversary->getWeddingDay()) {
                $families[] = $anniversary;
            }
        }
    
        return $families;
    }

    public static function getNumberAnniversaries()
    {
        return count(self::getAnniversaries());
    }
}
