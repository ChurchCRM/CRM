<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\SystemCalendars\FundraisersCalendar;
use ChurchCRM\SystemCalendars\SystemCalendar;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;

class CalendarAccessService
{
    /**
     * Global Add Events right, or coordinating the volunteer ministry that owns the calendar.
     */
    public function canWriteCalendar(User $user, ?Calendar $calendar): bool
    {
        if ($user->canManageEvents()) {
            return true;
        }

        if ($calendar === null || !User::isVolunteerV2Enabled()) {
            return false;
        }

        $ministryId = $calendar->getMinistryId();
        if ($ministryId === null) {
            return false;
        }

        return (new VolunteerAuthorizationService())->canManageMinistry($user, (int) $ministryId);
    }

    /**
     * Calendars as API arrays. The public-feed access token is a shareable secret, so only
     * users who may administer that calendar receive it.
     *
     * @param iterable<Calendar> $calendars
     *
     * @return array<int, array<string, mixed>>
     */
    public function toApiArray(iterable $calendars, User $user): array
    {
        $rows = [];
        foreach ($calendars as $calendar) {
            $row = $calendar->toArray();
            if (!$this->canWriteCalendar($user, $calendar)) {
                $row['AccessToken'] = null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function canViewSystemCalendar(User $user, SystemCalendar $calendar): bool
    {
        if ($calendar instanceof FundraisersCalendar) {
            return $user->isManageFundraisersEnabled();
        }

        return true;
    }
}
