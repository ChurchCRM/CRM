<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$sPageTitle = gettext('Fundraiser Listing');

$sDateFormat = SystemConfig::getValue('sDatePickerFormat');

$fundraisersQuery = FundraiserQuery::Create()    
    ->orderByDate('desc');

if (array_key_exists('DateStart', $_GET)) {
    $dDateStart = InputUtils::legacyFilterInput($_GET['DateStart']);
    if ($dDateStart !=="") {
        $dDateStartObj = DateTime::createFromFormat($sDateFormat, $dDateStart);
        $fundraisersQuery->filterByDate($dDateStartObj, Criteria::GREATER_EQUAL);
    }
}
if (array_key_exists('DateEnd', $_GET)) {
    $dDateEnd = InputUtils::legacyFilterInput($_GET['DateEnd']);
    if ($dDateEnd !=="") {
        $dDateEndObj = DateTime::createFromFormat($sDateFormat, $dDateEnd);
        $fundraisersQuery->filterByDate($dDateEndObj, Criteria::LESS_EQUAL);
    }
}

        
$fundraisers = $fundraisersQuery->find();

require_once __DIR__ . '/Include/Header.php';

?>
<div class="card-body">
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
                        <td colspan=4 class="text-center">
                            <input type="submit" class="btn btn-primary" value="<?= gettext('Apply Filters') ?>" name="FindFundRaiserSubmit">
                            <input type="button" class="btn btn-danger" value="<?= gettext('Clear Filters') ?>" onclick="javascript:document.location='FindFundRaiser.php';">
                        </td>
                    </tr>
                </table>
            </td>
    </form>
    </table>
</div>
<div class="card-body">
  <div class="card-body" style="overflow: visible;">
    <table id="fundraisers" class="table table-striped table-bordered data-table w-100">
        <thead>
            <tr>
                <th><?= gettext('Title') ?></th>
                <th><?= gettext('Date') ?></th>
                <th class="w-1 no-export"><?= gettext('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($fundraisers as $fundraiser) { ?>
                <tr>
                    <td><?= InputUtils::escapeHTML($fundraiser->getTitle()) ?></td>
                    <td><?= $fundraiser->getDate()->format($sDateFormat) ?></td>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="FundRaiserEditor.php?FundRaiserID=<?= $fundraiser->getId() ?>">
                                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="FundRaiserDelete.php?FundRaiserID=<?= $fundraiser->getId() ?>&linkBack=FindFundRaiser.php" onclick="return confirm('<?= gettext('Are you sure you want to delete this fundraiser?') ?>')">
                                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
