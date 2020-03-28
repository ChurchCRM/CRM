<?php

namespace ChurchCRM\Utils;

class ExecutionTime
{
  // inspired from https://stackoverflow.com/a/22885011
  private $startTime;
  private $endTime;
  private $startR;
  private $endR;
  public function __construct(){
    $this->startTime = self::getNow();
    $this->startR = getrusage();
  }

  public function End(){
    $this->endTime = self::getNow();
    $this->endR = getrusage();
  }
  
  public function getMiliseconds() {
    // if End() has not yet been called, this returns the current number of running seconds.  
    // Otherwise, returns the ending number of seconds
    if (is_null($this->endTime)){
      $value = (self::getNow() - $this->startTime)*1000;
    }
    else {
      $value = ($this->endTime - $this->startTime)*1000;
    }
    return round($value,2);
  }

  public static function getNow() {
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
      return hrtime(TRUE);
    }
    else {
      return microtime(TRUE);
    }
  }

  private function runTime($rEnd, $rStart, $index) {
      return self::microseconsToMiliseconds($rEnd["$index"] - $rStart["$index"]);
  }    

  public function __toString(){
      return "This process used " . $this->runTime($this->endR, $this->startR, "ru_utime.tv_usec") ." ms for its computations\n".
      "It spent " . $this->runTime($this->endR, $this->startR, "ru_stime.tv_usec") ." ms in system calls\n".
      "Real time used(ms) : " . self::nanosecondsToMiliseconds($this->endTime - $this->startTime);
  }

  private static function nanosecondsToMiliseconds($nanos) {
    return $nanos/1000000;
  }

  private static function microseconsToMiliseconds($micros) {
    return $micros/1000;
  }
}