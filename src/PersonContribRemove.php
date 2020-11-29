<?php


//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;


$sSQL = "ALTER TABLE person_per
  DROP per_inactive";

$bval = RunQuery($sSQL);
echo "per_inactive added<br>";

$sSQL = "DROP table typeofmbr;";

$bval = RunQuery($sSQL);
echo "DROP table typeofmbr<br>";

$sSQL = "DROP TABLE contrib_con;";
$bval = RunQuery($sSQL);
echo "DROP table contrib_con<br>";

$sSQL = "DROP TABLE contrib_split;";
$bval = RunQuery($sSQL);
echo "DROP table contrib_split<br>";


//RedirectUtils::Redirect('index.php');
header("Refresh: 3 url=index.php");
echo 'Update completed! Redirecting after 3 seconds.';
?>