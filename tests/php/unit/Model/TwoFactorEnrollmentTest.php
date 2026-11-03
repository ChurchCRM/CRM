<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\KeyManagerUtils;
use Defuse\Crypto\Crypto;
use PragmaRX\Google2FA\Google2FA;
use Propel\Runtime\Connection\ConnectionInterface;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

final class EnrollmentUser extends User
{
    public array $savedEnrollments = [];

    public function save(?ConnectionInterface $con = null): int
    {
        $this->savedEnrollments[] = [$this->getTwoFactorAuthSecret(), $this->getTwoFactorAuthGracePeriodStart()];

        return 1;
    }
}

KeyManagerUtils::init(bin2hex(random_bytes(32)));
$google2fa = new Google2FA();

Assert::false((new EnrollmentUser())->confirmProvisional2FACode('000000'));
session_start(['use_cookies' => false, 'cache_limiter' => '']);
try {
    Assert::false((new EnrollmentUser())->confirmProvisional2FACode('000000'));

    $firstRequest = new EnrollmentUser();
    $secret = $firstRequest->provisionNew2FAKey();
    $encrypted = $_SESSION['provisional2FAKey'];
    Assert::notSame($secret, $encrypted);
    Assert::same($secret, Crypto::decryptWithPassword($encrypted, KeyManagerUtils::getTwoFASecretKey()));
    Assert::null($firstRequest->getTwoFactorAuthSecret());
    Assert::same([], $firstRequest->savedEnrollments);
    unset($firstRequest);

    session_write_close();
    $_SESSION = [];
    session_start(['use_cookies' => false, 'cache_limiter' => '']);
    Assert::same($encrypted, $_SESSION['provisional2FAKey']);

    $confirmationRequest = new EnrollmentUser();
    $confirmationRequest->setTwoFactorAuthGracePeriodStart('2026-01-01');
    $wrongCode = '000000';
    while ($google2fa->verifyKey($secret, $wrongCode, 2)) {
        $wrongCode = str_pad((string) ((int) $wrongCode + 1), 6, '0', STR_PAD_LEFT);
    }
    Assert::false($confirmationRequest->confirmProvisional2FACode($wrongCode));
    Assert::null($confirmationRequest->getTwoFactorAuthSecret());
    Assert::same([], $confirmationRequest->savedEnrollments);
    Assert::same($encrypted, $_SESSION['provisional2FAKey']);

    $confirmationRequest = new EnrollmentUser();
    $confirmationRequest->setTwoFactorAuthGracePeriodStart('2026-01-01');
    Assert::true($confirmationRequest->confirmProvisional2FACode($google2fa->getCurrentOtp($secret)));
    Assert::same([[$encrypted, null]], $confirmationRequest->savedEnrollments);
    Assert::same($secret, $confirmationRequest->getDecryptedTwoFactorAuthSecret());
    Assert::false(isset($_SESSION['provisional2FAKey']));
    Assert::false($confirmationRequest->confirmProvisional2FACode($google2fa->getCurrentOtp($secret)));
    Assert::false((new EnrollmentUser())->confirmProvisional2FACode($google2fa->getCurrentOtp($secret)));
} finally {
    session_destroy();
    $_SESSION = [];
}

$sameRequest = new EnrollmentUser();
$secret = $sameRequest->provisionNew2FAKey();
Assert::false(isset($_SESSION['provisional2FAKey']));
Assert::true($sameRequest->confirmProvisional2FACode($google2fa->getCurrentOtp($secret)));
