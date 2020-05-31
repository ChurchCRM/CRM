<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Bootstrapper;

?>
    <div style="background-color: white; padding-top: 5px; padding-bottom: 5px; text-align: center; position: fixed; bottom: 0; width: 100%">
      <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="http://www.churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
    </div>


  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/select2/select2.min.js"></script>

  <!-- Bootstrap 3.3.5 -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap/bootstrap.min.js"></script>

  <!-- AdminLTE App -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/adminlte/adminlte.min.js"></script>

  <!-- InputMask -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/jquery.inputmask.bundle.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/inputmask.date.extensions.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/inputmask.extensions.min.js" ></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js" ></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootbox/bootbox.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/i18next/i18next.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= Bootstrapper::GetCurrentLocale()->getLocale() ?>.js"></script>

  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    i18nextOpt = {
      lng:window.CRM.shortLocale,
      nsSeparator: false,
      keySeparator: false,
      pluralSeparator:false,
      contextSeparator:false,
      fallbackLng: false,
      resources: { }
    };

    i18nextOpt.resources[window.CRM.shortLocale] = {
      translation: window.CRM.i18keys
    };
    i18next.init(i18nextOpt);
  </script>
  <?php

    //If this is a first-run setup, do not include google analytics code.
    if ($_SERVER['SCRIPT_NAME'] != '/setup/index.php') {
        include_once('analyticstracking.php');
    }
 ?>
</body>
</html>
