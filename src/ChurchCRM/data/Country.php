<?php
namespace ChurchCRM\data;

class Country
{
  private $countryCode;
  private $countryName;
  private $countryNameYasumi;
  public function __construct (string $CountryCode, string $CountryName, string $CountryNameYasumi = null)
  {
    $this->countryCode = $CountryCode;
    $this->countryName = $CountryName;
    $this->countryNameYasumi = $CountryNameYasumi;
  }
  
  public function getCountryCode(){
    return $this->countryCode;
  }
  
  public function getCountryName() {
    return $this->countryName;
  }
  
  public function getCountryNameYasumi() {
    return $this->countryNameYasumi;
  }
 
}