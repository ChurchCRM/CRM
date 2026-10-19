<?php

// Run only against the disposable compose.test.yml database.
if (getenv('SHEPHERD_DB_PASSWORD') !== 'disposable-test-password') {
    throw new RuntimeException('Refusing to seed a non-test database.');
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/shepherd/livez';
require '/var/www/html/shepherd/Include/LoadConfigs.php';

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\KeyManagerUtils;
use Defuse\Crypto\Crypto;

$accounts = [];
\ChurchCRM\dto\SystemConfig::setValue('bEnableLostPassword', '1');
\ChurchCRM\dto\SystemConfig::setValue('bRequire2FA', '0');
\ChurchCRM\dto\SystemConfig::setValue('sChurchName', 'Disposable Upgrade Tests');
\ChurchCRM\dto\SystemConfig::setValue('sChurchEmail', 'upgrade@example.invalid');
$provider = new \ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication();
$actor = UserQuery::create()->findOne();
if ($actor === null) {
    $actor = new User();
    $actor->setPersonId(0);
}
(new ReflectionProperty($provider, 'currentUser'))->setValue($provider, $actor);
$_SESSION['AuthenticationProvider'] = $provider;
foreach (['admin' => true, 'member' => false, 'enroll' => true] as $name => $admin) {
    $username = 'upgrade.' . $name;
    $user = UserQuery::create()->findOneByUserName($username);
    if ($user === null) {
        $person = new Person();
        $person->setFirstName('Upgrade');
        $person->setLastName(ucfirst($name));
        $person->setEmail($username . '@example.invalid');
        $person->setDateEntered(date('Y-m-d H:i:s'));
        $person->setEnteredBy(0);
        $person->save();
        $user = new User();
        $user->setPersonId($person->getId());
        $user->setUserName($username);
    }
    $user->setAdmin($admin);
    $user->setNeedPasswordChange(false);
    $user->setFailedLogins(0);
    $user->updatePassword('Upgrade-test-password-2026!');
    $user->setTwoFactorAuthSecret($name === 'admin'
        ? Crypto::encryptWithPassword('JBSWY3DPEHPK3PXP', KeyManagerUtils::getTwoFASecretKey()) : null);
    $user->setTwoFactorAuthLastKeyTimestamp(0);
    $user->save();
    $codes = $name === 'admin' ? $user->getNewTwoFARecoveryCodes($user->getPassword(), $user->getTwoFactorAuthSecret()) : [];
    $accounts[$name] = ['id' => $user->getId(), 'username' => $username, 'apiKey' => $user->getApiKey(), 'recoveryCodes' => $codes];
}
echo json_encode($accounts, JSON_THROW_ON_ERROR);
