<?php

namespace ChurchCRM\dto;

use ChurchCRM\Calendar;
use ChurchCRM\Event;

class FullCalendarEvent
{
    //the properties of this DTO are designed to align with the JSON object
    //expected by FullCalendar JS: https://fullcalendar.io/docs/event_data/Event_Object/

    public string $title;
    public string $start; // date-string
    public ?string $backgroundColor;
    public ?string $textColor;
    public ?string $end; // date-string
    public bool $allDay;
    public ?string $url;
    public string $id;
    public bool $editable;

    public static function createFromEvent(Event $CRMEvent, Calendar $CRMCalendar): self
    {
        $fce = new self();

        $fce->title = $CRMEvent->getTitle();
        $fce->start = $CRMEvent->getStart("c");
        $fce->end = $CRMEvent->getEnd("c");
        $fce->allDay =  ( $CRMEvent->getEnd() == null ? true : false);
        $fce->id = $CRMEvent->getId();
        $fce->backgroundColor = "#" . $CRMCalendar->getBackgroundColor();
        $fce->textColor = "#" . $CRMCalendar->getForegroundColor();
        $fce->editable = $CRMEvent->isEditable();
        
        $url = $CRMEvent->getURL();
        if ($url) {
            $fce->url = $url;
        }

        return $fce;
    }
}
