<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

?>
    <div style="background-color: white; padding-top: 5px; padding-bottom: 5px; text-align: center; position: fixed; bottom: 0; width: 100%">
      <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
    </div>


  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/select2/select2.full.min.js"></script>

  <!-- Bootstrap 3.3.5 -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap/js/bootstrap.min.js"></script>

  <!-- AdminLTE App -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/adminlte/adminlte.min.js"></script>

  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/fontawesome/js/all.js"></script>

  <!-- InputMask -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/jquery.inputmask.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/inputmask.binding.js"></script>

  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootbox/bootbox.min.js"></script>

  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/i18next/i18next.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-show-password/bootstrap-show-password.min.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= Bootstrapper::getCurrentLocale()->getLocale() ?>.js"></script>

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
