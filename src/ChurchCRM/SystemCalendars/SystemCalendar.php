<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\model\ChurchCRM\Event;
use Propel\Runtime\Collection\ObjectCollection;

interface SystemCalendar
{
    public function getId(): int;

    public function getName(): string;

    public function getAccessToken(): bool;

    public function getForegroundColor(): string;

    public function getBackgroundColor(): string;

    public function getEvents(string $start, string $end): ObjectCollection;

    public function getEventById(int $Id): ObjectCollection;

    public static function isAvailable(): bool;
}
