<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Authentication Issue");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>
  <div class="register-logo" style=" margin: 72px auto 0;">
    <a href="<?= SystemURLs::getRootPath() ?>/"><b>Church</b>CRM</a>
  </div>

  <div class="error-page">
    <h2 class="headline text-yellow">404</h2>
    <div class="error-content">
      <h3><i class="fa fa-warning text-yellow"></i><?= gettext("Authentication Issue") ?></h3>
      <p/>
      <h4><?= $message ?></h4>
    </div>
    <!-- /.error-content -->
  </div>
  <!-- /.error-page -->

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
