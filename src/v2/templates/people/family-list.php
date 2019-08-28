<?php


use ChurchCRM\dto\SystemConfig;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="pull-right">
  <a class="btn btn-success" role="button" href="<?= SystemURLs::getRootPath()?>/FamilyEditor.php">
    <span class="fa fa-plus" aria-hidden="true"></span><?= gettext('Add Family') ?>
  </a>
</div>
<p><br/><br/></p>
<div class="box">
    <div class="box-body">
        <table id="families" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
            <thead>
            <tr>
                <th><?= gettext('Actions') ?></th>
                <th><?= gettext('Name') ?></th>
                <th><?= gettext('Address') ?></th>
                <th><?= gettext('Home Phone') ?></th>
                <th><?= gettext('Cell Phone') ?></th>
                <th><?= gettext('Email') ?></th>
                <th><?= gettext('Created') ?></th>
                <th><?= gettext('Edited') ?></th>
            </tr>
            </thead>
            <tbody>

            <!--Populate the table with family details -->
            <?php foreach ($families as $family) {
              /* @var $family ChurchCRM\Family */
    ?>
            <tr>
              <td><a href='<?= SystemURLs::getRootPath()?>/FamilyView.php?FamilyID=<?= $family->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>
                    <a href='<?= SystemURLs::getRootPath()?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
                    </a></td>
              <td><?= $family->getName() ?></td>
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
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
?>
