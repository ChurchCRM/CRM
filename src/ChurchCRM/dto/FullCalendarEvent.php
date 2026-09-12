<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\Event;

class FullCalendarEvent
{
    //the properties of this DTO are designed to align with the JSON object
    //expected by FullCalendar JS: https://fullcalendar.io/docs/event_data/Event_Object/

    public string $title;
    public string $start; // date-string
    public ?string $color = null;
    public ?string $contrastColor = null;
    public ?string $end = null; // date-string
    public bool $allDay;
    public ?string $url = null;
    public string $id;
    public bool $editable;
    /** @var array<string,mixed>|null Extra metadata passed through to FullCalendar extendedProps */
    public ?array $extendedProps = null;

    /**
     * @param array{gapCount: int, liveCount: int, requiredCount: int, staffed: bool, occurrenceIds: int[]}|null $volunteerStaffing
     *     Volunteer v2 staffing roll-up for this event (#9713, design §3.5), or null when the
     *     event has no V2 occurrence the caller may see. Passed in rather than derived here:
     *     the caller resolves it for the whole feed in one call
     *     (`VolunteerAssignmentService::getEventStaffingSummary()`), because deriving it per
     *     event would fan a month of calendar out into one query per row — and because that
     *     is also where the per-caller scoping lives.
     */
    public static function createFromEvent(Event $CRMEvent, Calendar $CRMCalendar, ?array $volunteerStaffing = null): self
    {
        $fce = new self();

        $fce->title = $CRMEvent->getTitle();
        $fce->allDay = ($CRMEvent->getEnd() === null ? true : false);
        
        // For all-day events, use date-only format (Y-m-d) to avoid timezone issues
        // For timed events, use ISO 8601 format (c) which includes time and timezone
        if ($fce->allDay) {
            $fce->start = $CRMEvent->getStart('Y-m-d');
            $fce->end = null; // All-day events don't need an end date in FullCalendar
        } else {
            $fce->start = $CRMEvent->getStart('c');
            $fce->end = $CRMEvent->getEnd('c');
        }
        
        $fce->id = $CRMEvent->getId();
        $fce->color = '#' . $CRMCalendar->getBackgroundColor();
        $fce->contrastColor = '#' . $CRMCalendar->getForegroundColor();
        $fce->editable = $CRMEvent->isEditable();

        $url = $CRMEvent->getURL();
        if ($url) {
            $fce->url = $url;
        }

        // Build extendedProps from description and holiday metadata
        $extendedProps = [];

        $desc = $CRMEvent->getDesc();
        if ($desc) {
            $extendedProps['description'] = strip_tags($desc);
        }

        try {
            $country = $CRMEvent->getVirtualColumn('holidayCountry');
            $type    = $CRMEvent->getVirtualColumn('holidayType');
            if ($country !== null) {
                $extendedProps['country'] = $country;
            }
            if ($type !== null) {
                $extendedProps['type'] = $type;
            }
        } catch (\Throwable $e) {
            // not a holiday event — virtual columns absent
        }

        // Volunteer v2 staffing (#9713, §3.5). Only set when the caller may actually see
        // this event's staffing: an event with no visible V2 occurrence keeps exactly the
        // extendedProps it had before, so nothing downstream has to special-case a zero.
        if ($volunteerStaffing !== null) {
            $extendedProps['volunteerGapCount'] = (int) $volunteerStaffing['gapCount'];
            $extendedProps['volunteerStaffed'] = (bool) $volunteerStaffing['staffed'];
        }

        if (!empty($extendedProps)) {
            $fce->extendedProps = $extendedProps;
        }

        return $fce;
    }
}
