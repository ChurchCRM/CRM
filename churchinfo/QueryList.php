<?php
/*******************************************************************************
 *
 *  filename    : QueryList.php
 *  last change : 2003-01-07
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

//Set the page title
$sPageTitle = gettext("Query Listing");

$sSQL = "SELECT * FROM query_qry ORDER BY qry_Name";
$rsQueries = RunQuery($sSQL);

$aFinanceQueries = explode(',', $aFinanceQueries);

require "Include/Header.php";?>

<div class="box box-body">

<select id="querySelect">
<?php
while ($aRow = mysql_fetch_array($rsQueries))
{

	extract($aRow);

	// Filter out finance-related queries if the user doesn't have finance permissions
	if ($_SESSION['bFinance'] || !in_array($qry_ID,$aFinanceQueries))
	{
		// Display the query name and description
		echo "<option value=\"".$qry_ID . "\">" . $qry_Name . " - ". $qry_Description. "</option>";
	}
}
?>
</select>
<br><br>
Query Text:
<textarea id="queryText" class="form-control" name="queryText" <?php if (!$_SESSION['bAdmin']) { echo "disabled"; }?>></textarea>
<br>

<input type="button" class="btn btn-success" id="submitQuery" name="submitQuery" value="Submit Query"/>

</div>
<script>
$(document).ready(function() {
    $("#querySelect").select2();
});
$("#querySelect").on("select2:select", function (e) { 

});

</script>
<?php
require "Include/Footer.php";

?>
