<?php

declare(strict_types=1);

use ChurchCRM\Utils\MiscUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$asOf = new DateTimeImmutable('2026-05-01');

Assert::same(36, MiscUtils::ageOn(new DateTimeImmutable('1990-05-01'), $asOf)->y, 'the birthday counts the new age');
Assert::same(35, MiscUtils::ageOn(new DateTimeImmutable('1990-05-02'), $asOf)->y, 'the day before the birthday keeps the old age');
Assert::same(0, MiscUtils::ageOn($asOf, $asOf)->y, 'born today is zero years old');
Assert::null(MiscUtils::ageOn(new DateTimeImmutable('2030-05-01'), $asOf), 'a birth date in the future has no age');

Assert::same('', MiscUtils::formatAge(5, 1, 2030), 'a future birth year shows no age');
Assert::same('', MiscUtils::formatAge(5, 1, null), 'no birth year shows no age');
