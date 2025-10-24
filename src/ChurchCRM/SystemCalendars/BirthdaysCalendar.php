<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;

class BirthdaysCalendar implements SystemCalendar
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
        return '0000FF';
    }

    public function getForegroundColor(): string
    {
        return 'FFFFFF';
    }

    public function getId(): int
    {
        return 0;
    }

    public function getName(): string
    {
        return gettext('Birthdays');
    }

    public function getEvents(?string $start = null, ?string $end = null): ObjectCollection
    {
        $people = PersonQuery::create()
            ->filterByBirthDay(0, Criteria::GREATER_THAN)
            ->filterByBirthMonth(0, Criteria::GREATER_THAN)
            ->find();

        return $this->peopleCollectionToEvents($people, $start, $end);
    }

    public function getEventById(int $Id): ObjectCollection
    {
        $people = PersonQuery::create()
            ->filterByBirthDay(0, Criteria::GREATER_THAN)
            ->filterByBirthMonth(0, Criteria::GREATER_THAN)
            ->filterById($Id)
            ->find();

        return $this->peopleCollectionToEvents($people);
    }

    private function peopleCollectionToEvents(ObjectCollection $People, ?string $start = null, ?string $end = null): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);

        $startDate = $start ? new \DateTimeImmutable($start) : new \DateTimeImmutable(date('Y') . '-01-01');
        $endDate = $end ? new \DateTimeImmutable($end) : new \DateTimeImmutable((date('Y') + 1) . '-12-31');

        $maxYears = 2;
        if ($startDate->diff($endDate)->y > $maxYears) {
            throw new \Exception("Date range too large. Maximum allowed is {$maxYears} years.");
        }

        $years = [];
        for ($year = (int)$startDate->format('Y'); $year <= (int)$endDate->format('Y'); $year++) {
            $years[] = $year;
        }

        foreach ($People as $person) {
            $birthMonth = (int)$person->getBirthMonth();
            $birthDay = (int)$person->getBirthDay();

            foreach ($years as $year) {
                $eventDateStr = sprintf('%04d-%02d-%02d', $year, $birthMonth, $birthDay);
                $eventDateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $eventDateStr);
                if (!$eventDateObj) {
                    continue;
                }
                $eventDateStr = sprintf('%04d-%02d-%02d', $year, $birthMonth, $birthDay);
                if (!$this->isDateInRange($eventDateObj, $startDate, $endDate)) {
                    continue;
                }

                $birthday = new Event();
                $birthday->setId($person->getId());
                $birthday->setEditable(false);
                $birthday->setStart($eventDateStr);
                $age = $person->getAge($eventDateStr);
                $birthday->setTitle($person->getFullName() . ($age ? ' (' . $age . ')' : ''));
                $birthday->setURL($person->getViewURI());

                $events->push($birthday);
            }
        }

        return $events;
    }

    private function isDateInRange(\DateTimeImmutable $date, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): bool
    {
        // Convert all to Y-m-d strings, so time and timezone are ignored
        $d = $date->format('Y-m-d');
        $s = $startDate->format('Y-m-d');
        $e = $endDate->format('Y-m-d');
        return ($d >= $s && $d <= $e);
    }
}
