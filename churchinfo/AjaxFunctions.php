<?php
/*******************************************************************************
 *
 *  filename    : eGive.php
 *  last change : 2009-08-27
 *  description : Tool for importing eGive data
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";


// Handle URL via _GET first
$sSearchTerm = FilterInput($_GET["term"],'string');

$familyArray = getFamilyList($sDirRoleHead, $sDirRoleSpouse, null, $sSearchTerm);
	foreach ($familyArray as $fam_ID => $fam_Data) {
		$return[] = array("value"=> $fam_Data, "id" => $fam_ID);
	}

echo json_encode($return);

?>
