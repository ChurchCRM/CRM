use Propel\Runtime\Propel;
use ChurchCRM\Utils\LoggerUtils;

$connection = Propel::getConnection();
$logger = LoggerUtils::getAppLogger();

global $sDATABASE;

$logger->info("Upgrade " . $sDATABASE. " to InnoDB started ");

global $sDATABASE;
    
$sql = "ALTER TABLE events_event DROP INDEX event_txt;";
$rs = RunQuery($sql);

$logger->info("Upgrade: " . $rs); 

$sql = "SHOW TABLES FROM `".$sDATABASE."`";
$rs = RunQuery($sql);
$logger->info("Upgrade: " . $sql);    
    
while ($row = mysqli_fetch_row($rs)) {
  $sql = "ALTER TABLE ".$row[0]." ENGINE = InnoDB;";
  $rs2 = RunQuery($sql);
    
  $logger->info("Upgrade: " . $sql); 
}
    
$sql = "ALTER TABLE events_event ADD FULLTEXT KEY `event_txt` (`event_text`);";
$rs = RunQuery($sql);

$logger->info("Upgrade: " . $sql); 

$logger->info("Upgrade " . $sDATABASE. " to InnoDB finished ");
