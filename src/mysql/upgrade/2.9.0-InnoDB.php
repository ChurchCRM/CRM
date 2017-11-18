<?php

use Propel\Runtime\Propel;
use ChurchCRM\Utils\LoggerUtils;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

global $sDATABASE;

$logger->info("Upgrade " . $sDATABASE. " to InnoDB started ");

//this is needed as we create DB tables on the fly and we don't know what they are at upgrade time.
$sqlUpgrade = "SELECT CONCAT('ALTER TABLE ',TABLE_NAME,' ENGINE=InnoDB;') as alterSQL
    FROM INFORMATION_SCHEMA.TABLES
    WHERE ENGINE='MyISAM'
    AND table_schema = '". $sDATABASE ."';";

$statement = $connection->prepare($sqlUpgrade);
$statement->execute();
$dbTablesSQLs = $statement->fetchAll();



foreach ($dbTablesSQLs as $dbAlter) {
    $alterSQL = $dbAlter ['alterSQL'];
    if (! strpos("$alterSQL", "events_event")) {
        $logger->info("Upgrade: " . $alterSQL);
        $dbAlterStatement = $connection->prepare($alterSQL);
        $dbAlterStatement->execute();
        $logger->info("Upgrade: " . $alterSQL . " done.");
    } else {
        $logger->info("Upgrade: " . $alterSQL . " SKIPPED.");
    }
}

$logger->info("Upgrade " . $sDATABASE. " to InnoDB finished ");