<?php


//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;


// $sSQL = "
// ALTER TABLE `pledge_plg`
// DROP FOREIGN KEY typeofmbr,
// DROP FOREIGN KEY deposit,
// DROP COLUMN `plg_typeofmbr`;
// ";
// $bval = RunQuery($sSQL);

$sSQL = "
DROP table typeofmbr;
";
// $bval = RunQuery($sSQL);

// $sSQL = "
// ALTER TABLE `pledge_con`
// DROP FOREIGN KEY Family,
// DROP FOREIGN KEY Person
// ";
// $bval = RunQuery($sSQL);

$sSQL = "DROP TABLE contrib_con;";
$bval = RunQuery($sSQL);

$sSQL = "DROP TABLE contrib_split;";
$bval = RunQuery($sSQL);



//RedirectUtils::Redirect('index.php');
header("Refresh: 3 url=index.php");
echo 'Update completed! Redirecting after 3 seconds.';
?>