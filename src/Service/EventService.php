<?php

class EventService
{

  function getEventsByPerson($id)
  {
    $sSQL = "SELECT event.event_title, event.event_desc, event.event_start, event.event_end
            FROM events_event AS event, event_attend
              WHERE event.event_id = event_attend.event_id
              AND event_attend.person_id =" . $id . "
            ORDER BY event_start DESC LIMIT 10";
    $result = RunQuery($sSQL);

    $return = array();
    while ($row = mysql_fetch_array($result)) {
      $values['title'] = $row['event_title'];
      $values['desc'] = $row['event_desc'];
      $values['date'] = $row['event_start'];
      array_push($return, $values);
    }

    return $return;
  }

}
