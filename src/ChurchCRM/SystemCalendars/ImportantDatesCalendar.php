<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonImportantDate;
use ChurchCRM\Service\ImportantDateService;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\Collection\ObjectCollection;

/**
 * Church-defined yearly dates (baptisms, dedications...) whose type is shown on
 * the calendar, placed on the day each is observed. Event ids encode the record:
 * even for a person's date, odd for a family's.
 */
class ImportantDatesCalendar implements StaffOnlySystemCalendar
{
    private const MAX_YEARS = 2;

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
        return '6F42C1';
    }

    public function getForegroundColor(): string
    {
        return 'FFFFFF';
    }

    public function getId(): int
    {
        return 4;
    }

    public function getName(): string
    {
        return gettext('Important Dates');
    }

    public function getEvents(string $start, string $end): ObjectCollection
    {
        $from = new \DateTimeImmutable($start, DateTimeUtils::getConfiguredTimezone());
        $to = new \DateTimeImmutable($end, DateTimeUtils::getConfiguredTimezone());
        if ($from->diff($to)->y > self::MAX_YEARS) {
            throw new \Exception(sprintf('Date range too large. Maximum allowed is %d years.', self::MAX_YEARS));
        }

        return $this->toEvents((new ImportantDateService())->calendarOccurrences($from, $to));
    }

    public function getEventById(int $Id): ObjectCollection
    {
        $year = DateTimeUtils::getCurrentYear();
        $occurrences = (new ImportantDateService())->calendarOccurrences(
            new \DateTimeImmutable("$year-01-01", DateTimeUtils::getConfiguredTimezone()),
            new \DateTimeImmutable(($year + 1) . '-12-31', DateTimeUtils::getConfiguredTimezone()),
        );

        return $this->toEvents(array_values(array_filter($occurrences, fn (array $o): bool => $this->eventId($o['record']) === $Id)));
    }

    private function toEvents(array $occurrences): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);
        foreach ($occurrences as $occurrence) {
            $owner = $occurrence['owner'];
            $name = $owner instanceof Person ? $owner->getFullName() : $owner->getSalutation();
            $title = sprintf(gettext('%1$s: %2$s'), $occurrence['type']->getName(), $name);
            if ($occurrence['years'] !== null && $occurrence['years'] > 0) {
                $title .= ' (' . sprintf(ngettext('%d year', '%d years', $occurrence['years']), $occurrence['years']) . ')';
            }

            $event = new Event();
            $event->setId($this->eventId($occurrence['record']));
            $event->setEditable(false);
            $event->setTitle($title);
            $event->setStart($occurrence['date']->format('Y-m-d'));
            $event->setURL($owner->getViewURI());
            $events->push($event);
        }

        return $events;
    }

    private function eventId(object $record): int
    {
        return $record instanceof PersonImportantDate ? (int) $record->getId() * 2 : (int) $record->getId() * 2 + 1;
    }
}
