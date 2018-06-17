<?php

namespace ChurchCRM\Interfaces;


interface SystemCalendar {
  public function getId();
  public function getName();
  public function getAccessToken();
  public function getForegroundColor();  
  public function getBackgroundColor(); 
  public function getEvents();
  public function getEventById($Id);
  public static function isAvailable();
}
