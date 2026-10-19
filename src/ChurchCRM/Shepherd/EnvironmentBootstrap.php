<?php

namespace ChurchCRM\Shepherd;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;

final class EnvironmentBootstrap
{
    public static function apply(): void
    {
        $mapping = [
            'SHEPHERD_CHURCH_NAME' => 'sChurchName',
            'SHEPHERD_CHURCH_EMAIL' => 'sChurchEmail',
            'SHEPHERD_CHURCH_WEBSITE' => 'sChurchWebSite',
            'SHEPHERD_SMTP_HOST' => 'sSMTPHost',
            'SHEPHERD_SMTP_USERNAME' => 'sSMTPUser',
            'SHEPHERD_SMTP_PASSWORD' => 'sSMTPPass',
            'SHEPHERD_SMTP_SECURITY' => 'sPHPMailerSMTPSecure',
        ];

        try {
            foreach ($mapping as $environmentName => $configName) {
                $value = getenv($environmentName);
                if ($value !== false && $value !== '' && SystemConfig::getValue($configName) !== $value) {
                    SystemConfig::setValue($configName, $value);
                }
            }

            if (getenv('SHEPHERD_SMTP_HOST')) {
                self::setIfChanged('bEnabledEmail', '1');
                self::setIfChanged('bSMTPAuth', getenv('SHEPHERD_SMTP_USERNAME') ? '1' : '0');
                self::setIfChanged('bPHPMailerAutoTLS', '1');
            }
            if (getenv('SHEPHERD_PREVIEW') === '1') {
                self::setIfChanged('sSMTPHost', 'mailpit:1025');
                foreach (['sSMTPUser', 'sSMTPPass', 'sPHPMailerSMTPSecure'] as $key) {
                    self::setIfChanged($key, '');
                }
                foreach (['bSMTPAuth', 'bPHPMailerAutoTLS', 'bEnableBirthdayEmails'] as $key) {
                    self::setIfChanged($key, '0');
                }
                self::setIfChanged('sTelemetryLevel', 'none');
            }
            self::setIfChanged('bEnableSelfRegistration', '0');
            self::setIfChanged('s2FAApplicationName', 'Shepherd');
        } catch (\Throwable $exception) {
            LoggerUtils::getAppLogger()->warning('Shepherd environment bootstrap deferred: ' . $exception->getMessage());
        }
    }

    private static function setIfChanged(string $name, string $value): void
    {
        if ((string) SystemConfig::getValue($name) !== $value) {
            SystemConfig::setValue($name, $value);
        }
    }
}
