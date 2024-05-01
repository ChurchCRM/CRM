<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\model\ChurchCRM\EventQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;

class UnpinnedEvents implements SystemCalendar
{
    public static function isAvailable(): bool
    {
        return true;
    }

    public function getAccessToken(): bool
    {
        return false;
    }

    public function getBackgroundColor(): string
    {
        return 'FF0000';
    }

    public function getForegroundColor(): string
    {
        return 'FFFFFF';
    }

    public function getId(): int
    {
        return 3;
    }

    public function getName(): string
    {
        return gettext('Unpinned Events');
    }

    public function getEvents(string $start, string $end)
    {
        $Events = EventQuery::create()
            ->filterByStart(['min' => $start])
            ->filterByEnd(['max' => $end])
            ->useCalendarEventQuery(null, Criteria::LEFT_JOIN)
                ->filterByCalendarId(null)
            ->endUse()
            ->find();

        return $Events;
    }

    public function getEventById(int $Id)
    {
        $Event = EventQuery::create()
            ->findOneById($Id);

        return $Event;
    }
}
