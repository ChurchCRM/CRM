<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\SystemService;

use ChurchCRM\Utils\InputUtils;
$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
?>
      </div><!-- /.container-xl -->
    </div><!-- /.page-body -->

    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl">
        <div class="row text-center align-items-center flex-row-reverse">
          <div class="col-lg-auto ms-lg-auto">
            <b><?= gettext('Version') ?></b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
            &nbsp;&nbsp;
            <a href="https://www.facebook.com/getChurchCRM" target="_blank" rel="noopener noreferrer" title="Facebook">
              <i class="fa-brands fa-facebook"></i>
            </a>
            &nbsp;
            <a href="https://www.instagram.com/getchurchcrm/" target="_blank" rel="noopener noreferrer" title="Instagram">
              <i class="fa-brands fa-instagram"></i>
            </a>
            &nbsp;
            <a href="https://x.com/getChurchCRM" target="_blank" rel="noopener noreferrer" title="X">
              <i class="fa-brands fa-x-twitter"></i>
            </a>
            &nbsp;
            <a href="https://www.linkedin.com/company/getchurchcrm/" target="_blank" rel="noopener noreferrer" title="LinkedIn">
              <i class="fa-brands fa-linkedin"></i>
            </a>
            &nbsp;
            <a href="https://www.youtube.com/@getChurchCRM" target="_blank" rel="noopener noreferrer" title="YouTube">
              <i class="fa-brands fa-youtube"></i>
            </a>
          </div>
          <div class="col-12 col-lg-auto mt-3 mt-lg-0">
            <?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?>
            <a href="<?= InputUtils::escapeAttribute(SystemURLs::attributed('https://churchcrm.io', 'footer')) ?>" target="_blank" rel="noopener noreferrer">Church<b>CRM</b></a>.
            <?= gettext('All rights reserved') ?>.
          </div>
        </div>
      </div>
    </footer>

  </div><!-- /.page-wrapper -->

  <!-- Floating Action Buttons -->
  <div class="fab-container" id="fab-container">
    <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()): ?>
    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php" class="fab-button fab-person">
      <span class="fab-label" id="fab-person-label"></span>
      <div class="fab-icon">
        <i class="fa-solid fa-person-half-dress"></i>
      </div>
    </a>
    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php" class="fab-button fab-family">
      <span class="fab-label" id="fab-family-label"></span>
      <div class="fab-icon">
        <i class="fa-solid fa-people-roof"></i>
      </div>
    </a>
    <?php endif; ?>
    <button class="fab-menu-toggle d-xl-none" id="fab-menu-toggle" type="button"
            data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false"
            aria-label="<?= gettext('Toggle navigation') ?>">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>

</div><!-- /.page -->

<?php if (isset($sGlobalMessage) && !empty($sGlobalMessage)) { ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $("document").ready(function () {
            showGlobalMessage(<?= InputUtils::jsonEncodeForScript($sGlobalMessage) ?>, <?= InputUtils::jsonEncodeForScript($sGlobalMessageClass) ?>);
        });
    </script>
<?php } ?>

<?= PluginManager::getPluginFooterContent() ?>
</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();
