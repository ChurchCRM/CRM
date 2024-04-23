<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

?>
<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title">Cart Functions</h3>
  </div>
  <div class="card-body">
    <a href="#" id="emptyCart" class="btn btn-app emptyCart"><i class="fa fa-trash"></i><?= gettext('Empty Cart') ?></a>
    <?php if (AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
        ?>
      <a id="emptyCartToGroup" class="btn btn-app"><i class="fa fa-users"></i><?= gettext('Empty Cart to Group') ?></a>
        <?php
    }
    if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
        ?>
      <a href="<?= SystemURLs::getRootPath() . "/CartToFamily.php"?>" class="btn btn-app"><i
          class="fa fa-people-roof"></i><?= gettext('Empty Cart to Family') ?></a>
    <?php }
    ?>
    <a href="<?= SystemURLs::getRootPath() . "/CartToEvent.php"?>" class="btn btn-app"><i
        class="fa fa-ticket-alt"></i><?= gettext('Empty Cart to Event') ?></a>

    <?php if (AuthenticationManager::getCurrentUser()->isCSVExport()) {
        ?>
      <a href="<?= SystemURLs::getRootPath() . "/CSVExport.php?Source=cart" ?>" class="btn btn-app"><i
          class="fa fa-file-csv"></i><?= gettext('CSV Export') ?></a>
    <?php }
    ?>
    <a href="<?= SystemURLs::getRootPath() . "/MapUsingGoogle.php?GroupID=0"?>" class="btn btn-app"><i
        class="fa fa-map-marker"></i><?= gettext('Map Cart') ?></a>
    <a href="<?= SystemURLs::getRootPath() . "/Reports/NameTags.php?labeltype=74536&labelfont=times&labelfontsize=36"?>" class="btn btn-app"><i
        class="fa fa-file-pdf"></i><?= gettext('Name Tags') ?></a>
      <?php

        if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
            // Display link
            echo "<a href='mailto:" . $sEmailLink . "' class='btn btn-app'><i class='fa fa-paper-plane'></i>" . gettext('Email Cart') . '</a>';
            echo "<a href='mailto:?bcc=" . $sEmailLink . "' class='btn btn-app'><i class='fa-regular fa-paper-plane'></i>" . gettext('Email (BCC)') . '</a>';

            // Display link
            echo '<a href="javascript:void(0)" onclick="allPhonesCommaD()" class="btn btn-app"><i class="fa fa-mobile-phone"></i>' . gettext("Text Cart");
            echo '<script nonce="' . SystemURLs::getCSPNonce() . '">function allPhonesCommaD() {prompt("Press CTRL + C to copy all group members\' phone numbers", "' . $sPhoneLink . '")};</script>';
        }

        ?>
      <a href="<?= SystemURLs::getRootPath() . "/DirectoryReports.php?cartdir=Cart+Directory"?>" class="btn btn-app"><i
          class="fa fa-book"></i><?= gettext('Create Directory From Cart') ?></a>

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
