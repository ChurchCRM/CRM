<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\FullCalendarEvent;
use ChurchCRM\dto\SystemCalendars;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Calendar;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\EventTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\SystemCalendars\AnniversariesCalendar;
use ChurchCRM\SystemCalendars\BirthdaysCalendar;
use ChurchCRM\SystemCalendars\SystemCalendar;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Throwable;

/**
 * Which calendars the Member Portal shows, and what is on them (design §5.3,
 * issue #9866).
 *
 * The choice is per calendar and lives in one JSON config item,
 * `aPortalCalendars`: a list of `{"type": "calendar"|"system", "id": <int>}`.
 * Two kinds of calendar have to fit in it — rows of the `calendars` table, and
 * the virtual "system" calendars (Birthdays, Anniversaries, Unpinned events and
 * whatever a plugin registers) which are computed on the fly and therefore
 * cannot carry a column — so the entry names the kind as well as the id.
 *
 * Nothing here trusts the stored list: `visible()` drops anything that is not a
 * calendar the church actually has, so deleting a calendar silently retires its
 * entry rather than leaving a dangling id that the portal would try to read.
 *
 * Privacy (design §5.3): the Birthdays and Anniversaries calendars are an
 * administrator's deliberate choice, and when they are shown the portal renders
 * a first name and a last initial — never a full surname, never an age, never
 * the year of a wedding.
 */
class PortalCalendarService
{
    /** A row of the `calendars` table: a church calendar or a ministry calendar. */
    public const TYPE_CALENDAR = 'calendar';

    /** A virtual calendar computed by ChurchCRM\dto\SystemCalendars. */
    public const TYPE_SYSTEM = 'system';

    /** The config item holding the administrator's choice. */
    public const CONFIG_NAME = 'aPortalCalendars';

    /**
     * Longest window `eventsBetween()` will answer, in days. A member browsing
     * month by month never asks for more; a script asking for a decade would
     * make the system calendars expand a birthday per person per year.
     */
    public const MAX_WINDOW_DAYS = 62;

    /**
     * Every calendar an administrator may switch on, church and system alike,
     * in the order the Calendars tab lists them.
     *
     * @return array<int, array{type: string, id: int, name: string, colors: array{background: string, foreground: string}, ministryId: int|null, visible: bool}>
     */
    public static function listChoices(): array
    {
        $visible = self::visibleKeys();
        $choices = [];

        foreach (CalendarQuery::create()->orderByName()->find() as $calendar) {
            $choices[] = self::describeCalendar($calendar, $visible);
        }

        foreach (self::systemCalendars() as $systemCalendar) {
            $id = (int) $systemCalendar->getId();
            $choices[] = [
                'type' => self::TYPE_SYSTEM,
                'id' => $id,
                'name' => $systemCalendar->getName(),
                'colors' => [
                    'background' => '#' . $systemCalendar->getBackgroundColor(),
                    'foreground' => '#' . $systemCalendar->getForegroundColor(),
                ],
                'ministryId' => null,
                'visible' => isset($visible[self::key(self::TYPE_SYSTEM, $id)]),
            ];
        }

        return $choices;
    }

