<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

?>
<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title">Cart Functions</h3>
  </div>
  <div class="card-body">
    <a href="#" id="emptyCart" class="btn btn-app bg-danger emptyCart"><i class="fa-solid fa-trash fa-3x"></i><br><?= gettext('Empty Cart') ?></a>
    <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
        ?>
      <a id="emptyCartToGroup" class="btn btn-app bg-primary"><i class="fa-solid fa-users fa-3x"></i><br><?= gettext('Empty Cart to Group') ?></a>
        <?php
    }
    if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
        ?>
      <a href="<?= SystemURLs::getRootPath() . "/CartToFamily.php"?>" class="btn btn-app bg-success"><i
          class="fa-solid fa-people-roof fa-3x"></i><br><?= gettext('Empty Cart to Family') ?></a>
    <?php }
    ?>
    <a href="<?= SystemURLs::getRootPath() . "/CartToEvent.php"?>" class="btn btn-app bg-info"><i
        class="fa-solid fa-ticket-alt fa-3x"></i><br><?= gettext('Check In to Event') ?></a>

    <?php if (AuthenticationManager::getCurrentUser()->isCSVExport()) {
        ?>
      <a href="<?= SystemURLs::getRootPath() . "/CSVExport.php?Source=cart" ?>" class="btn btn-app bg-warning"><i
          class="fa-solid fa-file-csv fa-3x"></i><br><?= gettext('CSV Export') ?></a>
    <?php }
    ?>
    <a href="<?= SystemURLs::getRootPath() . "/MapUsingGoogle.php?GroupID=0"?>" class="btn btn-app bg-purple"><i
        class="fa-solid fa-map-marker fa-3x"></i><br><?= gettext('Map Cart') ?></a>
    <a href="<?= SystemURLs::getRootPath() . "/Reports/NameTags.php?labeltype=74536&labelfont=times&labelfontsize=36"?>" class="btn btn-app bg-primary"><i
        class="fa-solid fa-file-pdf fa-3x"></i><br><?= gettext('Name Tags') ?></a>
      <?php

        if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
            // Display link
            ?>
            <a href="mailto:<?= $sEmailLink ?>" class="btn btn-app bg-info">
                <i class="fa-solid fa-paper-plane fa-3x"></i><br>
                <?= gettext('Email Cart') ?>
            </a>
            <a href="mailto:?bcc=<?= $sEmailLink ?>" class="btn btn-app bg-secondary">
                <i class="fa-solid fa-user-secret fa-3x"></i><br>
                <?= gettext('Email (BCC)') ?>
            </a>
            <a href="javascript:void(0)" onclick="allPhonesCommaD()" class="btn btn-app bg-success">
                <i class="fa-solid fa-mobile-phone fa-3x"></i><br>
                <?= gettext("Text Cart") ?>
            </a>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                function allPhonesCommaD() {
                    prompt("Press CTRL + C to copy all group members' phone numbers", "<?= $sPhoneLink ?>");
                };
            </script>
            <?php
        }

        ?>
      <a href="<?= SystemURLs::getRootPath() . "/DirectoryReports.php?cartdir=Cart+Directory"?>" class="btn btn-app bg-orange"><i
          class="fa-solid fa-book fa-3x"></i><br><?= gettext('Create Directory From Cart') ?></a>

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
              <!-- /.box-body -->
            </div>
