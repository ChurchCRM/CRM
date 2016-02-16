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
$dDateStart = FilterInputArr($_GET,"DateStart");
$dDateEnd = FilterInputArr($_GET,"DateEnd");
$iID = FilterInputArr($_GET,"ID");
$sSort = FilterInputArr($_GET,"Sort");

// Build SQL Criteria
$sCriteria = "";
if (!$_SESSION['bFinance'])
	$sCriteria = "WHERE dep_EnteredBy=" . $_SESSION['iUserID'];

if ($dDateStart || $dDateEnd) {
	if (!$dDateStart && $dDateEnd)
		$dDateStart = $dDateEnd;
	if (!$dDateEnd && $dDateStart)
		$dDateEnd = $dDateStart;
	if ($sCriteria == "")
		$sCriteria .= " WHERE dep_Date BETWEEN '$dDateStart' AND '$dDateEnd' ";
	else
		$sCriteria .= " AND dep_Date BETWEEN '$dDateStart' AND '$dDateEnd' ";
}

if ($iID) {
	if ($sCriteria)
		$sCrieria .= "OR dep_ID = '$iID' ";
	else
		$sCriteria = " WHERE dep_ID = '$iID' ";
}

if (array_key_exists ("FilterClear", $_GET) && $_GET["FilterClear"]) {
	$sCriteria = "";
	$dDateStart = "";
	$dDateEnd = "";
	$iID = "";
}
require "Include/Header.php";

?>

<form method="get" action="FindDepositSlip.php" name="FindDepositSlip">
<input name="sort" type="hidden" value="<?= $sSort ?>"
<table cellpadding="3" align="center">

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?= gettext("Number:") ?></td>
				<td class="TextColumn"><input type="text" name="ID" id="ID" value="<?= $iID ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?= gettext("Date Start:") ?></td>
				<td class="TextColumn"><input type="text" name="DateStart" maxlength="10" id="DateStart" size="11" value="<?= $dDateStart ?>"></span></td>
				<td align="center">
					<input type="submit" class="btn" value="<?= gettext("Apply Filters") ?>" name="FindDepositSlipSubmit">
				</td>
			</tr>
			<tr>
				<td class="LabelColumn"><?= gettext("Date End:") ?></td>
				<td class="TextColumn"><input type="text" name="DateEnd" maxlength="10" id="DateEnd" size="11" value="<?= $dDateEnd ?>"></span></td>
				<td align="center">
					<input type="submit" class="btn" value="<?= gettext("Clear Filters") ?>" name="FilterClear">
				</td>
			</tr>
		</table>
		</td>
	</form>
</table>


<?php
// List Deposits
// Save record limit if changed
if (isset($_GET["Number"]))
{
	$_SESSION['SearchLimit'] = FilterInput($_GET["Number"],'int');
	$uSQL = "UPDATE user_usr SET usr_SearchLimit = " . $_SESSION['SearchLimit'] . " WHERE usr_per_ID = " . $_SESSION['iUserID'];
	$rsUser = RunQuery($uSQL);
}

// Select the proper sort SQL
switch($sSort)
{
	case "type":
		$sOrderSQL = "ORDER BY dep_Type, dep_Date DESC, dep_ID DESC";
		break;
	case "number":
		$sOrderSQL = "ORDER BY dep_ID DESC";
		break;
	case "closed":
		$sOrderSQL = "ORDER BY dep_closed, dep_Date DESC, dep_ID DESC";
		break;
	default:
		$sOrderSQL = " ORDER BY dep_Date DESC, dep_ID DESC";
		break;
}

// Append a LIMIT clause to the SQL statement
$iPerPage = $_SESSION['SearchLimit'];
if (empty($_GET['Result_Set']))
	$Result_Set = 0;
else
	$Result_Set = FilterInput($_GET['Result_Set'],'int');
$sLimitSQL = " LIMIT $Result_Set, $iPerPage";

// Build SQL query
$sSQL = "SELECT dep_ID, dep_Date, dep_Comment, dep_Closed, dep_Type FROM deposit_dep $sCriteria $sOrderSQL $sLimitSQL";
$sSQLTotal = "SELECT COUNT(dep_id) FROM deposit_dep $sCriteria";

// Execute SQL statement and get total result
$rsDep = RunQuery($sSQL);
$rsTotal = RunQuery($sSQLTotal);
list ($Total) = mysql_fetch_row($rsTotal);

