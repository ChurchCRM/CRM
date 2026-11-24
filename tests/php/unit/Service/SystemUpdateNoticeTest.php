<?php

declare(strict_types=1);

use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\NotificationService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('/crm', [], APP_ROOT);

$user = new class extends User {
    public bool $admin = false;
    public array $settings = [];

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function getSettingValue($name)
    {
        return $this->settings[$name] ?? '';
    }
};

$load = new ReflectionMethod(NotificationService::class, 'loadUpgradeNotification');
$registry = new ReflectionProperty(NotificationService::class, 'notifications');

$noticeFor = function (array $session) use ($user, $load, $registry): ?array {
    $_SESSION = $session + [
        'AuthenticationProvider' => new class($user) extends LocalAuthentication {
            public function __construct(private User $user) {}

            public function getCurrentUser(): ?User
            {
                return $this->user;
            }
        },
    ];
    $registry->setValue(null, []);
    $load->invoke(null);
    $notices = NotificationService::getNotifications();

    return $notices === [] ? null : $notices[0]->jsonSerialize();
};

$available = ['systemUpdateAvailable' => true, 'systemUpdateVersion' => '7.9.0'];
$support = 'Security updates and support are provided for the latest version only.';

$user->admin = true;
$admin = $noticeFor($available);
Assert::same('system-update-available', $admin['id']);
Assert::same('/crm/admin/system/upgrade', $admin['url']);
Assert::contains('Version 7.9.0 is available. Please upgrade your installation.', $admin['message']);
Assert::contains($support, $admin['message']);

$user->admin = false;
$staff = $noticeFor($available);
Assert::same('/crm/v2/releases', $staff['url']);
Assert::contains('Please ask your administrator to upgrade your installation.', $staff['message']);
Assert::contains($support, $staff['message']);

Assert::contains('A system update is available.', $noticeFor(['systemUpdateAvailable' => true])['message']);
Assert::null($noticeFor(['systemUpdateAvailable' => false]));

Assert::null($noticeFor($available + ['systemUpdateNoticeDismissed' => true]));

$user->settings[NotificationService::dismissalSettingKey('system-update-available')] = 'true';
Assert::notSame(null, $noticeFor($available));
