<?php

use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

$logger->info('Upgrade to InnoDB started ');

// Guard: the index may already be gone (e.g. when this script re-runs as part of
// the consolidated pre-6.0.0 entry starting from a 2.9.0+ schema state).
try {
    $sqlEvents = 'ALTER TABLE events_event DROP INDEX event_txt;';
    $connection->exec($sqlEvents);
    $logger->info('Dropped events_event INDEX');
} catch (\Exception $e) {
    $logger->warning('Could not drop events_event.event_txt index (may already be absent): ' . $e->getMessage());
}

$statement = $connection->prepare("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
$statement->execute();
$dbTablesSQLs = $statement->fetchAll();

foreach ($dbTablesSQLs as $dbTable) {
    $alterSQL = 'ALTER TABLE ' . $dbTable[0] . ' ENGINE=InnoDB;';
    $logger->info('Upgrade: ' . $alterSQL);
    $dbAlterStatement = $connection->exec($alterSQL);
    $logger->info('Upgrade: ' . $alterSQL . ' done.');
}

$logger->info('Upgrade to InnoDB finished ');
