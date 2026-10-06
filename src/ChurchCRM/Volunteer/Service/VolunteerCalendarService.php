<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerCalendarGrantTableMap;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerCalendarGrant;
use ChurchCRM\model\ChurchCRM\VolunteerCalendarGrantQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\VolunteerException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Which calendars a ministry's events may be pinned to (D25, design §2.16 / §4.6).
 *
 * The global Add Events right opens every calendar. Without it, a ministry's coordinator may
 * pin that ministry's events to the ministry's own calendar and to every church calendar an
 * administrator has opened to the ministry (`volunteer_calendar_vcal`). `mayPin()` is the whole
 * rule; the event API asks it for every pin a write adds or removes, and the event editor asks
 * `pinnableCalendarIds()` which calendars to offer.
 *
 * Administering a calendar (name, colours, public link, delete) is a different question and
 * stays with `CalendarWriteRoleAuthMiddleware`: a grant lets a ministry add events, nothing more.
 */
class VolunteerCalendarService
{
    private LoggerInterface $logger;

    private VolunteerAuthorizationService $authz;

    /** @var array<int, int[]> ministry id => ids of the church calendars opened to it */
    private array $grantedCalendarIds = [];

    public function __construct(?VolunteerAuthorizationService $authz = null)
    {
        $this->authz = $authz ?? new VolunteerAuthorizationService();
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * May this user pin an event whose ministry is `$eventMinistryId` to `$calendar`?
     *
     *     allowed = canManageEvents()
     *            || (rollout on && eventMinistry !== null && canManageMinistry(user, eventMinistry)
     *                && (calendar.ministry_id === eventMinistry
     *                    || (calendar.ministry_id === null && granted(calendar, eventMinistry))))
     *
     * The event's ministry decides, not the calendar's: a coordinator of two ministries still may
     * not put one ministry's event on a calendar opened only to the other.
     */
    public function mayPin(User $user, ?int $eventMinistryId, Calendar $calendar): bool
    {
        if ($user->canManageEvents()) {
            return true;
        }

        if ($eventMinistryId === null || !User::isVolunteerV2Enabled()) {
            return false;
        }

        if (!$this->authz->canManageMinistry($user, $eventMinistryId)) {
            return false;
        }

        $ownerMinistryId = $calendar->getMinistryId();
        if ($ownerMinistryId !== null) {
            return (int) $ownerMinistryId === $eventMinistryId;
        }

        return in_array((int) $calendar->getId(), $this->grantedCalendarIds($eventMinistryId), true);
    }

    /**
     * The calendars this user may pin an event of `$eventMinistryId` to, by id.
     *
     * @return int[]
     */
    public function pinnableCalendarIds(User $user, ?int $eventMinistryId): array
    {
        $ids = [];
        foreach (CalendarQuery::create()->orderById()->find() as $calendar) {
            if ($this->mayPin($user, $eventMinistryId, $calendar)) {
                $ids[] = (int) $calendar->getId();
            }
        }

        return $ids;
    }

    /**
     * Every ministry, ordered by name, for the admin calendar page's "Ministries that may add
     * events" field. Empty unless the rollout is on and the user may manage the grants.
     *
     * @return array<int, array{id: int, name: string, active: bool}>
     */
    public function grantableMinistries(User $user): array
    {
        if (!User::isVolunteerV2Enabled() || !$user->canManageEvents()) {
            return [];
        }

        $ministries = [];
        foreach (VolunteerMinistryQuery::create()->orderByName()->find() as $ministry) {
            $ministries[] = [
                'id' => (int) $ministry->getId(),
                'name' => (string) $ministry->getName(),
                'active' => (bool) $ministry->getActive(),
            ];
        }

        return $ministries;
    }

    /**
     * The ministries a calendar is opened to, ordered by name.
     *
     * @return array<int, array{id: int, name: string, active: bool}>
     */
    public function getGrantedMinistries(Calendar $calendar): array
    {
        $ministries = VolunteerMinistryQuery::create()
            ->useVolunteerCalendarGrantQuery()
                ->filterByCalendarId((int) $calendar->getId())
            ->endUse()
            ->orderByName()
            ->find();

        $granted = [];
        foreach ($ministries as $ministry) {
            $granted[] = [
                'id' => (int) $ministry->getId(),
                'name' => (string) $ministry->getName(),
                'active' => (bool) $ministry->getActive(),
            ];
        }

        return $granted;
    }

    /**
     * Open a church calendar to exactly these ministries, replacing whatever it was opened to.
     *
     * @param int[] $ministryIds
     *
     * @return array<int, array{id: int, name: string, active: bool}> the grants now in force
     *
     * @throws VolunteerException 403 without Add Events, 409 for a ministry's own calendar,
     *                            400 for an unknown ministry
     */
    public function replaceGrants(Calendar $calendar, array $ministryIds, User $actor): array
    {
        if (!$actor->canManageEvents()) {
            throw VolunteerException::forbidden(gettext('Opening a calendar to ministries requires Add Events permission'));
        }

        if ($calendar->getMinistryId() !== null) {
            throw VolunteerException::conflict(gettext("A ministry's own calendar cannot be opened to other ministries"));
        }

        $ministryIds = array_values(array_unique(array_map('intval', $ministryIds)));
        if ($ministryIds !== []
            && VolunteerMinistryQuery::create()->filterById($ministryIds, Criteria::IN)->count() !== count($ministryIds)) {
            throw VolunteerException::invalid(gettext('Unknown volunteer ministry'));
        }

        $calendarId = (int) $calendar->getId();
        $connection = Propel::getWriteConnection(VolunteerCalendarGrantTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            VolunteerCalendarGrantQuery::create()->filterByCalendarId($calendarId)->delete($connection);
            foreach ($ministryIds as $ministryId) {
                $grant = new VolunteerCalendarGrant();
                $grant->setCalendarId($calendarId);
                $grant->setMinistryId($ministryId);
                $grant->save($connection);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        $this->grantedCalendarIds = [];

        $this->logger->info('Calendar opened to volunteer ministries', [
            'calendarId' => $calendarId,
            'ministryIds' => $ministryIds,
            'actor' => (int) $actor->getId(),
        ]);

        return $this->getGrantedMinistries($calendar);
    }

    /** @return int[] */
    private function grantedCalendarIds(int $ministryId): array
    {
        if (!array_key_exists($ministryId, $this->grantedCalendarIds)) {
            $this->grantedCalendarIds[$ministryId] = array_map(
                'intval',
                VolunteerCalendarGrantQuery::create()
                    ->filterByMinistryId($ministryId)
                    ->select(['CalendarId'])
                    ->find()
                    ->toArray()
            );
        }

        return $this->grantedCalendarIds[$ministryId];
    }
}
