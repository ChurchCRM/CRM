<?php

/**
 * scripts/test-session-auth.php — regression tests for LocalAuthentication's
 * session state: a provider that merely holds a user (failed password, pending
 * 2FA, pre-upgrade session) must not validate as logged in, whatever
 * iSessionTimeout is set to.
 *
 * Usage:
 *   php scripts/test-session-auth.php
 *   npm run test:php
 *
 * Dependency-free like test-error-redaction.php: needs `composer install` in
 * src/ but no database, web server or Config.php. Every case returns before
 * validateUserSessionIsActive() reaches the database.
 *
 * Exit codes: 0 all passed, 1 a case failed, 2 vendor autoloader missing.
 */

declare(strict_types=1);

use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\model\ChurchCRM\User;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

$passed = 0;
$failed = 0;

$check = function (bool $ok, string $label) use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}\n";
};

$providerWith = function (array $state): LocalAuthentication {
    $provider = new LocalAuthentication();
    $provider->__unserialize($state);

    return $provider;
};

$loggerNoise = tempnam(sys_get_temp_dir(), 'session-auth-log');
$previousErrorLog = ini_set('error_log', $loggerNoise);

$user = new User();

$check(
    !(new LocalAuthentication())->validateUserSessionIsActive(false)->isAuthenticated,
    'a new provider is not authenticated'
);

$check(
    !$providerWith(['currentUser' => $user])->validateUserSessionIsActive(false)->isAuthenticated,
    'a provider holding a user but never completing login is not authenticated (failed password, or session from before the upgrade)'
);

$check(
    !$providerWith(['currentUser' => $user, 'bPendingTwoFactorAuth' => true])->validateUserSessionIsActive(false)->isAuthenticated,
    'a password-verified session still pending 2FA is not authenticated'
);

$check(
    !$providerWith(['currentUser' => $user, 'authenticated' => false, 'tLastOperationTimestamp' => time()])->validateUserSessionIsActive(false)->isAuthenticated,
    'a recent activity timestamp does not authenticate an unauthenticated provider'
);

$check(
    $providerWith(['currentUser' => $user, 'authenticated' => true])->__serialize()['authenticated'] === true,
    'the authenticated flag survives session serialization'
);

$check(
    $providerWith(['currentUser' => $user])->__serialize()['authenticated'] === false,
    'session state without the flag restores as unauthenticated'
);

$ended = $providerWith(['currentUser' => $user, 'authenticated' => true, 'bPendingTwoFactorAuth' => true]);
$ended->endSession();
$endedState = $ended->__serialize();
$check(
    $endedState['authenticated'] === false && $endedState['bPendingTwoFactorAuth'] === false && $endedState['currentUser'] === null,
    'endSession() clears the user, the authenticated flag and the pending-2FA flag'
);

ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
unlink($loggerNoise);

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
