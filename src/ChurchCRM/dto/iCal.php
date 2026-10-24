<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\Utils\VersionUtils;
use Propel\Runtime\Collection\ObjectCollection;

class iCal
{
    private string $icsHeader;
    private ObjectCollection $eventsArray;

    public function __construct(ObjectCollection $Events, string $CalendarName)
    {
        $this->eventsArray = $Events;
        $this->icsHeader = "BEGIN:VCALENDAR\r\n" .
                    "VERSION:2.0\r\n" .
                    'PRODID:-//ChurchCRM/CRM//NONSGML v' . VersionUtils::getInstalledVersion() . "//EN\r\n" .
                    "CALSCALE:GREGORIAN\r\n" .
                    "METHOD:PUBLISH\r\n" .
                    'X-WR-CALNAME:' . self::escapeText($CalendarName) . "\r\n" .
                    "X-WR-CALDESC:\r\n";
    }

    /** RFC 5545 §3.3.11 TEXT escaping; control characters are dropped so a value cannot start a new property line. */
    public static function escapeText(string $text): string
    {
        $text = preg_replace('/\r\n|\r|\n/', "\n", $text);
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $text);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    private function eventToVEVENT(Event $event): string
    {
        $now = new \DateTime();
        $UTC = new \DateTimeZone('UTC');

        return "BEGIN:VEVENT\r\n" .
          'UID:' . $event->getId() . '@' . self::escapeText(ChurchMetaData::getChurchName()) . "\r\n" .
          'DTSTAMP:' . $now->setTimezone($UTC)->format('Ymd\THis\Z') . "\r\n" .
          'DTSTART:' . $event->getStart()->setTimezone($UTC)->format('Ymd\THis\Z') . "\r\n" .
          'DTEND:' . $event->getEnd()->setTimezone($UTC)->format('Ymd\THis\Z') . "\r\n" .
          'SUMMARY:' . self::escapeText((string) $event->getTitle()) . "\r\n" .
          "END:VEVENT\r\n";
    }

    public function toString(): string
    {
        $iCal = $this->icsHeader;
        foreach ($this->eventsArray as $event) {
            $iCal .= $this->eventToVEVENT($event);
        }

        return $iCal . 'END:VCALENDAR';
    }
}
