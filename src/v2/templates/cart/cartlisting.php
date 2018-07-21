<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;

$ListTitleText = gettext('Your cart contains') . ' ' . count($cartPeople) . ' ' . gettext('persons from') . ' ' . $iNumFamilies . ' ' . gettext('families');
?>
<!-- BEGIN CART LISTING -->
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title"><?= $ListTitleText ?></h3>
  </div>
  <div class="box-body">
    <table class="table table-hover dt-responsive" id="cart-listing-table" style="width:100%;">
      <thead>
        <tr>
          <th><?= gettext('Name') ?></th>
          <th><?= gettext('Address') ?></th>
          <th><?= gettext('Email') ?></th>
          <th><?= gettext('Remove') ?></th>
          <th><?= gettext('Classification') ?></th>
          <th><?= gettext('Family Role') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        /* @var $Person ChurchCRM\Person */
        foreach ($cartPeople as $Person) {
          ?>
          <tr>
            <td>
              <img src="<?= $Person->getThumbnailURL(); ?>?>" class="direct-chat-img initials-image">&nbsp
              <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $Person->getId() ?>"><?= $Person->getFullName() ?></a>
            </td>
            <td><?= $Person->getAddress() ?></td>
            <td><?= $Person->getEmail() ?></td>
            <td><a class="RemoveFromPeopleCart" data-personid="<?= $Person->getId() ?>"><?= gettext('Remove') ?></a>
            </td>
            <td><?= $Person->getClassificationName() ?></td>
            <td><?= $Person->getFamilyRoleName() ?></td>
          </tr>
        <?php }
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- END CART LISTING -->