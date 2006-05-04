<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
 *  last change : 2005-02-06
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2001-2005 Deane Barker, Chris Gebhardt, Michael Wilt, Tim Dearborn
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
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
if (!$_SESSION['bFinance'])
{
	Redirect("Menu.php");
	exit;
}

//Filter Values
$dDateStart = FilterInput($_GET["DateStart"]);
$dDateEnd = FilterInput($_GET["DateEnd"]);
$iID = FilterInput($_GET["ID"]);
$sSort = FilterInput($_GET["Sort"]);

// Build SQL Criteria
if ($dDateStart || $dDateEnd) {
	if (!$dDateStart && $dDateEnd)
		$dDateStart = $dDateEnd;
	if (!$dDateEnd && $dDateStart)
		$dDateEnd = $dDateStart;
	$sCriteria = " WHERE dep_Date BETWEEN '$dDateStart' AND '$dDateEnd' ";
}
if ($iID) {
	if ($sCriteria)
		$sCrieria .= "OR dep_ID = '$iID' ";
	else
		$sCriteria = " WHERE dep_ID = '$iID' ";
}
if ($_GET["FilterClear"]) {
	$sCriteria = "";
	$dDateStart = "";
	$dDateEnd = "";
	$iID = "";
}
require "Include/Header.php";

?>

<form method="get" action="FindDepositSlip.php" name="FindDepositSlip">
<input name="sort" type="hidden" value="<?php echo $sSort; ?>"
<table cellpadding="3" align="center">

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Number:"); ?></td>
				<td class="TextColumn"><input type="text" name="ID" id="ID" value="<?php echo $iID; ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Date Start:"); ?></td>
				<td class="TextColumn"><input type="text" name="DateStart" maxlength="10" id="sel1" size="11" value="<?php echo $dDateStart; ?>">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[YYYY-MM-DD]"); ?></span></td>
				<td align="center">
					<input type="submit" class="icButton" value="<?php echo gettext("Apply Filters"); ?>" name="FindDepositSlipSubmit">
				</td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?><?php echo gettext("Date End:"); ?></td>
				<td class="TextColumn"><input type="text" name="DateEnd" maxlength="10" id="sel2" size="11" value="<?php echo $dDateEnd; ?>">&nbsp;<input type="image" onclick="return showCalendar('sel2', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[YYYY-MM-DD]"); ?></span></td>
				<td align="center">
					<input type="submit" class="icButton" value="<?php echo gettext("Clear Filters"); ?>" name="FilterClear">
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
$sLimitSQL .= " LIMIT $Result_Set, $iPerPage";

// Build SQL query
$sSQL = "SELECT dep_ID, dep_Date, dep_Comment, dep_Closed, dep_Type FROM deposit_dep $sCriteria $sOrderSQL $sLimitSQL";
$sSQLTotal = "SELECT COUNT(dep_id) FROM deposit_dep $sCriteria";

// Execute SQL statement and get total result
$rsDep = RunQuery($sSQL);
$rsTotal = RunQuery($sSQLTotal);
list ($Total) = mysql_fetch_row($rsTotal);

echo "<div align=\"center\">\n";
echo  "<form action='FindDepositSlip.php' method='get' name='ListNumber'>\n";
// Show previous-page link unless we're at the first page
if ($Result_Set < $Total && $Result_Set > 0)
{
	$thisLinkResult = $Result_Set - $iPerPage;
	if ($thisLinkResult < 0)
		$thisLinkResult = 0;
	echo "<a href='FindDepositSlip.php?Result_Set=$thisLinkResult&Sort=$sSort'>". gettext("Previous Page") . "</a>&nbsp;&nbsp;\n";
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
	echo "<a href=\"FindDepositSlip.php?Result_Set=0&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">1</a> ... \n";

	$dDateStart = FilterInput($_GET["DateStart"]);
	$dDateEnd = FilterInput($_GET["DateEnd"]);
	$iID = FilterInput($_GET["ID"]);
	$sSort = FilterInput($_GET["Sort"]);

// Display page links
if ($Pages > 1)
{
	for ($c = $startpage; $c <= $endpage; $c++)
	{
		$b = $c - 1;
		$thisLinkResult = $iPerPage * $b;
		if ($thisLinkResult != $Result_Set)
			echo "<a href=\"FindDepositSlip.php?Result_Set=$thisLinkResult&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">$c</a>&nbsp;\n";
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
		echo "&nbsp;&nbsp;<a href='FindDepositSlip.php?Result_Set=$thisLinkResult&Sort=$sSort'>". gettext("Next Page") . "</a>&nbsp;&nbsp;\n";
}


// Display Record Limit
echo "<input type=\"hidden\" name=\"Result_Set\" value=\"" . $Result_Set . "\">\n";
if(isset($sSort))
	echo "<input type=\"hidden\" name=\"Sort\" value=\"" . $sSort . "\">\n";
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
			
echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". gettext("Display:") . "&nbsp;
	<select class=\"SmallText\" name=\"Number\">\n
		<option value=\"5\" $sLimit5>5</option>\n
		<option value=\"10\" $sLimit10>10</option>\n
		<option value=\"20\" $sLimit20>20</option>\n
		<option value=\"25\" $sLimit25>25</option>\n
		<option value=\"50\" $sLimit50>50</option>\n
	</select>&nbsp;\n
	<input type=\"submit\" class=\"icTinyButton\" value=\"". gettext("Go") ."\">
	</form></div><br>\n";

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
	</tr>\n";

// Display Deposits
while (list ($dep_ID, $dep_Date, $dep_Comment, $dep_Closed, $dep_Type) = mysql_fetch_row($rsDep))
{
	echo "<tr><td><a href='DepositSlipEditor.php?DepositSlipID=$dep_ID'>" . gettext("Edit") . "</td>\n";
	echo "<td>$dep_ID</td>\n";
	echo "<td>$dep_Date</td>\n";
	// Get deposit total
	$sSQL = "SELECT SUM(plg_amount) AS deposit_total FROM pledge_plg WHERE plg_depID = '$dep_ID' AND plg_PledgeOrPayment = 'Payment'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($deposit_total) = mysql_fetch_row($rsDepositTotal);
	echo "<td>$deposit_total</td>\n";
	echo "<td>$dep_Comment</td>\n";
	if ($dep_Closed == 1)
		$dep_Closed_text = "Yes";
	else
		$dep_Closed_text = "No";
	echo "<td>$dep_Closed_text</td>\n";	
	echo "<td>$dep_Type</td>\n";
}
echo "</table>\n";

require "Include/Footer.php";
?>
