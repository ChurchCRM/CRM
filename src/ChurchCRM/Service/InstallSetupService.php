<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\VersionUtils;

/**
 * The setup window of a new install, and the steps that get it ready.
 * The window is open for iSetupDashboardDays after the first install,
 * until an administrator finishes setup.
 */
class InstallSetupService
{
    public const DAYS_SETTING = 'iSetupDashboardDays';
    public const FINISHED_SETTING = 'bSetupFinished';

    public static function isWindowOpen(): bool
    {
        if (SystemConfig::getBooleanValue(self::FINISHED_SETTING)) {
            return false;
        }
        $installed = VersionUtils::getFirstInstallDate();
        $days = SystemConfig::getIntValue(self::DAYS_SETTING);

        return $installed !== null && $days > 0 && time() < $installed->getTimestamp() + $days * 86400;
    }

    public static function isDemoDataLoaded(): bool
    {
        return SystemConfig::getValue('sInstallDataProfile') === 'demo';
    }

    /** No church name yet: the install has neither demo data, a restored backup, nor its own church information. */
    public static function needsChurchSetup(): bool
    {
        return SystemConfig::getValue('sChurchName') === '';
    }

    /**
     * Member sign-in needs working email. A new install turns it on the first time email starts working.
     *
     * @return bool whether member sign-in was turned on
     */
    public static function turnOnMemberSignInWhenEmailStarts(bool $emailWorkedBefore): bool
    {
        if ($emailWorkedBefore || !SystemConfig::isEmailEnabled() || !self::isWindowOpen() || SystemConfig::getBooleanValue('bMemberSignIn')) {
            return false;
        }
        SystemConfig::setValue('bMemberSignIn', '1');

        return true;
    }

    /**
     * @return list<array{done: bool, label: string, desc: string, link: string, icon: string}>
     */
    public static function checklist(): array
    {
        $root = SystemURLs::getRootPath();

        $churchName = SystemConfig::getValue('sChurchName');
        $hasChurchInfo = $churchName !== 'Some Church' && $churchName !== ''
            && SystemConfig::getValue('sChurchAddress') !== '' && SystemConfig::getValue('sChurchEmail') !== '';
        $hasData = FamilyQuery::create()->select('Id')->findOne() !== null
            || PersonQuery::create()->select('Id')->findOne() !== null;
        $hasHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $adminUser = UserQuery::create()->filterByAdmin(true)->findOne();
        // init() is idempotent; Header.php normally calls it while the view renders.
        PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');

        return [
            [
                'done'  => $hasChurchInfo,
                'label' => gettext('Church Information'),
                'desc'  => gettext('Set your church name, address, and contact email'),
                'link'  => $root . '/admin/system/church-info',
                'icon'  => 'fa-church',
            ],
            [
                'done'  => $hasData,
                'label' => gettext('Add Your Data'),
                'desc'  => gettext('Import, restore, or manually enter your congregation'),
                'link'  => $root . '/admin/get-started',
                'icon'  => 'fa-users',
            ],
            [
                'done'  => SystemConfig::hasValidMailServerSettings(),
                'label' => gettext('Configure Email'),
                'desc'  => gettext('Connect an SMTP server so ChurchCRM can send emails'),
                'link'  => $root . '/admin/email',
                'icon'  => 'fa-envelope',
            ],
            [
                'done'  => $hasHttps,
                'label' => gettext('Enable HTTPS'),
                'desc'  => gettext('Install a TLS/SSL certificate for secure connections'),
                'link'  => SystemURLs::attributed('https://docs.churchcrm.io/installation/ssl-https', 'admin_checklist_https'),
                'icon'  => 'fa-lock',
            ],
            [
                'done'  => $adminUser !== null && !empty($adminUser->getEmail()),
                'label' => gettext('Update Admin Contact'),
                'desc'  => gettext('Set an admin user email so you can reset passwords and receive notifications'),
                'link'  => $root . '/admin/system/users',
                'icon'  => 'fa-user-shield',
            ],
            [
                'done'  => UserQuery::create()->count() > 1,
                'label' => gettext('Invite Your Team'),
                'desc'  => gettext('Add staff or volunteers as system users'),
                'link'  => $root . '/admin/system/users',
                'icon'  => 'fa-user-plus',
            ],
            [
                'done'  => PluginManager::hasAnyActivePlugin(),
                'label' => gettext('Enable Plugins'),
                'desc'  => gettext('Extend ChurchCRM with MailChimp, backups, and more'),
                'link'  => $root . '/plugins/management',
                'icon'  => 'fa-plug',
            ],
        ];
    }
}
