<?php

use ChurchCRM\EventQuery;
use ChurchCRM\Calendar;


$publicEventsQuery = "SELECT * FROM events_event where event_publicly_visible = TRUE";

$statement = $connection->prepare($publicEventsQuery);
$statement->execute();
$PublicEvents = $statement->fetchAll();

if (count($PublicEvents) > 0) {
  $PublicCalendar = new Calendar();
  $PublicCalendar->setName(gettext("Public Calendar"));
  $PublicCalendar->setBackgroundColor("00AA00");
  $PublicCalendar->setForegroundColor("FFFFFF");
  $PublicCalendar->save();
  
  foreach ($PublicEvents as $PublicEvent) {
    $w = EventQuery::Create() ->findOneById($PublicEvent['event_id']);
    $w->addCalendar($PublicCalendar);
    $w->save();
  }
}

$privateEventsQuery = "SELECT * FROM events_event where event_publicly_visible = FALSE";

$statement = $connection->prepare($privateEventsQuery);
$statement->execute();
$PrivateEvents = $statement->fetchAll();


if (count($PrivateEvents) > 0) {
  $PrivateCalendar = new Calendar();
  $PrivateCalendar->setName(gettext("Private Calendar"));
  $PrivateCalendar->setBackgroundColor("0000AA");
  $PrivateCalendar->setForegroundColor("FFFFFF");
  $PrivateCalendar->save();
  
  foreach ($PrivateEvents as $PrivateEvent) {
    $w = EventQuery::Create() ->findOneById($PrivateEvent['event_id']);
    $w->addCalendar($PrivateCalendar);
    $w->save();
  }
}


$publicEventsQuery = "ALTER TABLE `events_event` "
        . "DROP COLUMN `event_publicly_visible`, "
        . "DROP COLUMN `event_grpid`";

$statement = $connection->prepare($publicEventsQuery);
$statement->execute();


