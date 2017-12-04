<?php
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;

$sMode = 'Active';
// Filter received user input as needed
if (isset($_GET['mode'])) {
    $sMode = InputUtils::LegacyFilterInput($_GET['mode']);
}
if (strtolower($sMode) == 'inactive') {
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null, Criteria::ISNOTNULL)
            ->orderByName()
            ->find();
} else {
    $sMode = 'Active';
    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
            ->orderByName()
            ->find();
}

// Set the page title and include HTML header
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
require 'Include/Header.php'; ?>

<div class="pull-right">
  <a class="btn btn-success" role="button" href="FamilyEditor.php"> <span class="fa fa-plus"
                                                                          aria-hidden="true"></span><?= gettext('Add Family') ?>
  </a>
</div>
<p><br/><br/></p>
<div class="box">
    <div class="box-body">
        <table id="families" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
            <thead>
            <tr>
                <th><?= gettext('Name') ?></th>
                <th><?= gettext('Address') ?></th>
                <th><?= gettext('Home Phone') ?></th>
                <th><?= gettext('Cell Phone') ?></th>
                <th><?= gettext('email') ?></th>
                <th><?= gettext('Created') ?></th>
                <th><?= gettext('Edited') ?></th>
            </tr>
            </thead>
            <tbody>

            <!--Populate the table with family details -->
            <?php foreach ($families as $family) {
    ?>
            <tr>
                <td><a href='FamilyView.php?FamilyID=<?= $family->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>
                    <a href='FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
                    </a><?= $family->getName() ?></td>
                <td> <?= $family->getAddress() ?></td>
                <td><?= $family->getHomePhone() ?></td>
                <td><?= $family->getCellPhone() ?></td>
                <td><?= $family->getEmail() ?></td>
                <td><?= date_format($family->getDateEntered(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                <td><?= date_format($family->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                <?php
}
                ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
    $('#families').DataTable(window.CRM.plugin.dataTable);
  });
</script>

<?php
require 'Include/Footer.php';
?>
