<?php
	// Handle special buttons at the bottom of the form.
	if (isset($_POST["SetDefaultCheck"])) {
		$tScanString = FilterInput($_POST["ScanInput"]);
		$routeAndAccount = $micrObj->FindRouteAndAccount ($tScanString); // use routing and account number for matching
		$iFamily = FilterInput($_POST["FamilyID"],'int');
		$sSQL = "UPDATE family_fam SET fam_scanCheck=\"" . $routeAndAccount . "\" WHERE fam_ID = " . $iFamily;
		RunQuery($sSQL);
	}
	
?>