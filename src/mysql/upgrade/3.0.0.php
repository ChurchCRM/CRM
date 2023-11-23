<?php

use ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\EventQuery;

$EventsQuery = 'SELECT * FROM events_event';

$statement = $connection->prepare($EventsQuery);
$statement->execute();
$Events = $statement->fetchAll();

$PublicCalendar = new Calendar();
$PublicCalendar->setName(gettext('Public Calendar'));
$PublicCalendar->setBackgroundColor('00AA00');
$PublicCalendar->setForegroundColor('FFFFFF');
$PublicCalendar->save();

$PrivateCalendar = new Calendar();
$PrivateCalendar->setName(gettext('Private Calendar'));
$PrivateCalendar->setBackgroundColor('0000AA');
$PrivateCalendar->setForegroundColor('FFFFFF');
$PrivateCalendar->save();

if (count($Events) > 0) {
    foreach ($Events as $Event) {
        $w = EventQuery::Create()->findOneById($Event['event_id']);
        if ($Event['event_publicly_visible']) {
            $w->addCalendar($PublicCalendar);
        } else {
            $w->addCalendar($PrivateCalendar);
        }
        $w->save();
    }
}

$publicEventsQuery = 'ALTER TABLE `events_event` '
        . 'DROP COLUMN `event_publicly_visible`, '
        . 'DROP COLUMN `event_grpid`';

$statement = $connection->prepare($publicEventsQuery);
$statement->execute();
