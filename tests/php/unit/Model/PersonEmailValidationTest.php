<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$person = (new Person())->setFirstName('Email')->setLastName('Example');
Assert::true($person->validate());
foreach (['setEmail', 'setWorkEmail'] as $setter) {
    $person->$setter('josé@example.org');
    Assert::false($person->validate());
    Assert::contains('ASCII', $person->getValidationFailures()[0]->getMessage());
    Assert::exception(fn () => $person->preSave(), InvalidArgumentException::class);
    $person->$setter('jose@bücher.org');
    Assert::true($person->validate());
    $person->$setter('');
    Assert::true($person->validate());
}
$person->setEmail('josé@example.org')->resetModified();
Assert::true($person->validate());
$person->setLastName('Changed');
Assert::true($person->validate());
$person->setFirstName('A');
Assert::false($person->validate());
