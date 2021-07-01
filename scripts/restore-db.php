<?php

include dirname(__FILE__)."/../src/ChurchCRM/SQLUtils.php";
Use ChurchCRM\SQLUtils;

$sSERVERNAME = "";
$sUSER = "";
$sPASSWORD = "";
$sDATABASE = "";
$dbPort = "";
$restoreDemoDb = $argv[1] == "demo";

function extract_config_values($value){

  global $sSERVERNAME,$sUSER,$sPASSWORD,$sDATABASE,$dbPort;

  if (preg_match('/\\$sSERVERNAME\\s+=\\s+[\'"](.*?)[\'"];/',$value,$matches)) {
    $sSERVERNAME = $matches[1];
  }

  if (preg_match('/\\$sUSER\\s+=\\s+[\'"](.*?)[\'"];/',$value,$matches)) {
    $sUSER = $matches[1];
  }

  if (preg_match('/\\$sPASSWORD\\s+=\\s+[\'"](.*?)[\'"];/',$value,$matches)) {
    $sPASSWORD = $matches[1];
  }

  if (preg_match('/\\$sDATABASE\\s+=\\s+[\'"](.*?)[\'"];/',$value,$matches)) {
    $sDATABASE = $matches[1];
  }

  if (preg_match('/\\$dbPort\\s+=\\s+[\'"](.*?)[\'"];/',$value,$matches)) {
    $dbPort = $matches[1];
  }
}

$config = explode("\n",file_get_contents (dirname(__FILE__)."/../src/Include/Config.php"));
array_map("extract_config_values",$config);

echo "Beginning to restore demo database\n";
echo "MySQL Server: $sSERVERNAME\n";
echo "Port: $dbPort\n";
echo "User: $sUSER\n";
echo "Password: $sPASSWORD\n";
echo "Database: $sDATABASE\n";

try {
  $mysqli = new mysqli($sSERVERNAME, $sUSER, $sPASSWORD, $sDATABASE,$dbPort);
  if (mysqli_connect_errno())
  {
    throw new \Exception("Failed to connect to MySQL: " . mysqli_connect_error());
  }
  $mysqli->select_db($sDATABASE);
  echo "Connected to database\n";
  echo "Deleting all tables\n";

  if ($result = $mysqli->query("SHOW TABLES"))
  {
      while($row = $result->fetch_array(MYSQLI_NUM))
      {
          $mysqli->query('DROP TABLE IF EXISTS '.$row[0]);
      }
  }
  //  this is a bit hacky, but anything left in TABLES here must be a view
  if ($result = $mysqli->query("SHOW TABLES"))
  {
      while($row = $result->fetch_array(MYSQLI_NUM))
      {
          $mysqli->query('DROP VIEW IF EXISTS '.$row[0]);
      }
  }
  $mysqli->query('SET foreign_key_checks = 1');
  echo "Tables deleted\n";
  if ($restoreDemoDb) {
    echo "restoring demo db\n";
    SQLUtils::sqlImport(dirname(__FILE__)."/../demo/ChurchCRM-Database.sql", $mysqli);
    echo "Demo db restored\n\n";
  }
}
catch (\Exception $e) {
  echo "Error restoring database: $e";
}