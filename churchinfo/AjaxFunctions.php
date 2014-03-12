<?php
/*******************************************************************************
 *
 *  filename    : AjaxFunctions.php
 *  last change : 2014-03-10
 *  description : AJAX helper file to return autocomplete names for various site searches
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Security
if (!isset($_SESSION['iUserID']))
{
	Redirect("Default.php");
	exit;
}

// Handle URL via _GET first
$sSearchTerm = FilterInput($_GET["term"],'string');
$sSearchType = FilterInput($_GET["searchtype"],'string');

//Are we looking for an individual? Most commonly from main search.
if ($sSearchType=="person") {
	$fetch = 'SELECT per_ID, per_FirstName, per_LastName, CONCAT_WS(" ",per_FirstName,per_LastName) AS fullname, per_fam_ID  FROM `person_per` WHERE per_FirstName LIKE \'%'.$sSearchTerm.'%\' OR per_LastName LIKE \'%'.$sSearchTerm.'%\'  OR CONCAT_WS(" ",per_FirstName,per_LastName) LIKE \'%'.$sSearchTerm.'%\' LIMIT 15';
	$result=mysql_query($fetch);

	$return = array();
	while($row=mysql_fetch_array($result)) {
	if($sSearchType=="person") {
		$values['id']=$row['per_ID'];
		$values['famID']=$row['per_fam_ID'];
		$values['per_FirstName']=$row['per_FirstName'];
		$values['per_LastName']=$row['per_LastName'];
		$values['value']=$row['per_FirstName']." ".$row['per_LastName'];
	}

	array_push($return,$values);	
}
	
} else { // the original code - for searching for a family (commonly from adding deposits)
$familyArray = getFamilyList($sDirRoleHead, $sDirRoleSpouse, null, $sSearchTerm);
	foreach ($familyArray as $fam_ID => $fam_Data) {
		$return[] = array("value"=> $fam_Data, "id" => $fam_ID);
	}
}

echo json_encode($return);

?>
