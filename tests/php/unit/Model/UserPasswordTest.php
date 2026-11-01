<?php

declare(strict_types=1);

use ChurchCRM\Authentication\SignInThrottle;
use ChurchCRM\model\ChurchCRM\User;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

const FLOOR_MS = 20.0;

$checkPassword = static function (string $name, User $user, string $password, bool $expected): void {
    $start = hrtime(true);
    $valid = $user->isPasswordValid($password);
    $ms = (hrtime(true) - $start) / 1e6;
    Assert::same($expected, $valid, $name);
    Assert::true($ms >= FLOOR_MS, $name . ': bcrypt work takes at least ' . FLOOR_MS . ' ms, got ' . $ms);
};

$bcrypt = new User();
$bcrypt->setPassword($bcrypt->hashPassword('correct horse'));
$checkPassword('bcrypt account, right password', $bcrypt, 'correct horse', true);
$checkPassword('bcrypt account, wrong password', $bcrypt, 'wrong', false);
$checkPassword('bcrypt account, blank password', $bcrypt, '', false);
$checkPassword('bcrypt account, password of spaces', $bcrypt, '   ', false);

$blankHash = new User();
$blankHash->setPassword($blankHash->hashPassword(''));
$checkPassword('account holding a hash of a blank password, blank password', $blankHash, '', false);

$md5 = new User();
$md5->setPassword('3cb4e732631f47e6eb961f34554b7cde');
$checkPassword('legacy MD5 account, wrong password', $md5, 'wrong', false);
$checkPassword('legacy MD5 account, blank password', $md5, '', false);

$sha = new User();
$sha->setPassword(hash('sha256', 'correct horse'));
$checkPassword('legacy SHA-256 account, wrong password', $sha, 'wrong', false);

// A hash saved before PHP 8.4 has cost 10, about a quarter of the refusals'
// cost-12 check. A wrong password against it must take about as long.
$timed = static function (callable $work): float {
    $start = hrtime(true);
    $work();

    return (hrtime(true) - $start) / 1e6;
};
$cost10 = new User();
$cost10->setPassword(password_hash('correct horse', PASSWORD_BCRYPT, ['cost' => 10]));
$refusalSamples = [];
$cost10Samples = [];
for ($sample = 0; $sample < 3; $sample++) {
    $refusalSamples[] = $timed(static fn () => SignInThrottle::spendPasswordCheckTime('wrong'));
    $cost10Samples[] = $timed(static fn () => $cost10->isPasswordValid('wrong'));
}
sort($refusalSamples);
sort($cost10Samples);
Assert::false($cost10->isPasswordValid('wrong'));
Assert::true($cost10Samples[1] >= 0.6 * $refusalSamples[1], 'Cost-10 refusal median matches the sign-in refusal work');
