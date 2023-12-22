<?php

/*******************************************************************************
 *
 *  filename    : FindFundRaiser.php
 *  last change : 2009-04-16
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2009 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;

//Set the page title
$sPageTitle = gettext('Fundraiser Listing');

//Filter Values
$dDateStart = '';
$dDateEnd = '';
$iID = '';
$sSort = '';

if (array_key_exists('DateStart', $_GET)) {
    $dDateStart = InputUtils::legacyFilterInput($_GET['DateStart']);
}
if (array_key_exists('DateEnd', $_GET)) {
    $dDateEnd = InputUtils::legacyFilterInput($_GET['DateEnd']);
}
if (array_key_exists('ID', $_GET)) {
    $iID = InputUtils::legacyFilterInput($_GET['ID']);
}
if (array_key_exists('Sort', $_GET)) {
    $sSort = InputUtils::legacyFilterInput($_GET['Sort']);
}

// Build SQL Criteria
$sCriteria = '';
if ($dDateStart || $dDateEnd) {
    if (!$dDateStart && $dDateEnd) {
        $dDateStart = $dDateEnd;
    }
    if (!$dDateEnd && $dDateStart) {
        $dDateEnd = $dDateStart;
    }
    $sCriteria .= " WHERE fr_Date BETWEEN '$dDateStart' AND '$dDateEnd' ";
}
if ($iID) {
    if ($sCriteria) {
        $sCrieria .= "OR fr_ID = '$iID' ";
    } else {
        $sCriteria = " WHERE fr_ID = '$iID' ";
    }
}
if (array_key_exists('FilterClear', $_GET) && $_GET['FilterClear']) {
    $sCriteria = '';
    $dDateStart = '';
    $dDateEnd = '';
    $iID = '';
}
require 'Include/Header.php';

?>
<div class="card card-body">
<form method="get" action="FindFundRaiser.php" name="FindFundRaiser">
<input name="sort" type="hidden" value="<?= $sSort ?>"
<table cellpadding="3" align="center">

    <tr>
        <td>
        <table cellpadding="3">
            <tr>
                <td class="LabelColumn"><?= gettext('Number') ?>:</td>
                <td class="TextColumn"><input type="text" name="ID" id="ID" value="<?= $iID ?>"></td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Date Start') ?>:</td>
                <td class="TextColumn"><input type="text" name="DateStart" maxlength="10" id="DateStart" size="11" value="<?= $dDateStart ?>" class="date-picker"></td>
                <td align="center">
                    <input type="submit" class="btn btn-primary" value="<?= gettext('Apply Filters') ?>" name="FindFundRaiserSubmit">
                </td>
            </tr>
            <tr>
                <td class="LabelColumn"><?= gettext('Date End') ?>:</td>
                <td class="TextColumn"><input type="text" name="DateEnd" maxlength="10" id="DateEnd" size="11" value="<?= $dDateEnd ?>" class="date-picker"></td>
                <td align="center">
                    <input type="submit" class="btn btn-danger" value="<?= gettext('Clear Filters') ?>" name="FilterClear">
                </td>
            </tr>
        </table>
        </td>
    </form>
</table>
</div>
<div class="card card-body">
<?php
// List Fundraisers
// Save record limit if changed
if (isset($_GET['Number'])) {
    $currentUser = AuthenticationManager::getCurrentUser();
    $currentUser->setSearchLimit(InputUtils::legacyFilterInput($_GET['Number'], 'int'));
    $currentUser->save();
}

// Select the proper sort SQL
switch ($sSort) {
    case 'number':
        $sOrderSQL = 'ORDER BY fr_ID DESC';
        break;
    default:
        $sOrderSQL = ' ORDER BY fr_Date DESC, fr_ID DESC';
        break;
}

// Append a LIMIT clause to the SQL statement
$tableSizeSetting = AuthenticationManager::getCurrentUser()
    ->getSetting("ui.table.size");
if (empty($tableSizeSetting)) {
    $iPerPage = 10;
} else {
    $iPerPage = $tableSizeSetting->getValue();
}
if (empty($_GET['Result_Set'])) {
    $Result_Set = 0;
} else {
    $Result_Set = InputUtils::legacyFilterInput($_GET['Result_Set'], 'int');
}
$sLimitSQL = " LIMIT $Result_Set, $iPerPage";

// Build SQL query
$sSQL = "SELECT fr_ID, fr_Date, fr_Title FROM fundraiser_fr $sCriteria $sOrderSQL $sLimitSQL";
$sSQLTotal = "SELECT COUNT(fr_ID) FROM fundraiser_fr $sCriteria";

// Execute SQL statement and get total result
$rsDep = RunQuery($sSQL);
$rsTotal = RunQuery($sSQLTotal);
list($Total) = mysqli_fetch_row($rsTotal);

echo '<div align="center">';
echo  '<form action="FindFundRaiser.php" method="get" name="ListNumber">';
// Show previous-page link unless we're at the first page
if ($Result_Set < $Total && $Result_Set > 0) {
    $thisLinkResult = $Result_Set - $iPerPage;
    if ($thisLinkResult < 0) {
        $thisLinkResult = 0;
    }
    echo '<a href="FindFundRaiser.php?Result_Set=' . $thisLinkResult . '&Sort=' . $sSort . '">' . gettext('Previous Page') . '</a>&nbsp;&nbsp;';
}

// Calculate starting and ending Page-Number Links
$Pages = ceil($Total / $iPerPage);
$startpage = (ceil($Result_Set / $iPerPage)) - 6;
if ($startpage <= 2) {
    $startpage = 1;
}
$endpage = (ceil($Result_Set / $iPerPage)) + 9;
if ($endpage >= ($Pages - 1)) {
    $endpage = $Pages;
}

// Show Link "1 ..." if startpage does not start at 1
if ($startpage != 1) {
    echo "<a href=\"FindFundRaiser.php?Result_Set=0&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">1</a> ... ";
}

// Display page links
if ($Pages > 1) {
    for ($c = $startpage; $c <= $endpage; $c++) {
        $b = $c - 1;
        $thisLinkResult = $iPerPage * $b;
        if ($thisLinkResult != $Result_Set) {
            echo "<a href=\"FindFundRaiser.php?Result_Set=$thisLinkResult&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">$c</a>&nbsp;";
        } else {
            echo '&nbsp;&nbsp;[ ' . $c . ' ]&nbsp;&nbsp;';
        }
    }
}

// Show Link "... xx" if endpage is not the maximum number of pages
if ($endpage != $Pages) {
    $thisLinkResult = ($Pages - 1) * $iPerPage;
    echo " <a href=\"FindFundRaiser.php?Result_Set=$thisLinkResult&Sort=$sSort&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd\">$Pages</a>";
}

// Show next-page link unless we're at the last page
if ($Result_Set >= 0 && $Result_Set < $Total) {
    $thisLinkResult = $Result_Set + $iPerPage;
    if ($thisLinkResult < $Total) {
        echo "&nbsp;&nbsp;<a href='FindFundRaiser.php?Result_Set=$thisLinkResult&Sort=$sSort'>" . gettext('Next Page') . '</a>&nbsp;&nbsp;';
    }
}

// Display Record Limit
echo '<input type="hidden" name="Result_Set" value="' . $Result_Set . '">';
if (isset($sSort)) {
    echo '<input type="hidden" name="Sort" value="' . $sSort . '">';
}

$sLimit5 = '';
$sLimit10 = '';
$sLimit20 = '';
$sLimit25 = '';
$sLimit50 = '';

if ($iPerPage === 5) {
    $sLimit5 = 'selected';
} elseif ($iPerPage === 10) {
    $sLimit10 = 'selected';
} elseif ($iPerPage === 20) {
    $sLimit20 = 'selected';
} elseif ($iPerPage === 25) {
    $sLimit25 = 'selected';
} elseif ($iPerPage === 50) {
    $sLimit50 = 'selected';
}

echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . gettext('Display:') . "&nbsp;
	<select class=\"SmallText\" name=\"Number\">
		<option value=\"5\" $sLimit5>5</option>
		<option value=\"10\" $sLimit10>10</option>
		<option value=\"20\" $sLimit20>20</option>
		<option value=\"25\" $sLimit25>25</option>
		<option value=\"50\" $sLimit50>50</option>
	</select>&nbsp;
	<input type=\"submit\" class=\"icTinyButton\" value=\"" . gettext('Go') . '">
	</form></div><br>';

// Column Headings
echo "<table cellpadding='4' align='center' cellspacing='0' width='100%'>\n
	<tr class='TableHeader'>\n
	<td width='25'>" . gettext('Edit') . "</td>\n
	<td><a href='FindFundRaiser.php?Sort=number&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd'>" . gettext('Number') . "</a></td>\n
	<td><a href='FindFundRaiser.php?Sort=date'&ID=$iID&DateStart=$dDateStart&DateEnd=$dDateEnd>" . gettext('Date') . "</a></td>\n
	<td>" . gettext('Title') . "</td>\n
	</tr>";

// Display Deposits
while (list($fr_ID, $fr_Date, $fr_Title) = mysqli_fetch_row($rsDep)) {
    echo "<tr><td><a href='FundRaiserEditor.php?FundRaiserID=$fr_ID'>" . gettext('Edit') . '</td>';
    echo "<td>$fr_ID</td>";
    echo "<td>$fr_Date</td>";
    // Get deposit total
    echo "<td>$fr_Title</td>";
}
echo '</table>';
?>
</div>
<?php require 'Include/Footer.php' ?>
