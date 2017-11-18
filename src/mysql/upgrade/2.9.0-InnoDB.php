<?php
require '../../Include/Config.php';

use Propel\Runtime\Propel;

$connection = Propel::getConnection();

//this is needed as we create DB tables on the fly and we don't know what they are at upgrade time.
$sqlUpgrade = "SELECT CONCAT('ALTER TABLE ',TABLE_NAME,' ENGINE=InnoDB;')  
    FROM INFORMATION_SCHEMA.TABLES
    WHERE ENGINE='MyISAM'
    AND table_schema = '". $sDATABASE ."';";

$statement = $connection->prepare($query);
$statement->execute();