<?php

include "src\ChurchCRM\SQLUtils.php";
Use ChurchCRM\SQLUtils;
$mysqli = new mysqli("127.0.0.1", "churchcrm", "churchcrm", "churchcrm");
echo "Connected to database\n";
echo "Deleting all tables\n";
if ($result = $mysqli->query("SHOW TABLES"))
{
    while($row = $result->fetch_array(MYSQLI_NUM))
    {
        $mysqli->query('DROP TABLE IF EXISTS '.$row[0]);
    }
}
$mysqli->query('SET foreign_key_checks = 1');
echo "Tables deleted, restoring demo db\n";
SQLUtils::sqlImport("demo/ChurchCRM-Database.sql", $mysqli);
echo "Demo db restored\n\n";