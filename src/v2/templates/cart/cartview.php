<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$ListTitleText = gettext('Your cart contains') . ' ' . count($cartPeople) . ' ' . gettext('people from') . ' ' . $iNumFamilies . ' ' . gettext('families');
?>

<!-- Cart Functions -->
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?= gettext('Cart Functions') ?></h3>
  </div>
  <div class="card-body">
    <div class="btn-group flex-wrap" role="group">
      <a href="#" id="emptyCart" class="btn btn-outline-danger emptyCart" title="<?= gettext('Clear all items from cart') ?>"><i class="fa-solid fa-trash me-2"></i><?= gettext('Empty') ?></a>
      <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) { ?>
        <a id="emptyCartToGroup" class="btn btn-outline-primary" title="<?= gettext('Add all cart items to a group') ?>"><i class="fa-solid fa-users me-2"></i><?= gettext('To Group') ?></a>
      <?php }
      if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) { ?>
        <a href="<?= SystemURLs::getRootPath() ?>/CartToFamily.php" class="btn btn-outline-success" title="<?= gettext('Add cart items to a family') ?>"><i class="fa-solid fa-people-roof me-2"></i><?= gettext('To Family') ?></a>
      <?php } ?>
      <a href="<?= SystemURLs::getRootPath() ?>/CartToEvent.php" class="btn btn-outline-info" title="<?= gettext('Check in to an event') ?>"><i class="fa-solid fa-ticket-alt me-2"></i><?= gettext('Check In') ?></a>
      <a href="<?= SystemURLs::getRootPath() ?>/v2/map?groupId=0" class="btn btn-outline-info" title="<?= gettext('Map cart items') ?>"><i class="fa-solid fa-map-marker me-2"></i><?= gettext('Map') ?></a>
      <a href="<?= SystemURLs::getRootPath() ?>/Reports/NameTags.php?labeltype=74536&labelfont=times&labelfontsize=36" class="btn btn-outline-secondary" title="<?= gettext('Print name tags') ?>"><i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Tags') ?></a>
    </div>
    <?php if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { ?>
      <div class="btn-group" role="group">
        <a href="mailto:<?= $sEmailLink ?>" class="btn btn-outline-info" title="<?= gettext('Email cart items') ?>">
          <i class="fa-solid fa-paper-plane me-2"></i><?= gettext('Email') ?>
        </a>
        <a href="mailto:?bcc=<?= $sEmailLink ?>" class="btn btn-outline-secondary" title="<?= gettext('Email with hidden recipients') ?>">
          <i class="fa-solid fa-user-secret me-2"></i><?= gettext('BCC') ?>
        </a>
      </div>
    <?php } ?>
    <a href="<?= SystemURLs::getRootPath() ?>/DirectoryReports.php?cartdir=Cart+Directory" class="btn btn-outline-warning" title="<?= gettext('Generate phone directory') ?>">
      <i class="fa-solid fa-book me-2"></i><?= gettext('Directory') ?>
    </a>
  </div>
</div>

<!-- Cart Listing -->
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?= $ListTitleText ?></h3>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover w-100" id="cart-listing-table">
        <thead>
          <tr>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Address') ?></th>
            <th><?= gettext('Email') ?></th>
            <th><?= gettext('Classification') ?></th>
            <th><?= gettext('Family Role') ?></th>
            <th class="no-export"><?= gettext('Actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          /* @var $Person ChurchCRM\Person */
          foreach ($cartPeople as $Person) { ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php
                    // Render a placeholder image element and let the client-side avatar-loader
                    // fetch avatar info and set photo/initials as appropriate.
                    echo '<img data-image-entity-type="person" data-image-entity-id="' . $Person->getId() . '" class="avatar avatar-sm rounded-circle photo-small me-2" alt="" />';
                  ?>
                  <a href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $Person->getId() ?>"><?= $Person->getFullName() ?></a>
                </div>
              </td>
              <td><?= $Person->getAddress() ?></td>
              <td><?= $Person->getEmail() ?></td>
              <td><?= $Person->getClassificationName() ?></td>
              <td><?= $Person->getFamilyRoleName() ?></td>
              <td>
                <div class="dropdown">
                  <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="ti ti-dots-vertical"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $Person->getId() ?>">
                      <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                    </a>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $Person->getId() ?>">
                      <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                    </a>
                    <?php if ($Person->getFamId()) { ?>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $Person->getFamId() ?>">
                      <i class="ti ti-users me-2"></i><?= gettext('View Family') ?>
                    </a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <button type="button"
                      class="dropdown-item RemoveFromCart text-danger"
                      data-cart-id="<?= $Person->getId() ?>"
                      data-cart-type="person"
                      data-label-add="<?= gettext('Add to Cart') ?>"
                      data-label-remove="<?= gettext('Remove from Cart') ?>">
                      <i class="ti ti-trash me-2"></i>
                      <span class="cart-label"><?= gettext('Remove from Cart') ?></span>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $(document).ready(function () {
    $("#cart-listing-table").DataTable(window.CRM.plugin.dataTable);
  });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
