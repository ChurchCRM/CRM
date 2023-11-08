<?php

namespace ChurchCRM\data;

class Country implements \JsonSerializable
{
    private string $countryCode;
    private string $countryName;
    private ?string $countryNameYasumi = null;

    public function __construct(string $CountryCode, string $CountryName, string $CountryNameYasumi = null)
    {
        $this->countryCode = $CountryCode;
        $this->countryName = $CountryName;
        $this->countryNameYasumi = $CountryNameYasumi;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getCountryName(): string
    {
        return $this->countryName;
    }

    public function getCountryNameYasumi(): ?string
    {
        return $this->countryNameYasumi;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->countryName,
            'code' => $this->countryCode,
        ];
    }
}
