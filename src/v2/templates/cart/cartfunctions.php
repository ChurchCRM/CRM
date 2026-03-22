<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

?>
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title">Cart Functions</h3>
  </div>
  <div class="card-body">
    <div class="btn-group flex-wrap" role="group">
      <a href="#" id="emptyCart" class="btn btn-outline-danger emptyCart" title="<?= gettext('Clear all items from cart') ?>"><i class="fa-solid fa-trash me-2"></i><?= gettext('Empty') ?></a>
      <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
          ?>
        <a id="emptyCartToGroup" class="btn btn-outline-primary" title="<?= gettext('Add all cart items to a group') ?>"><i class="fa-solid fa-users me-2"></i><?= gettext('To Group') ?></a>
          <?php
      }
      if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
          ?>
        <a href="<?= SystemURLs::getRootPath() . "/CartToFamily.php"?>" class="btn btn-outline-success" title="<?= gettext('Add cart items to a family') ?>"><i class="fa-solid fa-people-roof me-2"></i><?= gettext('To Family') ?></a>
      <?php }
      ?>
      <a href="<?= SystemURLs::getRootPath() . "/CartToEvent.php"?>" class="btn btn-outline-info" title="<?= gettext('Check in to an event') ?>"><i class="fa-solid fa-ticket-alt me-2"></i><?= gettext('Check In') ?></a>
      <a href="<?= SystemURLs::getRootPath() . "/CSVExport.php?Source=cart" ?>" class="btn btn-outline-warning" title="<?= gettext('Export as CSV') ?>"><i class="fa-solid fa-file-csv me-2"></i><?= gettext('Export') ?></a>
      <a href="<?= SystemURLs::getRootPath() . "/v2/map?groupId=0"?>" class="btn btn-outline-info" title="<?= gettext('Map cart items') ?>"><i class="fa-solid fa-map-marker me-2"></i><?= gettext('Map') ?></a>
      <a href="<?= SystemURLs::getRootPath() . "/Reports/NameTags.php?labeltype=74536&labelfont=times&labelfontsize=36"?>" class="btn btn-outline-secondary" title="<?= gettext('Print name tags') ?>"><i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Tags') ?></a>
    </div>
      <?php

        if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
            // Display link
            ?>
            <div class="btn-group" role="group">
                <a href="mailto:<?= $sEmailLink ?>" class="btn btn-outline-info" title="<?= gettext('Email cart items') ?>">
                    <i class="fa-solid fa-paper-plane me-2"></i><?= gettext('Email') ?>
                </a>
                <a href="mailto:?bcc=<?= $sEmailLink ?>" class="btn btn-outline-secondary" title="<?= gettext('Email with hidden recipients') ?>">
                    <i class="fa-solid fa-user-secret me-2"></i><?= gettext('BCC') ?>
                </a>
                <a href="javascript:void(0)" onclick="allPhonesCommaD()" class="btn btn-outline-success" title="<?= gettext('Copy phone numbers to clipboard') ?>">
                    <i class="fa-solid fa-mobile-phone me-2"></i><?= gettext('Text') ?>
                </a>
            </div>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                function allPhonesCommaD() {
                    prompt("Press CTRL + C to copy all group members' phone numbers", "<?= $sPhoneLink ?>");
                };
            </script>
            <?php
        }

        ?>
      <a href="<?= SystemURLs::getRootPath() . "/DirectoryReports.php?cartdir=Cart+Directory"?>" class="btn btn-outline-warning" title="<?= gettext('Generate phone directory') ?>"><i
          class="fa-solid fa-book me-2"></i><?= gettext('Directory') ?></a>

      <script nonce="<?= SystemURLs::getCSPNonce() ?>" ><!--
                  function codename() {
        if (document.labelform.bulkmailpresort.checked) {
          document.labelform.bulkmailquiet.disabled = false;
        } else {
          document.labelform.bulkmailquiet.disabled = true;
          document.labelform.bulkmailquiet.checked = false;
        }
      }

      //-->
                </SCRIPT>
              </div>
            </div>
