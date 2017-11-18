<?php
require '../../Include/Config.php';

$sqlUpgrade = "SELECT CONCAT('ALTER TABLE ',TABLE_NAME,' ENGINE=InnoDB;')  
    FROM INFORMATION_SCHEMA.TABLES
    WHERE ENGINE='MyISAM'
    AND table_schema = '". $sDATABASE ."';";

