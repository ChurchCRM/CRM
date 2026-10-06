<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\VersionUtils;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Builds the iCalendar document a member's subscription URL serves
 * (design §5.3, "Subscribing").
 *
 * Why not `ChurchCRM\dto\iCal`: that class takes a Propel `ObjectCollection` of
 * `Event` rows, which the portal cannot supply. Half of what a member sees is
 * virtual — the Birthdays and Anniversaries calendars are computed per request
 * and their "events" are not rows — and the portal rewrites the titles it shows
 * for privacy. `PortalCalendarService::eventsBetween()` is where all of that has
 * already happened, so this builder consumes its arrays instead. The existing
 * per-calendar public ICS route is untouched.
 *
 * What it promises, and what a calendar app needs to accept the document:
 *
 *  - RFC 5545 line endings (CRLF) and folding at 75 octets, folding on a
 *    character boundary so a multi-byte name is never cut in half;
 *  - the TEXT escapes: backslash, semicolon, comma, and newline as `\n`;
 *  - a UID that is stable across fetches, so a calendar app updates an event in
 *    place rather than showing it twice;
 *  - UTC instants (`DTSTART`/`DTEND` with a `Z`) for timed events, and
 *    `VALUE=DATE` for whole-day ones — a birthday has no time of day, and
 *    giving it one would move it across the date line for half the world.
 */
class PortalCalendarFeed
{
    /** RFC 5545 §3.1: a content line is at most 75 octets before folding. */
    private const FOLD_OCTETS = 75;

    /**
     * @param array<int, array<string, mixed>> $events as PortalCalendarService::eventsBetween() shapes them
     */
    public static function build(array $events, string $calendarName, string $uidHost): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ChurchCRM/CRM//NONSGML v' . VersionUtils::getInstalledVersion() . '//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($calendarName),
            'X-PUBLISHED-TTL:PT1H',
        ];

        $timezone = ChurchMetaData::getChurchTimeZone();
        if ($timezone !== '') {
            // Not a VTIMEZONE — every instant below is already UTC — but a hint
            // apps use when they show the calendar's "home" zone.
            $lines[] = 'X-WR-TIMEZONE:' . self::escape($timezone);
        }

        $stamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

        foreach ($events as $event) {
            foreach (self::vevent($event, $stamp, $uidHost) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        $folded = array_map(static fn (string $line): string => self::fold($line), $lines);

        return implode("\r\n", $folded) . "\r\n";
    }

    /**
     * One event as a VEVENT, as a list of unfolded content lines.
     *
     * @param array<string, mixed> $event
     *
     * @return array<int, string>
     */
    private static function vevent(array $event, string $stamp, string $uidHost): array
    {
        $start = self::parse((string) ($event['start'] ?? ''));
        if ($start === null) {
            // An event with no readable start is not something a calendar app
            // can place; skipping it is better than emitting a broken VEVENT
            // that makes the whole document unparseable.
            return [];
        }

        $allDay = (bool) ($event['allDay'] ?? false);
        $end = self::parse((string) ($event['end'] ?? ''));

        $lines = [
            'BEGIN:VEVENT',
            'UID:' . self::uid($event, $start, $uidHost),
            'DTSTAMP:' . $stamp,
        ];

        if ($allDay) {
            // A whole-day event is a date, not an instant. DTEND is exclusive
            // in RFC 5545, so a one-day event ends on the following day.
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $start->modify('+1 day')->format('Ymd');
        } else {
            $utc = new DateTimeZone('UTC');
            $lines[] = 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . ($end ?? $start)->setTimezone($utc)->format('Ymd\THis\Z');
        }

        $lines[] = 'SUMMARY:' . self::escape((string) ($event['title'] ?? ''));

        $props = is_array($event['extendedProps'] ?? null) ? $event['extendedProps'] : [];

        $location = trim((string) ($props['location'] ?? ''));
        if ($location !== '') {
            $lines[] = 'LOCATION:' . self::escape($location);
        }

        $description = trim((string) ($props['description'] ?? ''));
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }

        $calendarName = trim((string) ($props['calendarName'] ?? ''));
        if ($calendarName !== '') {
            $lines[] = 'CATEGORIES:' . self::escape($calendarName);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * A UID that names this event on this installation and nothing else.
     *
     * It has to be stable across fetches — a calendar app keys on it — and it
     * has to differ between the occurrences a virtual calendar produces, since
     * every yearly repeat of one birthday shares the person's id. The event's
     * own composite id (`<type>-<calendarId>-<rowId>`) plus the day it falls on
     * gives both.
     *
     * The host part is the installation's own host, not the member's, and the
     * UID carries nothing about who is subscribed: two members who tick the
     * same calendar see identical UIDs, which is what lets them compare
     * calendars without either learning anything about the other's feed.
     *
     * @param array<string, mixed> $event
     */
    private static function uid(array $event, DateTimeInterface $start, string $uidHost): string
    {
        $key = (string) ($event['id'] ?? 'event');

        // The local part of a UID must not carry characters that would need
        // escaping; the ids the portal builds are already plain, but a plugin
        // calendar's could be anything.
        $key = preg_replace('/[^A-Za-z0-9._-]/', '-', $key) ?? 'event';

        return 'portal-' . $key . '-' . $start->format('Ymd') . '@' . $uidHost;
    }

    /**
     * A date string as PortalCalendarService emits it: either ISO 8601 with an
     * offset (a timed event) or a bare `Y-m-d` (a whole-day one).
     */
    private static function parse(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * RFC 5545 §3.3.11: inside a TEXT value, a backslash, a semicolon and a
     * comma are escaped with a backslash, and a line break becomes `\n`.
     * Control characters have no representation at all and are dropped.
     */
    private static function escape(string $value): string
    {
        $value = str_replace("\r\n", "\n", $value);
        $value = strtr($value, [
            '\\' => '\\\\',
            ';' => '\\;',
            ',' => '\\,',
            "\n" => '\\n',
            "\r" => '\\n',
        ]);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
    }

    /**
     * RFC 5545 §3.1: a content line longer than 75 octets is broken, and each
     * continuation begins with one space.
     *
     * The split is on a character boundary, not a byte one: cutting a UTF-8
     * sequence in half would make the document invalid, and a church name in a
     * non-Latin script is exactly where that would happen.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= self::FOLD_OCTETS) {
            return $line;
        }

        $out = '';
        $current = '';
        // The continuation space costs an octet, so every line after the first
        // may carry one fewer character's worth of payload.
        $limit = self::FOLD_OCTETS;

        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $out .= $current . "\r\n ";
                $current = '';
                $limit = self::FOLD_OCTETS - 1;
            }
            $current .= $character;
        }

        return $out . $current;
    }
}
