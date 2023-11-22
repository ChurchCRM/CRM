<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;

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
<div class="card">
    <div class="card-body">
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
              /* @var Family $family */
                ?>
            <tr>
              <td><a href='<?= SystemURLs::getRootPath()?>/v2/family/<?= $family->getId() ?>'>
                      <i class="fa fa-search-plus"></i>
                    </a> 
                    <a href='<?= SystemURLs::getRootPath()?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                        <i class="fas fa-pen"></i>
                    </a></td>
              <td><?= $family->getName() ?></td>
                <td> <?= $family->getAddress() ?></td>
                <td><?= $family->getHomePhone() ?></td>
                <td><?= $family->getCellPhone() ?></td>
                <td><?= $family->getEmail() ?></td>
                <td><?= date_format($family->getDateEntered(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                <td>
                  <?php if ($family->getDateLastEdited()) {
                        echo date_format($family->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong'));
                  } ?>
                </td>

            </tr>
            <?php } ?>
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
