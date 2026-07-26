<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use Propel\Runtime\Collection\ObjectCollection;

class FundraisersCalendar implements SystemCalendar
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
        return '9C27B0';
    }

    public function getForegroundColor(): string
    {
        return 'FFFFFF';
    }

    public function getId(): int
    {
        return 2;
    }

    public function getName(): string
    {
        return gettext('Fundraisers');
    }

    public function getEvents(string $start, string $end): ObjectCollection
    {
        $startDate = $start !== '' ? new \DateTimeImmutable($start) : new \DateTimeImmutable('today');
        $endDate   = $end !== '' ? new \DateTimeImmutable($end) : $startDate->modify('+1 year');

        // Fetch broadly (fr_date <= range end) then filter the effective-end
        // boundary in PHP — mirrors BirthdaysCalendar/AnniversariesCalendar,
        // which also do their date-range filtering in PHP rather than SQL.
        $fundraisers = FundRaiserQuery::create()
            ->filterByDate(['max' => $endDate->format('Y-m-d')])
            ->find();

        return $this->fundraisersToEvents($fundraisers, $startDate, $endDate);
    }

    public function getEventById(int $Id): ObjectCollection
    {
        $fundraisers = FundRaiserQuery::create()->filterById($Id)->find();

        return $this->fundraisersToEvents($fundraisers);
    }

    private function fundraisersToEvents(ObjectCollection $fundraisers, ?\DateTimeImmutable $startDate = null, ?\DateTimeImmutable $endDate = null): ObjectCollection
    {
        $events = new ObjectCollection();
        $events->setModel(Event::class);

        foreach ($fundraisers as $fundraiser) {
            $frDate = $fundraiser->getDate();
            if ($frDate === null) {
                continue;
            }
            $frEndDate = $fundraiser->getEndDate() ?? $frDate;

            if ($startDate !== null && $endDate !== null
                && !$this->rangesOverlap($frDate, $frEndDate, $startDate, $endDate)) {
                continue;
            }

            // Marker on the start date only (FullCalendar all-day events here follow
            // the same single-point-in-time pattern as Birthdays/Anniversaries —
            // fr_EndDate has no time component to render as a real timed range).
            $title = $fundraiser->getTitle();
            if ($frEndDate->format('Y-m-d') !== $frDate->format('Y-m-d')) {
                $title .= ' (' . sprintf(gettext('thru %s'), $frEndDate->format('Y-m-d')) . ')';
            }

            $event = new Event();
            $event->setId($fundraiser->getId());
            $event->setEditable(false);
            $event->setStart($frDate->format('Y-m-d'));
            $event->setTitle($title);
            $event->setURL(SystemURLs::getRootPath() . '/fundraiser/view/' . $fundraiser->getId());

            $events->push($event);
        }

        return $events;
    }

    private function rangesOverlap(\DateTimeInterface $aStart, \DateTimeInterface $aEnd, \DateTimeInterface $bStart, \DateTimeInterface $bEnd): bool
    {
        return $aStart->format('Y-m-d') <= $bEnd->format('Y-m-d')
            && $aEnd->format('Y-m-d') >= $bStart->format('Y-m-d');
    }
}
