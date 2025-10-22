<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

?>
    <div style="background-color: white; padding-top: 5px; padding-bottom: 5px; text-align: center; position: fixed; bottom: 0; width: 100%">
      <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
    </div>

  <!-- Locale/i18n strings (must load before i18next initialization script) -->
  <script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= Bootstrapper::getCurrentLocale()->getLocale() ?>.js" defer></script>

  <script nonce="<?= SystemURLs::getCSPNonce() ?>" defer>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
  </script>
  <?php

    //If this is a first-run setup, do not include google analytics code.
    if ($_SERVER['SCRIPT_NAME'] != '/setup/index.php') {
        include_once('analyticstracking.php');
    }
    ?>
</body>
</html>
