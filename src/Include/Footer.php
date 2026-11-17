<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\InputUtils;

$rootPath = InputUtils::escapeAttribute(SystemURLs::getRootPath());
?>
      </div><!-- /.container-xl -->
    </div><!-- /.page-body -->

    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl">
        <div class="row text-center align-items-center flex-row-reverse">
          <div class="col-lg-auto ms-lg-auto">
            <b><?= InputUtils::escapeHTML(gettext('Version')) ?></b> <?= InputUtils::escapeHTML((string) ($_SESSION['sSoftwareInstalledVersion'] ?? '')) ?>
            &nbsp;&nbsp;
            <?php $socialLinkSeparator = "\n&nbsp;\n"; require __DIR__ . '/partials/social-links.php'; ?>
          </div>
          <div class="col-12 col-lg-auto mt-3 mt-lg-0">
            <?php $copyrightAttribution = 'footer'; require __DIR__ . '/partials/copyright.php'; ?>
          </div>
        </div>
      </div>
    </footer>

  </div><!-- /.page-wrapper -->

  <!-- Floating Action Buttons -->
  <div class="fab-container" id="fab-container">
    <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()): ?>
    <a href="<?= $rootPath ?>/PersonEditor.php" class="fab-button fab-person">
      <span class="fab-label" id="fab-person-label"></span>
      <div class="fab-icon">
        <i class="fa-solid fa-person-half-dress"></i>
      </div>
    </a>
    <a href="<?= $rootPath ?>/FamilyEditor.php" class="fab-button fab-family">
      <span class="fab-label" id="fab-family-label"></span>
      <div class="fab-icon">
        <i class="fa-solid fa-people-roof"></i>
      </div>
    </a>
    <?php endif; ?>
  </div>

</div><!-- /.page -->

<?php if (!empty($sGlobalMessage)) { ?>
<div id="crm-global-message" hidden
     data-message="<?= InputUtils::escapeAttribute((string) $sGlobalMessage) ?>"
     data-type="<?= InputUtils::escapeAttribute((string) ($sGlobalMessageClass ?? 'info')) ?>"></div>
<?php } ?>

<?php require __DIR__ . '/partials/toast-container.php'; ?>

<?= PluginManager::getPluginFooterContent() ?>
</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();