    /**
     * The administrator's choice, cleaned of entries that no longer name a
     * calendar this installation has.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public static function visible(): array
    {
        $known = [];
        foreach (self::listChoicesRaw() as $choice) {
            $known[self::key($choice['type'], $choice['id'])] = $choice;
        }

        $entries = [];
        foreach (self::storedEntries() as $entry) {
            $key = self::key($entry['type'], $entry['id']);
            if (isset($known[$key]) && !isset($entries[$key])) {
                $entries[$key] = ['type' => $entry['type'], 'id' => $entry['id']];
            }
        }

        return array_values($entries);
    }

    /**
     * Replace the administrator's choice.
     *
     * Every entry has to name a calendar that exists, which is what keeps an
     * arbitrary id out of the config item; duplicates collapse, and order is
     * not meaningful.
     *
     * @param array<int, mixed> $entries
     *
     * @throws \InvalidArgumentException when an entry is malformed or names no calendar
     */
    public static function setVisible(array $entries): void
    {
        $known = [];
        foreach (self::listChoicesRaw() as $choice) {
            $known[self::key($choice['type'], $choice['id'])] = true;
        }

        $accepted = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['type'], $entry['id'])) {
                throw new \InvalidArgumentException(gettext('Each calendar must be given as a type and an id.'));
            }

            $type = (string) $entry['type'];
            if ($type !== self::TYPE_CALENDAR && $type !== self::TYPE_SYSTEM) {
                throw new \InvalidArgumentException(sprintf(
                    gettext('"%s" is not a kind of calendar this page knows about.'),
                    $type
                ));
            }

            if (!is_numeric($entry['id'])) {
                throw new \InvalidArgumentException(gettext('A calendar id must be a number.'));
            }
            $id = (int) $entry['id'];

            $key = self::key($type, $id);
            if (!isset($known[$key])) {
                throw new \InvalidArgumentException(sprintf(
                    gettext('There is no %1$s calendar with id %2$d.'),
                    $type,
                    $id
                ));
            }

            $accepted[$key] = ['type' => $type, 'id' => $id];
        }

        SystemConfig::setValue(self::CONFIG_NAME, json_encode(array_values($accepted)));
    }

    /**
     * Every event a member may see in `[$from, $to)`, from every calendar that
     * is switched on, shaped the way the rest of the application shapes events
     * for FullCalendar.
     *
     * Timezone: the shaping is `FullCalendarEvent::createFromEvent()`, exactly
     * as `/api/calendars/{id}/fullcalendar` and the public calendar use — a
     * timed event is `format('c')`, i.e. ISO 8601 carrying the offset of the
     * configured `sTimeZone` (PHP's default timezone after bootstrap), and an
     * all-day event is a bare `Y-m-d` with no time to misread. Nothing here
     * converts anything: the stored value is wall-clock in the church's zone
     * and that is what is served.
     *
     * The portal is read-only, so no event carries a URL into the admin area
     * and none is editable.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function eventsBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $entries = self::visible();
        if ($entries === []) {
            return [];
        }

        $calendarsById = [];
        foreach (CalendarQuery::create()->find() as $calendar) {
            $calendarsById[(int) $calendar->getId()] = $calendar;
        }

        $systemById = [];
        foreach (self::systemCalendars() as $systemCalendar) {
            $systemById[(int) $systemCalendar->getId()] = $systemCalendar;
        }

        $events = [];
        foreach ($entries as $entry) {
            if ($entry['type'] === self::TYPE_CALENDAR) {
                $calendar = $calendarsById[$entry['id']] ?? null;
                if ($calendar === null) {
                    continue;
                }
                foreach (self::churchCalendarEvents($calendar, $from, $to) as $event) {
                    $events[] = self::shape($event, $calendar, self::TYPE_CALENDAR, (int) $calendar->getId(), $calendar->getName());
                }
                continue;
            }

            $systemCalendar = $systemById[$entry['id']] ?? null;
            if ($systemCalendar === null) {
                continue;
            }
            $propelCalendar = SystemCalendars::toPropelCalendar($systemCalendar);
            foreach (self::systemCalendarEvents($systemCalendar, $from, $to) as $event) {
                $events[] = self::shape(
                    $event,
                    $propelCalendar,
                    self::TYPE_SYSTEM,
                    (int) $systemCalendar->getId(),
                    $systemCalendar->getName()
                );
            }
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) $a['start'], (string) $b['start']));

        return $events;
    }

    /**
     * The next few events a member may see, for the portal home page.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function upcoming(int $limit = 3): array
    {
        $from = new \DateTimeImmutable('today');
        $to = $from->modify('+' . self::MAX_WINDOW_DAYS . ' days');

        return array_slice(self::eventsBetween($from, $to), 0, max(0, $limit));
    }

    /**
     * Whether any calendar at all is shared with members — what the portal
     * calendar's empty state and the home page card ask.
     */
    public static function hasVisibleCalendars(): bool
    {
        return self::visible() !== [];
    }

    // ------------------------------------------------------------------ //

    /**
     * The choices without the `visible` flag, used by the paths that only need
     * to know which calendars exist.
     *
     * @return array<int, array{type: string, id: int, name: string, ministryId: int|null}>
     */
    private static function listChoicesRaw(): array
    {
        $choices = [];

        foreach (CalendarQuery::create()->find() as $calendar) {
            $choices[] = [
                'type' => self::TYPE_CALENDAR,
                'id' => (int) $calendar->getId(),
                'name' => (string) $calendar->getName(),
                'ministryId' => $calendar->getMinistryId() === null ? null : (int) $calendar->getMinistryId(),
            ];
        }

        foreach (self::systemCalendars() as $systemCalendar) {
            $choices[] = [
                'type' => self::TYPE_SYSTEM,
                'id' => (int) $systemCalendar->getId(),
                'name' => $systemCalendar->getName(),
                'ministryId' => null,
            ];
        }

        return $choices;
    }

    /**
     * @param array<string, bool> $visible
     *
     * @return array{type: string, id: int, name: string, colors: array{background: string, foreground: string}, ministryId: int|null, visible: bool}
     */
    private static function describeCalendar(Calendar $calendar, array $visible): array
    {
        $id = (int) $calendar->getId();

        return [
            'type' => self::TYPE_CALENDAR,
            'id' => $id,
            'name' => (string) $calendar->getName(),
            'colors' => [
                'background' => '#' . $calendar->getBackgroundColor(),
                'foreground' => '#' . $calendar->getForegroundColor(),
            ],
            'ministryId' => $calendar->getMinistryId() === null ? null : (int) $calendar->getMinistryId(),
            'visible' => isset($visible[self::key(self::TYPE_CALENDAR, $id)]),
        ];
    }

    /**
     * @return SystemCalendar[]
     */
    private static function systemCalendars(): array
    {
        return SystemCalendars::getCalendars();
    }

    /**
     * The stored list, normalised. A value that was never written, or written
     * as something other than a list of entries, reads as "nothing shared".
     *
     * @return array<int, array{type: string, id: int}>
     */
    private static function storedEntries(): array
    {
        try {
            $raw = SystemConfig::getValue(self::CONFIG_NAME);
        } catch (Throwable $e) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        $entries = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry) || !isset($entry['type'], $entry['id'])) {
                continue;
            }
            $type = (string) $entry['type'];
            if ($type !== self::TYPE_CALENDAR && $type !== self::TYPE_SYSTEM) {
                continue;
            }
            if (!is_numeric($entry['id'])) {
                continue;
            }
            $entries[] = ['type' => $type, 'id' => (int) $entry['id']];
        }

        return $entries;
    }

    /**
     * @return array<string, bool>
     */
    private static function visibleKeys(): array
    {
        $keys = [];
        foreach (self::visible() as $entry) {
            $keys[self::key($entry['type'], $entry['id'])] = true;
        }

        return $keys;
    }

    private static function key(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * The events pinned to one church calendar that overlap the window — the
     * same query `PublicCalendarMiddleware::getEvents()` runs for a shared
     * calendar, so a member and a public subscriber see the same set.
     *
     * @return iterable<Event>
     */
    private static function churchCalendarEvents(Calendar $calendar, DateTimeInterface $from, DateTimeInterface $to): iterable
    {
        return EventQuery::create()
            ->joinCalendarEvent()
            ->useCalendarEventQuery()
                ->filterByCalendar($calendar)
            ->endUse()
            // Keep events that overlap the window: they end after it starts (or
            // never end), and they start before it ends.
            //
            // The parentheses are load-bearing. Propel appends this clause with
            // AND, and `A AND B OR C` binds as `(A AND B) OR C` in SQL, so an
            // unparenthesised OR here would drop the calendar filter for every
            // row that matches its right-hand side — i.e. hand a member events
            // from calendars nobody shared with them.
            ->where('(events_event.event_end IS NULL OR events_event.event_end >= ?)', $from->format('Y-m-d H:i:s'))
            ->filterByStart($to, Criteria::LESS_THAN)
            ->orderBy(EventTableMap::COL_EVENT_START)
            ->find();
    }

    /**
     * The events of one system calendar for the window.
     *
     * System calendars take date strings and refuse a range of more than two
     * years; the window is capped long before that, and a calendar that throws
     * anyway is logged and skipped rather than failing the whole request — one
     * misbehaving plugin calendar must not empty a member's month.
     *
     * @return iterable<Event>
     */
    private static function systemCalendarEvents(SystemCalendar $calendar, DateTimeInterface $from, DateTimeInterface $to): iterable
    {
        try {
            return $calendar->getEvents($from->format('Y-m-d'), $to->format('Y-m-d'));
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Member Portal: a system calendar failed to list its events', [
                'calendar' => $calendar->getName(),
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * One event as the portal calendar consumes it.
     *
     * @return array<string, mixed>
     */
    private static function shape(
        Event $event,
        Calendar $calendar,
        string $sourceType,
        int $sourceId,
        string $sourceName
    ): array {
        $shaped = (array) FullCalendarEvent::createFromEvent($event, $calendar);

        // The portal is read-only and a member has no admin pages to be sent
        // to, so the event's own URL (a person, a family, a fundraiser) is
        // dropped and the detail panel is the only thing a click opens.
        unset($shaped['url'], $shaped['editable']);

        $extendedProps = is_array($shaped['extendedProps'] ?? null) ? $shaped['extendedProps'] : [];
        $extendedProps['calendarType'] = $sourceType;
        $extendedProps['calendarId'] = $sourceId;
        $extendedProps['calendarName'] = $sourceName;
        $extendedProps['location'] = self::locationName($event);

        $shaped['id'] = $sourceType . '-' . $sourceId . '-' . $event->getId();
        $shaped['title'] = self::displayTitle($event, $sourceType, $sourceId);
        $shaped['extendedProps'] = $extendedProps;

        return $shaped;
    }

    /**
     * The title a member sees.
     *
     * Birthdays and anniversaries are rewritten: the system calendars build
     * "Lena Black (34)" and "Anniversary: John & Jane Smith" for staff, and
     * neither an age nor a full surname belongs in the portal (design §5.3).
     */
    private static function displayTitle(Event $event, string $sourceType, int $sourceId): string
    {
        if ($sourceType !== self::TYPE_SYSTEM) {
            return (string) $event->getTitle();
        }

        if ($sourceId === (new BirthdaysCalendar())->getId()) {
            $person = PersonQuery::create()->findPk($event->getId());

            return $person === null
                ? gettext('Birthday')
                : self::abbreviate((string) $person->getFirstName(), (string) $person->getLastName());
        }

        if ($sourceId === (new AnniversariesCalendar())->getId()) {
            $family = FamilyQuery::create()->findPk($event->getId());
            if ($family === null) {
                return gettext('Anniversary');
            }

            $firstNames = [];
            $lastName = (string) $family->getName();
            foreach ($family->getAdults() as $adult) {
                $firstName = (string) $adult->getFirstName();
                if ($firstName !== '') {
                    $firstNames[] = $firstName;
                }
                if ($lastName === '') {
                    $lastName = (string) $adult->getLastName();
                }
            }

            $who = $firstNames === []
                ? self::initial($lastName)
                : implode(' & ', $firstNames) . ' ' . self::initial($lastName);

            return sprintf(gettext('Anniversary: %s'), trim($who));
        }

        return (string) $event->getTitle();
    }

    /**
     * "Lena", "Black" → "Lena B." — a first name and a last initial, the most
     * the portal ever says about somebody else's birthday.
     */
    private static function abbreviate(string $firstName, string $lastName): string
    {
        $initial = self::initial($lastName);
        $firstName = trim($firstName);

        if ($firstName === '') {
            return $initial;
        }

        return $initial === '' ? $firstName : $firstName . ' ' . $initial;
    }

    /**
     * The first character of a surname followed by a full stop, multibyte-safe.
     */
    private static function initial(string $lastName): string
    {
        $lastName = trim($lastName);

        return $lastName === '' ? '' : mb_substr($lastName, 0, 1) . '.';
    }

    /**
     * The event's location name, or '' when it has none. Most events carry
     * `location_id = 0`, which is not a row.
     */
    private static function locationName(Event $event): string
    {
        if ((int) $event->getLocationId() === 0) {
            return '';
        }

        try {
            $location = $event->getLocation();
        } catch (Throwable $e) {
            return '';
        }

        return $location === null ? '' : (string) $location->getLocationName();
    }
}
