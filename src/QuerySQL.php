<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Free-Text Query');

// Security: User must be an Admin to access this page.  It allows unrestricted database access!
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfNotAdmin();

if (isset($_POST['SQL'])) {
    // Assign the value locally
    $sSQL = stripslashes(trim($_POST['SQL']));
} else {
    $sSQL = '';
}

if (isset($_POST['CSV'])) {
    ExportQueryResults($sSQL, $rsQueryResults);
    exit;
}

require_once 'Include/Header.php';
?>

<form method="post">
    <div class="text-center">
        <table>
            <tr>
                <td class="LabelColumn"> <?= gettext('Export Results to CSV file') ?> </td>
                <td class="TextColumn"><input name="CSV" type="checkbox" id="CSV" value="1"></td>
            </tr>
        </table>
    </div>

    <p class="text-center">
        <textarea style="font-family:courier,fixed; font-size:9pt; padding:1rem;" cols="60" rows="10" name="SQL"><?= $sSQL ?></textarea>
    </p>
    <p class="text-center">
        <input type="submit" class="btn btn-secondary" name="Submit" value="<?= gettext('Execute SQL') ?>">
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

        //Loop through the recordset
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
    echo '<p class="text-center">';
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

        echo '<table class="mx-auto table-spaced">';

        echo '<tr class="' . $sRowClass . '">';

        //Loop through the fields and write the header row
        for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
            //If this field is called "AddToCart", don't display this field...
            $fieldInfo = mysqli_fetch_field_direct($rsQueryResults, $iCount);
            if ($fieldInfo->name != 'AddToCart') {
                echo '  <td class="text-center">
                            <b>' . $fieldInfo->name . '</b>
                            </td>';
            }
        }

        echo '</tr>';

        //Loop through the recordset
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
                    echo '<td class="text-center">' . InputUtils::escapeHTML($aRow[$iCount]) . '</td>';
                }
            }
            echo '</tr>';
        }

        echo '</table>';
        echo '<p class="text-center">';

        if ($aHiddenFormField && count($aHiddenFormField) > 0) {
?>
            <form method="post" action="CartView.php">
                <p class="text-center">
                    <input type="hidden" value="<?= implode(',', $aHiddenFormField) ?>" name="BulkAddToCart">
                    <input type="submit" class="btn btn-secondary" name="AddToCartSubmit" value="<?php echo gettext('Add Results To Cart'); ?>">&nbsp;
                    <input type="submit" class="btn btn-secondary" name="AndToCartSubmit" value="<?php echo gettext('Intersect Results With Cart'); ?>">&nbsp;
                    <input type="submit" class="btn btn-secondary" name="NotToCartSubmit" value="<?php echo gettext('Remove Results From Cart'); ?>">
                </p>
            </form>
<?php
        }

        echo '<p class="text-center"><a href="QueryList.php">' . gettext('Return to Query Menu') . '</a></p>';
        echo '<br><p class="card card-body" style="border-style: solid; margin-left: 50px; margin-right: 50px; border-width: 1px;"><span class="SmallText">' . str_replace(chr(13), '<br>', InputUtils::escapeHTML($sSQL)) . '</span></p>';
    }
}

require_once 'Include/Footer.php';
