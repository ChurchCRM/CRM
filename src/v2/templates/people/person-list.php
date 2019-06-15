<?php


use ChurchCRM\dto\SystemConfig;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('List');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="pull-right">
  <a class="btn btn-success" role="button" href="<?= SystemURLs::getRootPath()?>/PersonEditor.php">
    <span class="fa fa-plus" aria-hidden="true"></span><?= gettext('Add Person') ?>
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
                <?= SystemConfig::getValue('bHidePersonAddress') ?'': '<th>' . gettext('Address') . '</th>' ?>
                <th><?= gettext('Home Phone') ?></th>
                <th><?= gettext('Cell Phone') ?></th>
                <th><?= gettext('Email') ?></th>
                <th><?= gettext('Created') ?></th>
                <th><?= gettext('Edited') ?></th>
            </tr>
            </thead>
            <tbody>

            <!--Populate the table with person details -->
            <?php foreach ($member as $person) {
              /* @var $member ChurchCRM\people */
              
    ?>
            <tr>
              <td><a href='<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $person->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>
                    <a href='<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>
 
                    <?php if (!isset($_SESSION['aPeopleCart']) || !in_array($per_ID, $_SESSION['aPeopleCart'], false)) {
                            ?>
                          <a class="AddToPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                        <span class="fa-stack">
                                    <i class="fa fa-square fa-stack-2x"></i>
                                    <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
                                </span>
                            </a>
                        </td>
                      <?php
                        } else {
                            ?>
                        <a class="RemoveFromPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                        <span class="fa-stack">
                                    <i class="fa fa-square fa-stack-2x"></i>
                                    <i class="fa fa-remove fa-stack-1x fa-inverse"></i>
                                </span>
                            </a>
                            <?php
                        }
                            ?>

              <td><?= $person->getFormattedName(SystemConfig::getValue('iPersonNameStyle')) ?></td>
                <?= SystemConfig::getValue('bHidePersonAddress') ?'': '<td>' . $person->getAddress() . '</hd>' ?>
                <td><?= $person->getHomePhone() ?></td>
                <td><?= $person->getCellPhone() ?></td>
                <td><?= $person->getEmail() ?></td>
                <td><?= date_format($person->getDateEntered(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                <td><?= date_format($person->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                <?php
}
                ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function() {
      //$('#families').DataTable( {"order": [[ 1, "asc" ]]} );
      $('#families').DataTable(window.CRM.plugin.dataTable);
  } );
</script>

<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
?>
