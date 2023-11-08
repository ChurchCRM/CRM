<?php

use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

$logger->info('Dropping columns for 3.5.0 upgrade');

try {
    $q1 = 'alter table family_custom_master drop column `fam_custom_Side`;';
    $connection->exec($q1);
} catch (\Exception $e) {
    $logger->warning('Could not remove `family_custom_master.fam_custom_Side`, but this is probably ok');
}

try {
    $q2 = 'alter table custom_master drop column `custom_Side`;';
    $connection->exec($q2);
} catch (\Exception $e) {
    $logger->warning('Could not remove `custom_master.custom_Side`, but this is probably ok');
}

try {
    $q3 = 'alter table person_custom_master drop column `custom_Side`;';
    $connection->exec($q3);
} catch (\Exception $e) {
    $logger->warning('Could not remove `person_custom_master.custom_Side`, but this is probably ok');
}

$logger->info('Finished dropping columns for 3.5.0 upgrade');
