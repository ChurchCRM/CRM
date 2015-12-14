<?php
/*******************************************************************************
 *
 *  filename    : AjaxFunctions.php
 *  last change : 2015-12-12
 *  description : AJAX helper file to return autocomplete names for various site searches and resource requests
 *
 ******************************************************************************/

// Include the function library
require "../Include/Config.php";
require "../Include/Functions.php";

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

	$iEnvelope = FilterInput($_GET["envelopeID"], 'int');
	$return[] = getFamilyStringByEnvelope($iEnvelope);

echo json_encode($return);

?>
