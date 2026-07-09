<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\DateTimeUtils;
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

    public function getEvents(string $start, string $end): ObjectCollection
    {
        $families = FamilyQuery::create()
            ->filterByWeddingdate(null, Criteria::ISNOTNULL)
            ->filterByDateDeactivated(null)
            ->find();

        return $this->familyCollectionToEvents($families, $start, $end);
    }

    public function getEventById(int $Id): ObjectCollection
    {
        $families = FamilyQuery::create()
            ->filterByWeddingdate(null, Criteria::ISNOTNULL)
            ->filterByDateDeactivated(null)
            ->filterById($Id)
            ->find();

        return $this->familyCollectionToEvents($families);
    }

    private function familyCollectionToEvents(ObjectCollection $Families, ?string $start = null, ?string $end = null): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);

        $currentYear = DateTimeUtils::getCurrentYear();
        $startDate = $start ? new \DateTimeImmutable($start) : new \DateTimeImmutable($currentYear . '-01-01');
        $endDate = $end ? new \DateTimeImmutable($end) : new \DateTimeImmutable(($currentYear + 1) . '-12-31');

        $maxYears = 2;
        if ($startDate->diff($endDate)->y > $maxYears) {
            throw new \Exception("Date range too large. Maximum allowed is {$maxYears} years.");
        }

        $years = [];
        for ($year = (int) $startDate->format('Y'); $year <= (int) $endDate->format('Y'); $year++) {
            $years[] = $year;
        }

        foreach ($Families as $family) {
            $weddingMonth = $family->getWeddingMonth();
            $weddingDay = $family->getWeddingDay();

            if ($weddingMonth === '' || $weddingDay === '') {
                continue;
            }

            foreach ($years as $year) {
                $eventDateStr = sprintf('%04d-%02d-%02d', $year, (int) $weddingMonth, (int) $weddingDay);
                $eventDateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $eventDateStr);
                if (!$eventDateObj) {
                    continue;
                }
                if (!$this->isDateInRange($eventDateObj, $startDate, $endDate)) {
                    continue;
                }

                $anniversary = new Event();
                $anniversary->setId($family->getId());
                $anniversary->setEditable(false);
                $anniversary->setTitle(gettext('Anniversary') . ': ' . $family->getFamilyString());
                $anniversary->setStart($eventDateStr);
                $anniversary->setURL($family->getViewURI());
                $events->push($anniversary);
            }
        }

        return $events;
    }

    private function isDateInRange(\DateTimeImmutable $date, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): bool
    {
        $d = $date->format('Y-m-d');
        $s = $startDate->format('Y-m-d');
        $e = $endDate->format('Y-m-d');

        return ($d >= $s && $d <= $e);
    }
}
