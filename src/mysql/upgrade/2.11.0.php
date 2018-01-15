<?php

use ChurchCRM\Event;
use ChurchCRM\Calendar;


$publicEventsQuery = "SELECT * FROM events_event where event_publicly_visible = TRUE";

$statement = $connection->prepare($publicEventsQuery);
$statement->execute();
$PublicEvents = $statement->fetchAll();

if (count($PublicEvents) > 0) {
  $PublicCalendar = new Calendar();
  $PublicCalendar->setName(gettext("Public Calendar"));
  $PublicCalendar->save();

  foreach ($PublicEvents as $PublicEvent) {
    $w = new Event();
    $w->setType($PublicEvent['event_type']);
    $w->setTitle($PublicEvent['event_title']);
    $w->setDesc($PublicEvent['event_desc']);
    $w->setText($PublicEvent['event_text']);
    $w->setStart($PublicEvent['event_start']);
    $w->setEnd($PublicEvent['event_end']);
    $w->setInActive($PublicEvent['inactive']);
    $w->setTypeName($PublicEvent['event_typename']);
    $w->addCalendar($PublicCalendar);
    $w->save();
  }
  
  
  
}


$publicEventsQuery = "ALTER TABLE `events_event` "
        . "DROP COLUMN `event_publicly_visible`, "
        . "DROP COLUMN `event_grpid`";

$statement = $connection->prepare($publicEventsQuery);
$statement->execute();


