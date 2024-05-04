<?php

/*******************************************************************************
 *
 *  filename    : QuerySQL.php
 *  last change : 2003-01-04
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Free-Text Query');

// Security: User must be an Admin to access this page.  It allows unrestricted database access!
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfNotAdmin();

if (isset($_POST['SQL'])) {
    //Assign the value locally
    $sSQL = stripslashes(trim($_POST['SQL']));
} else {
    $sSQL = '';
}

if (isset($_POST['CSV'])) {
    ExportQueryResults($sSQL, $rsQueryResults);
    exit;
}

require 'Include/Header.php';
?>

<form method="post">

<center><table><tr>
    <td class="LabelColumn"> <?= gettext('Export Results to CSV file') ?> </td>
    <td class="TextColumn"><input name="CSV" type="checkbox" id="CSV" value="1"></td>
</tr></table></center>

<p align="center">
    <textarea style="font-family:courier,fixed; font-size:9pt; padding:1rem;" cols="60" rows="10" name="SQL"><?= $sSQL ?></textarea>
</p>
<p align="center">
    <input type="submit" class="btn btn-default" name="Submit" value="<?= gettext('Execute SQL') ?>">
</p>

</form>

<?php


if (isset($_POST['SQL'])) {
    if (strtolower(mb_substr($sSQL, 0, 6)) === 'select') {
        RunFreeQuery($sSQL, $rsQueryResults);
    }
}

function ExportQueryResults(string $sSQL, &$rsQueryResults)
{
    global $cnInfoCentral;

    $sCSVstring = '';

    //Run the SQL
    $rsQueryResults = RunQuery($sSQL);

    if (mysqli_error($cnInfoCentral) != '') {
        $sCSVstring = gettext('An error occurred: ') . mysqli_errno($cnInfoCentral) . '--' . mysqli_error($cnInfoCentral);
    } else {
        //Loop through the fields and write the header row
        for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
            $fieldInfo = mysqli_fetch_field_direct($rsQueryResults, $iCount);
            $sCSVstring .= $fieldInfo->name . ',';
        }

        $sCSVstring .= "\n";

        //Loop through the recordsert
        while ($aRow = mysqli_fetch_array($rsQueryResults)) {
            //Loop through the fields and write each one
            for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
                $outStr = str_replace('"', '""', $aRow[$iCount]);
                $sCSVstring .= '"' . $outStr . '",';
            }

            $sCSVstring .= "\n";
        }
    }

    header('Content-type: application/csv');
    header('Content-Disposition: attachment; filename=Query-' . date(SystemConfig::getValue("sDateFilenameFormat")) . '.csv');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    echo $sCSVstring;
    exit;
}

//Display the count of the recordset
if (isset($_POST['SQL'])) {
    echo '<p align="center">';
    echo mysqli_num_rows($rsQueryResults) . gettext(' record(s) returned');
    echo '</p>';
}

function RunFreeQuery(string $sSQL, &$rsQueryResults)
{
    global $cnInfoCentral;

    //Run the SQL
    $rsQueryResults = RunQuery($sSQL);

    if (mysqli_error($cnInfoCentral) != '') {
        echo gettext('An error occurred: ') . mysqli_errno($cnInfoCentral) . '--' . mysqli_error($cnInfoCentral);
    } else {
        $sRowClass = 'RowColorA';

        echo '<table align="center" cellpadding="5" cellspacing="0">';

        echo '<tr class="' . $sRowClass . '">';

        //Loop through the fields and write the header row
        for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
            //If this field is called "AddToCart", don't display this field...
            $fieldInfo = mysqli_fetch_field_direct($rsQueryResults, $iCount);
            if ($fieldInfo->name != 'AddToCart') {
                echo '  <td align="center">
							<b>' . $fieldInfo->name . '</b>
							</td>';
            }
        }

        echo '</tr>';

        //Loop through the recordsert
        while ($aRow = mysqli_fetch_array($rsQueryResults)) {
            $sRowClass = AlternateRowStyle($sRowClass);

            echo '<tr class="' . $sRowClass . '">';

            //Loop through the fields and write each one
            for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
                //If this field is called "AddToCart", add this to the hidden form field...
                $fieldInfo = mysqli_fetch_field_direct($rsQueryResults, $iCount);
                if ($fieldInfo->name === 'AddToCart') {
                    $aHiddenFormField[] = $aRow[$iCount];
                } else {  //...otherwise just render the field
                    //Write the actual value of this row
                    echo '<td align="center">' . $aRow[$iCount] . '</td>';
                }
            }
            echo '</tr>';
        }

        echo '</table>';
        echo '<p align="center">';

        if ($aHiddenFormField && count($aHiddenFormField) > 0) { // TODO Don't post to CartView.php?>
            <form method="post" action="CartView.php"><p align="center">
                <input type="hidden" value="<?= implode(',', $aHiddenFormField) ?>" name="BulkAddToCart">
                <input type="submit" class="btn btn-default" name="AddToCartSubmit" value="<?php echo gettext('Add Results To Cart'); ?>">&nbsp;
                <input type="submit" class="btn btn-default" name="AndToCartSubmit" value="<?php echo gettext('Intersect Results With Cart'); ?>">&nbsp;
                <input type="submit" class="btn btn-default" name="NotToCartSubmit" value="<?php echo gettext('Remove Results From Cart'); ?>">
            </p></form>
            <?php
        }

        echo '<p align="center"><a href="QueryList.php">' . gettext('Return to Query Menu') . '</a></p>';
        echo '<br><p class="ShadedBox" style="border-style: solid; margin-left: 50px; margin-right: 50px; border-width: 1px;"><span class="SmallText">' . str_replace(chr(13), '<br>', htmlspecialchars($sSQL)) . '</span></p>';
    }
}

require 'Include/Footer.php';

?>
