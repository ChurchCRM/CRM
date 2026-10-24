<?php

/**
 * scripts/test-password-check-timing.php — every User::isPasswordValid() answer
 * spends one bcrypt check (#10197).
 *
 * Usage:
 *   php scripts/test-password-check-timing.php
 *   npm run test:php
 *
 * The sign-in refusals for an unknown, locked or throttled account spend a
 * bcrypt check (SignInThrottle::spendPasswordCheckTime()). A wrong password for
 * an existing account must take about as long, or the timing tells which
 * usernames exist. A blank password and a legacy MD5 or SHA-256 hash are the
 * paths that could return without bcrypt. A bcrypt check at the current cost
 * takes far longer than FLOOR_MS on any machine, while a short-circuit takes
 * well under a millisecond, so the floor separates them without being flaky.
 *
 * It needs `composer install` in src/ but no database, web server or Config.php.
 *
 * Exit codes: 0 every case passed, 1 a case failed, 2 vendor autoloader missing.
 */

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\User;

const FLOOR_MS = 20.0;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "src/vendor/autoload.php is missing; run composer install in src/\n");
    exit(2);
}
require $autoload;

$failures = 0;
$case = 0;

function check(string $name, User $user, string $password, bool $expected): void
{
    global $failures, $case;
    $case++;
    $start = hrtime(true);
    $valid = $user->isPasswordValid($password);
    $ms = (hrtime(true) - $start) / 1e6;
    $ok = $valid === $expected && $ms >= FLOOR_MS;
    if (!$ok) {
        $failures++;
    }
    printf("%s %d - %s (%s, %.1f ms)\n", $ok ? 'ok' : 'not ok', $case, $name, $valid ? 'valid' : 'refused', $ms);
}

$bcrypt = new User();
$bcrypt->setPassword($bcrypt->hashPassword('correct horse'));
check('bcrypt account, right password', $bcrypt, 'correct horse', true);
check('bcrypt account, wrong password', $bcrypt, 'wrong', false);
check('bcrypt account, blank password', $bcrypt, '', false);
check('bcrypt account, password of spaces', $bcrypt, '   ', false);

$blankHash = new User();
$blankHash->setPassword($blankHash->hashPassword(''));
check('account holding a hash of a blank password, blank password', $blankHash, '', false);

$md5 = new User();
$md5->setPassword(md5('correct horse'));
check('legacy MD5 account, wrong password', $md5, 'wrong', false);
check('legacy MD5 account, blank password', $md5, '', false);

$sha = new User();
$sha->setPassword(hash('sha256', 'correct horse'));
check('legacy SHA-256 account, wrong password', $sha, 'wrong', false);

echo $failures === 0 ? "# all {$case} passed\n" : "# {$failures} of {$case} failed\n";
exit($failures === 0 ? 0 : 1);
