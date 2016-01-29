<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
 *  last change : 2005-02-06
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001-2005 Deane Barker, Chris Gebhardt, Michael Wilt, Tim Dearborn
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

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext("Deposit Listing");

// Security: User must have finance permission to use this form
//if (!$_SESSION['bFinance'])
//{
//	Redirect("Menu.php");
//	exit;
//}

//Filter Values

// Build SQL Criteria
$sCriteria = "";
if (!$_SESSION['bFinance'])
	$sCriteria = "WHERE dep_EnteredBy=" . $_SESSION['iUserID'];

require "Include/Header.php";

// Build SQL query
$sSQL = "SELECT dep_ID, dep_Date, dep_Comment, dep_Closed, dep_Type FROM deposit_dep $sCriteria";
$sSQLTotal = "SELECT COUNT(dep_id) FROM deposit_dep $sCriteria";

// Execute SQL statement and get total result
$rsDep = RunQuery($sSQL);
$rsTotal = RunQuery($sSQLTotal);
list ($Total) = mysql_fetch_row($rsTotal);
?>

<div class="box">
<div class="box-body">
<table class="table" id="DepositsTable">

<?php

// Column Headings
echo "<tr class='TableHeader'>\n
	<td width='25'>".gettext("Edit") . "</td>\n
	<td><a href='FindDepositSlip.php?Sort=number&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd'>".gettext("Number")."</a></td>\n
	<td><a href='FindDepositSlip.php?Sort=date'&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd>".gettext("Date")."</a></td>\n
	<td>".gettext("Total Payments")."</td>\n
	<td>".gettext("Comment")."</td>\n
	<td><a href='FindDepositSlip.php?Sort=closed'&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd>".gettext("Closed")."</a></td>\n
	<td><a href='FindDepositSlip.php?Sort=type'&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd>".gettext("Deposit Type")."</a></td>\n
	<td>Download OFX</td>\n
	</tr>";

// Display Deposits
while (list ($dep_ID, $dep_Date, $dep_Comment, $dep_Closed, $dep_Type) = mysql_fetch_row($rsDep))
{
	echo "<tr><td><a href='DepositSlipEditor.php?DepositSlipID=$dep_ID'>" . gettext("Edit") . "</td>";
	echo "<td>$dep_ID</td>";
	echo "<td>$dep_Date</td>";
	// Get deposit total
	$sSQL = "SELECT SUM(plg_amount) AS deposit_total FROM pledge_plg WHERE plg_depID = '$dep_ID' AND plg_PledgeOrPayment = 'Payment'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($deposit_total) = mysql_fetch_row($rsDepositTotal);
	echo "<td>$deposit_total</td>";
	echo "<td>$dep_Comment</td>";
	if ($dep_Closed == 1)
		$dep_Closed_text = "Yes";
	else
		$dep_Closed_text = "No";
	echo "<td>$dep_Closed_text</td>";	
	echo "<td>$dep_Type</td>";
	echo "<td><a href='Reports/ExportOFX.php?deposit=$dep_ID'>Download</td>";
}
echo "</table>";
?>
</div>
</div>
<script>
$("#DateStart").datepicker({format:'yyyy-mm-dd'});
$("#DateEnd").datepicker({format:'yyyy-mm-dd'});
$('#DepositsTable').DataTable();
</script>

<?php
require "Include/Footer.php";
?>
