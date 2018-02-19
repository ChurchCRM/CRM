<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

    namespace ChurchCRM\dto;
use ChurchCRM\Event;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Service\SystemService;
use Propel\Runtime\Collection\ObjectCollection;

Class iCal {
  
  private $icsHeader;
  private $eventsArray;

  public function __construct(ObjectCollection $Events, $CalendarName) {
    $this->eventsArray = $Events;
    $this->icsHeader =  "BEGIN:VCALENDAR\r\n".
                    "VERSION:2.0\r\n".
                    "PRODID:-//ChurchCRM/CRM//NONSGML v".SystemService::getInstalledVersion()."//EN\r\n".
                    "CALSCALE:GREGORIAN\r\n".
                    "METHOD:PUBLISH\r\n".
                    "X-WR-CALNAME:".$CalendarName."\r\n".
                    "X-WR-CALDESC:\r\n";
  }
  
  private function eventToVEVENT(Event $event) {
    $now = new \DateTime();
    $UTC = new \DateTimeZone("UTC");
        
    return "BEGIN:VEVENT\r\n".
          "UID:".$event->getId()."@".ChurchMetaData::getChurchName()."\r\n".
          "DTSTAMP:".$now->setTimezone($UTC)->format('Ymd\THis\Z')."\r\n".
          "DTSTART:".$event->getStart()->setTimezone($UTC)->format('Ymd\THis\Z')."\r\n".
          "DTEND:".$event->getEnd()->setTimezone($UTC)->format('Ymd\THis\Z')."\r\n".
          "SUMMARY:".$event->getTitle()."\r\n".
          "END:VEVENT\r\n";
  }
  
  public function toString() {
    $iCal = $this->icsHeader;
    foreach ($this->eventsArray as $event){
      $iCal .= $this->eventToVEVENT($event);
    }
    $iCal .="END:VCALENDAR";
    return $iCal;
    
  }
}