echo '<div align="center">';
echo  '<form action="FindDepositSlip.php" method="get" name="ListNumber">';
// Show previous-page link unless we're at the first page
if ($Result_Set < $Total && $Result_Set > 0)
{
	$thisLinkResult = $Result_Set - $iPerPage;
	if ($thisLinkResult < 0)
		$thisLinkResult = 0;
	echo '<a href="FindDepositSlip.php?Result_Set='.$thisLinkResult.'&Sort='.$sSort.'">'. gettext("Previous Page") . '</a>&nbsp;&nbsp;';
}

// Calculate starting and ending Page-Number Links
$Pages = ceil($Total / $iPerPage);
$startpage =  (ceil($Result_Set / $iPerPage)) - 6;
if ($startpage <= 2)
	$startpage = 1;
$endpage = (ceil($Result_Set / $iPerPage)) + 9;
if ($endpage >= ($Pages - 1))
	$endpage = $Pages;

// Show Link "1 ..." if startpage does not start at 1
if ($startpage != 1)
	echo "<a href=\"FindDepositSlip.php?Result_Set=0&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">1</a> ... ";

	$dDateStart = FilterInputArr($_GET,"DateStart");
	$dDateEnd = FilterInputArr($_GET,"DateEnd");
	$iID = FilterInputArr($_GET,"ID");
	$sSort = FilterInputArr($_GET,"Sort");

// Display page links
if ($Pages > 1)
{
	for ($c = $startpage; $c <= $endpage; $c++)
	{
		$b = $c - 1;
		$thisLinkResult = $iPerPage * $b;
		if ($thisLinkResult != $Result_Set)
			echo "<a href=\"FindDepositSlip.php?Result_Set=$thisLinkResult&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">$c</a>&nbsp;";
		else
			echo "&nbsp;&nbsp;[ " . $c . " ]&nbsp;&nbsp;";
	}
}

// Show Link "... xx" if endpage is not the maximum number of pages
if ($endpage != $Pages)
{
	$thisLinkResult = ($Pages - 1) * $iPerPage;
		echo " <a href=\"FindDepositSlip.php?Result_Set=$thisLinkResult&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">$Pages</a>";
}

// Show next-page link unless we're at the last page
if ($Result_Set >= 0 && $Result_Set < $Total)
{
	$thisLinkResult=$Result_Set+$iPerPage;
	if ($thisLinkResult<$Total)
		echo "&nbsp;&nbsp;<a href='FindDepositSlip.php?Result_Set=$thisLinkResult&Sort=$sSort'>". gettext("Next Page") . "</a>&nbsp;&nbsp;";
}


$sLimit5 = "";
$sLimit10 = "";
$sLimit20 = "";
$sLimit25 = "";
$sLimit50 = "";
$sLimit100 = "";
$sLimit200 = "";
$sLimit500 = "";

// Display Record Limit
echo "<input type=\"hidden\" name=\"Result_Set\" value=\"" . $Result_Set . "\">";
if(isset($sSort))
	echo "<input type=\"hidden\" name=\"Sort\" value=\"" . $sSort . "\">";
if ($_SESSION['SearchLimit'] == "5")
	$sLimit5 = "selected";
if ($_SESSION['SearchLimit'] == "10")
	$sLimit10 = "selected";
if ($_SESSION['SearchLimit'] == "20")
	$sLimit20 = "selected";
if ($_SESSION['SearchLimit'] == "25")
	$sLimit25 = "selected";
if ($_SESSION['SearchLimit'] == "50")
	$sLimit50 = "selected";
if ($_SESSION['SearchLimit'] == "100")
	$sLimit100 = "selected";
if ($_SESSION['SearchLimit'] == "200")
	$sLimit200 = "selected";
if ($_SESSION['SearchLimit'] == "500")
	$sLimit500 = "selected";
	
echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". gettext("Display:") . "&nbsp;
	<select class=\"SmallText\" name=\"Number\">
		<option value=\"5\" $sLimit5>5</option>
		<option value=\"10\" $sLimit10>10</option>
		<option value=\"20\" $sLimit20>20</option>
		<option value=\"25\" $sLimit25>25</option>
		<option value=\"50\" $sLimit50>50</option>
		<option value=\"100\" $sLimit100>100</option>
		<option value=\"200\" $sLimit200>200</option>
		<option value=\"500\" $sLimit500>500</option>
	</select>&nbsp;
	<input type=\"submit\" class=\"icTinyButton\" value=\"". gettext("Go") ."\">
	</form></div><br>";

// Column Headings
echo "<table cellpadding='4' align='center' cellspacing='0' width='100%'>\n
	<tr class='TableHeader'>\n
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
<script>
$("#DateStart").datepicker({format:'yyyy-mm-dd'});
$("#DateEnd").datepicker({format:'yyyy-mm-dd'});
</script>

<?php
require "Include/Footer.php";
?>
