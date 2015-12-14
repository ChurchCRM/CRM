<?php
$tScanString = FilterInput($_POST["ScanInput"]);
$routeAndAccount = $micrObj->FindRouteAndAccount ($tScanString); // use routing and account number for matching
if ($routeAndAccount) {
	$sSQL = "SELECT fam_ID FROM family_fam WHERE fam_scanCheck=\"" . $routeAndAccount . "\"";
	$rsFam = RunQuery($sSQL);
	extract(mysql_fetch_array($rsFam));
	$iFamily = $fam_ID;

	$iCheckNo = $micrObj->FindCheckNo ($tScanString);


?>