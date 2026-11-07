<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$family = new Family();
$family->setAddress1('1 Family Road');
$family->setLatitude(40.5);
$family->setLongitude(-75.25);

$person = new Person();
$person->setAddress1('1 Own Street');
$person->setCity('Elsewhere');
$person->setFamily($family);
Assert::same(['Latitude' => 40.5, 'Longitude' => -75.25], $person->getLatLng(), 'own address not geocoded: pins at the family');
Assert::same($family->getAddress(), $person->getMapAddress(), 'the label follows the family pin');

$person->recordGeocode(['Latitude' => 10.5, 'Longitude' => 20.5]);
Assert::same(['Latitude' => 10.5, 'Longitude' => 20.5], $person->getLatLng(), 'own address geocoded: pins at the person');
Assert::same($person->getAddress(), $person->getMapAddress(), 'the label follows the person pin');

$alone = new Person();
$alone->setAddress1('2 Alone Street');
$alone->recordGeocode(['Latitude' => 1.5, 'Longitude' => 2.5]);
Assert::same(['Latitude' => 1.5, 'Longitude' => 2.5], $alone->getLatLng(), 'a person without a family keeps a pin');

Assert::same(['Latitude' => 0, 'Longitude' => 0], (new Person())->getLatLng(), 'nothing stored: no pin');

// tried-and-missed is a different state from never tried
$fresh = new Person();
Assert::same(Person::GEOCODE_UNTRIED, $fresh->getGeocodeStatus());
$fresh->recordGeocode(null);
Assert::same(Person::GEOCODE_NOT_FOUND, $fresh->getGeocodeStatus());
$fresh->recordGeocode(['Latitude' => 3.5, 'Longitude' => 4.5]);
Assert::same(Person::GEOCODE_FOUND, $fresh->getGeocodeStatus());

$familyFresh = new Family();
Assert::same(Family::GEOCODE_UNTRIED, $familyFresh->getGeocodeStatus());
$familyFresh->recordGeocode(null);
Assert::same(Family::GEOCODE_NOT_FOUND, $familyFresh->getGeocodeStatus());

// an address change makes the stored result stale; a geocode column set by the caller is kept
$saved = new Person();
$saved->setAddress1('3 Old Street');
$saved->recordGeocode(['Latitude' => 5.5, 'Longitude' => 6.5]);
$saved->resetModified();
$saved->setCity('Newtown');
$saved->preSave();
Assert::same(Person::GEOCODE_UNTRIED, $saved->getGeocodeStatus(), 'an address change returns the row to never tried');

$saved->recordGeocode(['Latitude' => 5.5, 'Longitude' => 6.5]);
$saved->setCity('Othertown');
$saved->preSave();
Assert::same(Person::GEOCODE_FOUND, $saved->getGeocodeStatus(), 'coordinates set with the address are kept');
