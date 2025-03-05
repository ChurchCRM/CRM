<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$sPageTitle = gettext('Fundraiser Listing');

$sDateFormat = SystemConfig::getValue('sDatePickerFormat');

$fundraisersQuery = FundraiserQuery::Create()    
    ->orderByDate('desc');

if (array_key_exists('DateStart', $_GET)) {
    $dDateStart = InputUtils::legacyFilterInput($_GET['DateStart']);
    if ($dDateStart !== "") {
        $dDateStartObj = DateTime::createFromFormat($sDateFormat, $dDateStart);
        $fundraisersQuery->filterByDate($dDateStartObj, Criteria::GREATER_EQUAL);
    }
}
if (array_key_exists('DateEnd', $_GET)) {
    $dDateEnd = InputUtils::legacyFilterInput($_GET['DateEnd']);
    if ($dDateEnd !== "") {
        $dDateEndObj = DateTime::createFromFormat($sDateFormat, $dDateEnd);
        $fundraisersQuery->filterByDate($dDateEndObj, Criteria::LESS_EQUAL);
    }
}

        
$fundraisers = $fundraisersQuery->find();

require_once 'Include/Header.php';

?>
<div class="card card-body">
    <form method="get" action="FindFundRaiser.php" name="FindFundRaiser">
        <tr>
            <td>
                <table cellpadding="3" width="100%">
                    <tr>
                        <td class="LabelColumn"><?= gettext('Date Start') ?>:</td>
                        <td class="TextColumn"><input type="text" name="DateStart" maxlength="10" id="DateStart" size="11" value="<?= $dDateStart ?>" class="date-picker"></td>
                        <td class="LabelColumn"><?= gettext('Date End') ?>:</td>
                        <td class="TextColumn"><input type="text" name="DateEnd" maxlength="10" id="DateEnd" size="11" value="<?= $dDateEnd ?>" class="date-picker"></td>
                    </tr>
                    <tr>
                        <td colspan=4 align="center">
                            <input type="submit" class="btn btn-primary" value="<?= gettext('Apply Filters') ?>" name="FindFundRaiserSubmit">
                            <input type="button" class="btn btn-danger" value="<?= gettext('Clear Filters') ?>" onclick="javascript:document.location='FindFundRaiser.php';">
                        </td>
                    </tr>
                </table>
            </td>
    </form>
    </table>
</div>
<div class="card card-body">
  <!-- /.box-header -->
  <div class="card-body table-responsive">
    <table id="fundraisers" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th><?= gettext('Title') ?></th>
                <th><?= gettext('Date') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($fundraisers as $fundraiser) { ?>
                <tr>
                    <td><a href="FundRaiserEditor.php?FundRaiserID=<?= $fundraiser->getId() ?>"> <?= $fundraiser->getTitle() ?> </a></td>
                    <td><?= $fundraiser->getDate()->format($sDateFormat) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
  </div>
</div>
<?php
require_once 'Include/Footer.php';
