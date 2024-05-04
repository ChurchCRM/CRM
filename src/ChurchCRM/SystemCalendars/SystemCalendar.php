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

    /**
     * @return ObjectCollection|array
     */
    public function getEvents(string $start, string $end);

    /**
     * TODO: this seems wrong. please fix this.
     *
     * @return ObjectCollection|array|Event|false
     */
    public function getEventById(int $Id);

    public static function isAvailable(): bool;
}
