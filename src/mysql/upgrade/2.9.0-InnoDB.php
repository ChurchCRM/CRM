<?php

use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

$logger->info('Upgrade to InnoDB started ');

$sqlEvents = 'ALTER TABLE events_event DROP INDEX event_txt;';
$connection->exec($sqlEvents);

$logger->info('Dropped events_event INDEX');

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
