<?php
namespace ChurchCRM\data;

class Country implements \JsonSerializable
{
  private string $countryCode;
  private string $countryName;
  private ?string $countryNameYasumi = null;
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

    public function jsonSerialize()
    {
        return ["name" => $this->countryName, "code" => $this->countryCode];
    }
}
