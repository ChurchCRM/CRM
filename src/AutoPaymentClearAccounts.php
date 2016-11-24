<?php
require "Include/Config.php";
require "Include/Functions.php";
require "Include/VancoConfig.php";

$iVancoAutID = FilterInput($_GET['customerid'],'int');

$sSQL = "UPDATE autopayment_aut SET ";
$sSQL .= "aut_CreditCard=CONCAT(\"************\",SUBSTR(aut_CreditCard,LENGTH(aut_CreditCard)-3,4))";
$sSQL .= ", aut_Account=CONCAT(\"*****\",SUBSTR(aut_Account,LENGTH(aut_Account)-3, 4))";
$sSQL .= " WHERE aut_ID=$iVancoAutID";

$bSuccess = false;
if ($result = mysqli_query($cnInfoCentral, $sSQL))
    $bSuccess = true;

$errStr = "";

if (! $bSuccess)
	$errStr = gettext("Cannot execute query.") . "<p>$sSQL<p>" . mysqli_error($cnInfoCentral);

header('Content-type: application/json');
echo json_encode(array('Success'=>$bSuccess, 'ErrStr'=>$errStr));
?>
