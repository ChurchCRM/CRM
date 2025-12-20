<?php

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ConfigQuery;

$logger = LoggerUtils::getAppLogger();

$logger->info('Migrating TwoFASecretKey to SystemConfig for 6.6.0 upgrade');

try {
    // Load existing configs from database and initialize SystemConfig
    $configs = ConfigQuery::create()->find();
    SystemConfig::init($configs);

    // Check if sTwoFASecretKey already exists
    $existingConfig = ConfigQuery::create()
        ->filterByName('sTwoFASecretKey')
        ->findOne();

    // If it already exists, migration is complete
    if ($existingConfig !== null) {
        $logger->info('sTwoFASecretKey already exists in SystemConfig, no migration needed');
        return;
    }

    // Try to migrate from Config.php if value exists
    // Note: Most installations won't have this set initially, which is expected
    if (isset($GLOBALS['TwoFASecretKey']) && !empty($GLOBALS['TwoFASecretKey'])) {
        SystemConfig::setValue('sTwoFASecretKey', $GLOBALS['TwoFASecretKey']);
        $logger->info('Successfully migrated TwoFASecretKey to SystemConfig');
    } else {
        // No existing key to migrate - users will configure it in System Settings if needed
        $logger->info('No TwoFASecretKey found in Config.php - 2FA can be configured later in System Settings');
    }
} catch (\Exception $e) {
    $logger->error('Failed to migrate TwoFASecretKey: ' . $e->getMessage(), ['exception' => $e]);
    throw $e;
}

$logger->info('Finished TwoFASecretKey migration for 6.6.0 upgrade');
