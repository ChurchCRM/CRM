<?php

use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

$logger->info('Dropping columns for 5.8.0 upgrade');

try {
    $q1 = 'ALTER TABLE events_event DROP COLUMN `event_typename`;';
    $connection->exec($q1);
} catch (\Exception $e) {
    $logger->warning('Could not remove `events_event.event_typename`, but this is probably ok');
}

$logger->info('Finished dropping columns for 5.8.0 upgrade');
