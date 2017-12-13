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
require 'Include/QueryFunctions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;

//Include the header
require 'Include/Header.php';

//Set the page title
$sPageTitle = gettext('Query View 2.0');
if (isset($_POST['Submit'])) {
    DoQuery();
} else {
    DisplayQuery();
}

function DisplayQuery()
{
    ?>
<div class="box box-info">
    <div class="box-body">
        <p><strong><?= gettext('Birthdays'); ?></strong></p>
        <p><?= gettext('People with birthdays in a particular month'); ?></p>
    </div>
</div>
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-body">
                <form method="post" action="">
                    <div class="form-group">
                        <label><?= gettext('Month') ?></div>
                        <select name="birthMonth" class="form-control">
                        <option disabled="" selected="" value=""> -- Select an option -- </option>
                        <?php
                        foreach ($birthdayMonths as $monthNum => $monthName) {
                            ?>
                            <option value="<?= $monthNum ?>"><?= $monthName ?></option>
                        <?php
                        } ?>
                        </select>
                        <div class="help-block"><div><?= gettext('The birthday month for which you would like records returned.') ?></div>
                    </div>
                    <div class="form-group">
                        <label><?= gettext('Classification') ?></div>
                        <select name="percls" class="form-control">
                        <option disabled="" selected="" value=""> -- Select an option -- </option>
                        <?php
                        foreach ($rsMembershipClasses as $Member) {
                            ?>
                            <option value="<?= $Member->getOptionSequence() ?>"><?= $Member->getOptionName() ?></option>
                        <?php
                        } ?>

                        </select>
                        <div class="help-block"><div><?= gettext('Member, Regular Attender, etc.') ?></div>
                    </div>
                    <div class="form-group text-right">
                        <input class="btn btn-primary" type="Submit" value="<?= gettext("Execute Query") ?>" name="Submit">
                    </div>
                </form>                
            </div>
        </div>       
    </div>
</div>
<?php
}

function DoQuery()
{
    ?>
    <div class="box box-primary">   
    <div class="box-body">
        <p class="text-right">
            <?= $qry_Count ? mysqli_num_rows($rsQueryResults).gettext(' record(s) returned') : ''; ?>
        </p>
        
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
            <th>Table Header</th>
            </thead>
            <tbody>
            <tr>
            <td>Table Cell</td>
            </tr>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php
}
require 'Include/Footer.php';
?>
