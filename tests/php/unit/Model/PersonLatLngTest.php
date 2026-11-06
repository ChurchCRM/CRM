<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$family = new Family();
$family->setLatitude(40.5);
$family->setLongitude(-75.25);

$person = new Person();
$person->setAddress1('1 Own Street');
$person->setCity('Elsewhere');
$person->setFamily($family);
Assert::same(['Latitude' => 40.5, 'Longitude' => -75.25], $person->getLatLng(), 'a member with their own address pins at the stored family location');

Assert::same(['Latitude' => 0, 'Longitude' => 0], (new Person())->getLatLng(), 'a person without a family has no stored location');
