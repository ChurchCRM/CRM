<?php

declare(strict_types=1);

use ChurchCRM\Service\NotificationService;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\model\ChurchCRM\User;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::same('', NotificationService::dismissalSettingKey(''));
Assert::same(
    'notification.dismissed.system-update-available',
    NotificationService::dismissalSettingKey('system-update-available')
);

$boundaryId = str_repeat('a', 27);
Assert::same('notification.dismissed.' . $boundaryId, NotificationService::dismissalSettingKey($boundaryId));

$ballotId = 'community-feedback-ballot-2026-11';
$key = NotificationService::dismissalSettingKey($ballotId);
Assert::same(50, strlen($key));
Assert::same('notification.dismissed.d2fa1db07ef75272d4cdfba18d2', $key);
Assert::same(1, preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $key));
Assert::same($key, NotificationService::dismissalSettingKey($ballotId));
Assert::notSame($key, NotificationService::dismissalSettingKey('community-feedback-ballot-2026-12'));
Assert::same(50, strlen(NotificationService::dismissalSettingKey($boundaryId . 'a')));

$user = new class extends User {
    public array $settings = [];

    public function getSettingValue($name)
    {
        return $this->settings[$name] ?? '';
    }
};
$_SESSION = [
    'AuthenticationProvider' => new class($user) extends LocalAuthentication {
        public function __construct(private User $user) {}

        public function getCurrentUser(): ?User
        {
            return $this->user;
        }
    },
    'SystemNotifications' => (object) ['messages' => [(object) [
        'id' => $ballotId,
        'title' => 'Community feedback',
    ]]],
];
$load = new ReflectionMethod(NotificationService::class, 'loadRemoteNotifications');
$registry = new ReflectionProperty(NotificationService::class, 'notifications');
$load->invoke(null);
Assert::count(1, NotificationService::getNotifications());
$notice = NotificationService::getNotifications()[0];
Assert::same($ballotId, $notice->getId());
Assert::same($key, $notice->jsonSerialize()['dismissSettingKey']);

$user->settings[$key] = 'true';
$registry->setValue(null, []);
$load->invoke(null);
Assert::same([], NotificationService::getNotifications());
