<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

?>

<style>
  .auth-footer {
    margin-top: auto;
    padding: 40px 20px 20px;
    text-align: center;
    color: rgba(255, 255, 255, 0.8);
    font-size: 13px;
  }

  .auth-footer a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: color 0.2s;
  }

  .auth-footer a:hover {
    color: white;
    text-decoration: underline;
  }

  .auth-footer-social {
    margin-top: 15px;
  }

  .auth-footer-social a {
    display: inline-block;
    margin: 0 8px;
    width: 32px;
    height: 32px;
    line-height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    transition: all 0.2s;
  }

  .auth-footer-social a:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
  }

  .auth-footer-social i {
    font-size: 14px;
  }
</style>

<div class="auth-footer">
  <div>
    <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> 
    <a href="https://churchcrm.io" target="_blank" rel="noopener noreferrer"><b>Church</b>CRM</a></strong>. 
    <?= gettext('All rights reserved') ?>.
  </div>
  <div class="auth-footer-social">
    <a href="https://www.facebook.com/getChurchCRM" target="_blank" rel="noopener noreferrer" title="Facebook">
      <i class="fa-brands fa-facebook"></i>
    </a>
    <a href="https://www.instagram.com/getchurchcrm/" target="_blank" rel="noopener noreferrer" title="Instagram">
      <i class="fa-brands fa-instagram"></i>
    </a>
    <a href="https://x.com/getChurchCRM" target="_blank" rel="noopener noreferrer" title="X">
      <i class="fa-brands fa-x-twitter"></i>
    </a>
    <a href="https://www.linkedin.com/company/getchurchcrm/" target="_blank" rel="noopener noreferrer" title="LinkedIn">
      <i class="fa-brands fa-linkedin"></i>
    </a>
    <a href="https://www.youtube.com/@getChurchCRM" target="_blank" rel="noopener noreferrer" title="YouTube">
      <i class="fa-brands fa-youtube"></i>
    </a>
  </div>
</div>

  <script src="<?= SystemURLs::assetVersioned('/skin/external/select2/select2.full.min.js') ?>"></script>

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
