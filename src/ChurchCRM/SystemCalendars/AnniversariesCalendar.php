<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Collection\ObjectCollection;

class AnniversariesCalendar implements SystemCalendar
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
        return '000000';
    }

    public function getForegroundColor(): string
    {
        return 'FFFFFF';
    }

    public function getId(): int
    {
        return 1;
    }

    public function getName(): string
    {
        return gettext('Anniversaries');
    }

    public function getEvents(string $start, string $end)
    {
        $families = FamilyQuery::create()
            ->filterByWeddingdate('', Criteria::NOT_EQUAL)
            ->find();

        return $this->familyCollectionToEvents($families);
    }

    public function getEventById(int $Id)
    {
        $families = FamilyQuery::create()
            ->filterByWeddingdate('', Criteria::NOT_EQUAL)
            ->filterById($Id)
            ->find();

        return $this->familyCollectionToEvents($families);
    }

    private function familyCollectionToEvents(ObjectCollection $Families): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);
        foreach ($Families as $family) {
            $anniversary = new Event();
            $anniversary->setId($family->getId());
            $anniversary->setEditable(false);
            $anniversary->setTitle(gettext('Anniversary') . ': ' . $family->getFamilyString());
            $year = date('Y');
            $anniversary->setStart($year . '-' . $family->getWeddingMonth() . '-' . $family->getWeddingDay());
            $anniversary->setURL($family->getViewURI());
            $events->push($anniversary);
        }

        return $events;
    }
}
