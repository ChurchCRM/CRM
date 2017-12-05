<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ChurchCRM\Dashboard;

use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\EventQuery;

class EventsDashboardItem implements DashboardItemInterface {
  
  public static function getDashboardItemRenderControl() {
    return "#EventsNumber";
  }

  public static function getDashboardItemName() {
    return "Events Counter";
  }

  public static function getDashboardItemValue() {
    $start_date = date("Y-m-d ")." 00:00:00";
        $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 day'));

        $activeEvents = EventQuery::create()
            ->where("event_start <= '".$start_date ."' AND event_end >= '".$end_date."'") /* the large events */
            ->_or()->where("event_start>='".$start_date."' AND event_end <= '".$end_date."'") /* the events of the day */
            ->find();

    return  count($activeEvents);
  }

  public static function shouldInclude($PageName) {
    return true; // this ID would be found on all pages.
  }

}