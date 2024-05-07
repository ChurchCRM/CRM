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

    public function getEvents(string $start, string $end)
    {
        $people = PersonQuery::create()
            ->filterByBirthDay('', Criteria::NOT_EQUAL)
            ->find();

        return $this->peopleCollectionToEvents($people);
    }

    public function getEventById(int $Id)
    {
        $people = PersonQuery::create()
            ->filterByBirthDay('', Criteria::NOT_EQUAL)
            ->filterById($Id)
            ->find();

        return $this->peopleCollectionToEvents($people);
    }

    private function peopleCollectionToEvents(ObjectCollection $People): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);
        foreach ($People as $person) {
            for ($year = date('Y'); $year <= date('Y') + 1; $year++) {
                $birthday = new Event();
                $birthday->setId($person->getId());
                $birthday->setEditable(false);
                $eventDate = $year . '-' . $person->getBirthMonth() . '-' . $person->getBirthDay();
                $birthday->setStart($eventDate);
                $age = $person->getAge($eventDate);
                $birthday->setTitle($person->getFullName() . ($age ? ' (' . $age . ')' : ''));
                $birthday->setURL($person->getViewURI());
                $events->push($birthday);
            }
        }

        return $events;
    }
}
