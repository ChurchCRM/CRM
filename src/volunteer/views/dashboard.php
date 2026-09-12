<?php

use ChurchCRM\dto\SystemURLs;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fa-solid fa-handshake-angle me-2"></i><?= gettext('Volunteer Management v2 is enabled') ?>
        </h3>
      </div>
      <div class="card-body">
        <p><?= gettext('This is the home of the new volunteer scheduling and assignment workflow.') ?></p>
        <p class="mb-0"><?= gettext('Ministries, teams, positions, qualifications, schedules and assignments arrive in later releases. Nothing is set up yet.') ?></p>
      </div>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
