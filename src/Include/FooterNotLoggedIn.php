<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

?>
    <div class="text-center" style="background-color: white; padding-top: 5px; padding-bottom: 5px; position: fixed; bottom: 0; width: 100%">
      <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank" rel="noopener noreferrer"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
      <div class="mt-1">
        <a href="https://www.facebook.com/getChurchCRM" target="_blank" rel="noopener noreferrer">Facebook <i class="fa-brands fa-facebook"></i></a>
        &nbsp;|&nbsp;
        <a href="https://www.instagram.com/getchurchcrm/" target="_blank" rel="noopener noreferrer">Instagram <i class="fa-brands fa-instagram"></i></a>
        &nbsp;|&nbsp;
        <a href="https://x.com/getChurchCRM" target="_blank" rel="noopener noreferrer">X <i class="fa-brands fa-x-twitter"></i></a>
        &nbsp;|&nbsp;
        <a href="https://www.linkedin.com/company/getchurchcrm/" target="_blank" rel="noopener noreferrer">LinkedIn <i class="fa-brands fa-linkedin"></i></a>
        &nbsp;|&nbsp;
        <a href="https://www.youtube.com/@getChurchCRM" target="_blank" rel="noopener noreferrer">YouTube <i class="fa-brands fa-youtube"></i></a>
      </div>
    </div>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/select2/select2.full.min.js') ?>"></script>

  <!-- Bootstrap 3.3.5 -->
  <script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap/js/bootstrap.min.js') ?>"></script>

  <!-- AdminLTE App -->
  <script src="<?= SystemURLs::assetVersioned('/skin/external/adminlte/adminlte.min.js') ?>"></script>

  <!-- InputMask -->
  <script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/jquery.inputmask.min.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/inputmask.binding.js') ?>"></script>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/external/bootbox/bootbox.min.js') ?>"></script>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/i18next/i18next.min.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/external/just-validate/just-validate.production.min.js') ?>"></script>

  <script src="<?= SystemURLs::assetVersioned('/skin/v2/locale-loader.min.js') ?>"></script>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Load locale files dynamically
    (function() {
        const localeConfig = <?= json_encode(Bootstrapper::getCurrentLocale()->getLocaleConfigArray()) ?>;
        if (window.CRM && window.CRM.loadLocaleFiles) {
            window.CRM.loadLocaleFiles(localeConfig);
        }
    })();
  </script>
  <?php

    //If this is a first-run setup, do not include google analytics code.
    if ($_SERVER['SCRIPT_NAME'] != '/setup/index.php') {
        include_once('analyticstracking.php');
    }
    ?>
</body>
</html>
