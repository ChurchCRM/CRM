<?php
/*******************************************************************************
 *
 *  filename    : QueryView.php
 *  last change : 2012-07-22
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
 *                Copyright 2004-2012 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Query View');

//Get the QueryID from the querystring
$iQueryID = InputUtils::LegacyFilterInput($_GET['QueryID'], 'int');

$aFinanceQueries = explode(',', SystemConfig::getValue('aFinanceQueries'));

if (!$_SESSION['bFinance'] && in_array($iQueryID, $aFinanceQueries)) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

//Include the header
require 'Include/Header.php';

//Get the query information
$sSQL = 'SELECT * FROM query_qry WHERE qry_ID = '.$iQueryID;
$rsSQL = RunQuery($sSQL);
extract(mysqli_fetch_array($rsSQL));

//Get the parameters for this query
$sSQL = 'SELECT * FROM queryparameters_qrp WHERE qrp_qry_ID = '.$iQueryID.' ORDER BY qrp_ID';
$rsParameters = RunQuery($sSQL);

//If the form was submitted or there are no parameters, run the query
if (isset($_POST['Submit']) || mysqli_num_rows($rsParameters) == 0) {
    //Check that all validation rules were followed
    ValidateInput();

    //Any errors?
    if (count($aErrorText) == 0) {
        //No errors; process the SQL, run the query, and display the results
        DisplayQueryInfo();
        ProcessSQL();
        DoQuery();
    } else {
        //Yes, there were errors; re-display the parameter form (the DisplayParameterForm function will
        //pick up and display any error messages)
        DisplayQueryInfo();
        DisplayParameterForm();
    }
} else {
    //Display the parameter form
    DisplayQueryInfo();
    DisplayParameterForm();
}

//Loops through all the parameters and ensures validation rules have been followed
function ValidateInput()
{
    global $rsParameters;
    global $_POST;
    global $vPOST;
    global $aErrorText;

    //Initialize the validated post array, error text array, and the error flag
    $vPOST = [];
    $aErrorText = [];
    $bError = false;

    //Are there any parameters to loop through?
    if (mysqli_num_rows($rsParameters)) {
        mysqli_data_seek($rsParameters, 0);
    }
    while ($aRow = mysqli_fetch_array($rsParameters)) {
        extract($aRow);

        //Is the value required?
        if ($qrp_Required && strlen(trim($_POST[$qrp_Alias])) < 1) {
            $bError = true;
            $aErrorText[$qrp_Alias] = gettext('This value is required.');
        }

        //Assuming there was no error above...
        else {
            //Validate differently depending on the contents of the qrp_Validation field
            switch ($qrp_Validation) {
                //Numeric validation
                case 'n':

                    //Is it a number?
                    if (!is_numeric($_POST[$qrp_Alias])) {
                        $bError = true;
                        $aErrorText[$qrp_Alias] = gettext('This value must be numeric.');
                    } else {
                        //Is it more than the minimum?
                        if ($_POST[$qrp_Alias] < $qrp_NumericMin) {
                            $bError = true;
                            $aErrorText[$qrp_Alias] = gettext('This value must be at least ').$qrp_NumericMin;
                        }
                        //Is it less than the maximum?
                        elseif ($_POST[$qrp_Alias] > $qrp_NumericMax) {
                            $bError = true;
                            $aErrorText[$qrp_Alias] = gettext('This value cannot be more than ').$qrp_NumericMax;
                        }
                    }

                    $vPOST[$qrp_Alias] = InputUtils::LegacyFilterInput($_POST[$qrp_Alias], 'int');
                    break;

                //Alpha validation
                case 'a':

                    //Is the length less than the maximum?
                    if (strlen($_POST[$qrp_Alias]) > $qrp_AlphaMaxLength) {
                        $bError = true;
                        $aErrorText[$qrp_Alias] = gettext('This value cannot be more than ').$qrp_AlphaMaxLength.gettext(' characters long');
                    }
                    //is the length more than the minimum?
                    elseif (strlen($_POST[$qrp_Alias]) < $qrp_AlphaMinLength) {
                        $bError = true;
                        $aErrorText[$qrp_Alias] = gettext('This value cannot be less than ').$qrp_AlphaMinLength.gettext(' characters long');
                    }

                    $vPOST[$qrp_Alias] = InputUtils::LegacyFilterInput($_POST[$qrp_Alias]);
                    break;

                default:
                    $vPOST[$qrp_Alias] = $_POST[$qrp_Alias];
                    break;
            }
        }
    }
}

//Loops through the list of parameters and replaces their alias in the SQL with the value given for the parameter
function ProcessSQL()
{
    global $vPOST;
    global $qry_SQL;
    global $rsParameters;

    //Loop through the list of parameters
    if (mysqli_num_rows($rsParameters)) {
        mysqli_data_seek($rsParameters, 0);
    }
    while ($aRow = mysqli_fetch_array($rsParameters)) {
        extract($aRow);

        //Debugging code
        //echo "--" . $qry_SQL . "<br>--" . "~" . $qrp_Alias . "~" . "<br>--" . $vPOST[$qrp_Alias] . "<p>";

        //Replace the placeholder with the parameter value
        $qry_SQL = str_replace('~'.$qrp_Alias.'~', $vPOST[$qrp_Alias], $qry_SQL);
    }
}

//Checks if a count is to be displayed, and displays it if required
function DisplayRecordCount()
{
    global $qry_Count;
    global $rsQueryResults;

    //Are we supposed to display a count for this query?
    if ($qry_Count == 1) {
        //Display the count of the recordset
        echo '<p align="center">';
        echo mysqli_num_rows($rsQueryResults).gettext(' record(s) returned');
        echo '</p>';
    }
}

//Runs the parameterized SQL and display the results
function DoQuery()
{
    global $cnInfoCentral;
    global $aRowClass;
    global $rsQueryResults;
    global $qry_SQL;
    global $iQueryID;
    global $qry_Name;
    global $qry_Count;

    //Run the SQL
    $rsQueryResults = RunQuery($qry_SQL); ?>
<div class="box box-primary">
    
    <div class="box-body">
        <p class="text-right">
            <?= $qry_Count ? mysqli_num_rows($rsQueryResults).gettext(' record(s) returned') : ''; ?>
        </p>
        
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <?php
                    //Loop through the fields and write the header row
                    for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
                        //If this field is called "AddToCart", don't display this field...
                        $fieldInfo = mysqli_fetch_field_direct($rsQueryResults, $iCount);
                        if ($fieldInfo->name != 'AddToCart') {
                            echo '<th>'.$fieldInfo->name.'</th>';
                        }
                    } ?>
            </thead>
            <tbody>
<?php
    $aHiddenFormField = [];

    while ($aRow = mysqli_fetch_array($rsQueryResults)) {
        //Alternate the background color of the row
        $sRowClass = AlternateRowStyle($sRowClass);

        echo '<tr class="'.$sRowClass.'">';

        //Loop through the fields and write each one
        for ($iCount = 0; $iCount < mysqli_num_fields($rsQueryResults); $iCount++) {
            //If this field is called "AddToCart", add this to the hidden form field...
            $fieldInfo = mysqli_fetch_field_direct($rsQueryResults, $iCount);
            if ($fieldInfo->name == 'AddToCart') {
                $aHiddenFormField[] = $aRow[$iCount];
            }
            //...otherwise just render the field
            else {
                //Write the actual value of this row
                echo '<td>'.$aRow[$iCount].'</td>';
            }
        }

        echo '</tr>';
    } ?>
            </tbody>
        </table>
        </div>
    </div>
    
    <div class="box-footer">
        <p>
        <?php if (count($aHiddenFormField)): ?>
            <form method="post" action="CartView.php">
            <div class="col-sm-offset-1">
                <input type="hidden" value="<?= implode(',', $aHiddenFormField) ?>" name="BulkAddToCart">
                <input type="submit" class="btn btn-primary btn-sm" name="AddToCartSubmit" value="<?= gettext('Add To Cart') ?>">
                <input type="submit" class="btn btn-warning btn-sm" name="AndToCartSubmit" value="<?= gettext('Intersect With Cart') ?>">
                <input type="submit" class="btn btn-danger btn-sm" name="NotToCartSubmit" value="<?= gettext('Remove From Cart') ?>">
            </div>
            </form>
        <?php endif; ?>
        </p>
        
        <p class="text-right">
            <?= '<a href="QueryView.php?QueryID='.$iQueryID.'">'.gettext('Run Query Again').'</a>'; ?>
        </p>
    </div>

</div>

<div class="box box-info">
    <div class="box-header with-border">
        <div class="box-title">Query</div>
    </div>
    <div class="box-body">
        <code><?= str_replace(chr(13), '<br>', htmlspecialchars($qry_SQL)); ?></code>
    </div>
</div>
<?php
}


//Displays the name and description of the query
function DisplayQueryInfo()
{
    global $qry_Name;
    global $qry_Description; ?>
<div class="box box-info">
    <div class="box-body">
        <p><strong><?= gettext($qry_Name); ?></strong></p>
        <p><?= gettext($qry_Description); ?></p>
    </div>
</div>
<?php
}


function getQueryFormInput($queryParameters)
{
    global $aErrorText;
    
    extract($queryParameters);
    
    $input = '';
    $label = '<label>' . gettext($qrp_Name) . '</label>';
    $helpMsg = '<div>' . gettext($qrp_Description) . '</div>';

    switch ($qrp_Type) {
        //Standard INPUT box
        case 0:
            $input = '<input size="'.$qrp_InputBoxSize.'" name="'.$qrp_Alias.'" type="text" value="'.$qrp_Default.'" class="form-control">';
            break;

        //SELECT box with OPTION tags supplied in the queryparameteroptions_qpo table
        case 1:
            //Get the query parameter options for this parameter
            $sSQL = 'SELECT * FROM queryparameteroptions_qpo WHERE qpo_qrp_ID = '.$qrp_ID;
            $rsParameterOptions = RunQuery($sSQL);

            $input = '<select name="'.$qrp_Alias.'" class="form-control">';
            $input .= '<option disabled selected value> -- ' . gettext("select an option"). ' -- </option>';
            
            //Loop through the parameter options
            while ($ThisRow = mysqli_fetch_array($rsParameterOptions)) {
                extract($ThisRow);
                $input .= '<option value="'.$qpo_Value.'">'.gettext($qpo_Display).'</option>';
            }

            $input .= '</select>';
            break;

        //SELECT box with OPTION tags provided via a SQL query
        case 2:
            //Run the SQL to get the options
            $rsParameterOptions = RunQuery($qrp_OptionSQL);

            $input .= '<select name="'.$qrp_Alias.'" class="form-control">';
            $input .= '<option disabled selected value> -- select an option -- </option>';

            while ($ThisRow = mysqli_fetch_array($rsParameterOptions)) {
                extract($ThisRow);
                $input .= '<option value="'.$Value.'">'.$Display.'</option>';
            }

            $input .= '</select>';
            break;
    }
    
    $helpBlock = '<div class="help-block">' . $helpMsg . '</div>';
    
    if ($aErrorText[$qrp_Alias]) {
        $errorMsg = '<div>' . $aErrorText[$qrp_Alias] . '</div>';
        $helpBlock = '<div class="help-block">' . $helpMsg . $errorMsg . '</div>';
        return '<div class="form-group has-error">' . $label . $input . $helpBlock . '</div>';
    }
    
    return '<div class="form-group">' . $label . $input . $helpBlock . '</div>';
}

//Displays a form to enter values for each parameter, creating INPUT boxes and SELECT drop-downs as necessary
function DisplayParameterForm()
{
    global $rsParameters;
    global $iQueryID; ?>
<div class="row">
    <div class="col-md-8">
        
        <div class="box box-primary">
            
            <div class="box-body">
            
                <form method="post" action="QueryView.php?QueryID=<?= $iQueryID ?>">
<?php
//Loop through the parameters and display an entry box for each one
if (mysqli_num_rows($rsParameters)) {
    mysqli_data_seek($rsParameters, 0);
}
    while ($aRow = mysqli_fetch_array($rsParameters)) {
        echo getQueryFormInput($aRow);
    } ?>
                    
                    <div class="form-group text-right">
                        <input class="btn btn-primary" type="Submit" value="<?= gettext("Execute Query") ?>" name="Submit">
                    </div>
                </form>
                
            </div>
        </div> <!-- box -->
        
    </div>
    
</div>
<?php
}

require 'Include/Footer.php';
?>
