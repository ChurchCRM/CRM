<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\SystemService;

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
            <a href="https://churchcrm.io" target="_blank" rel="noopener noreferrer"><b>Church</b>CRM</a>.
            <?= gettext('All rights reserved') ?>.
          </div>
        </div>
      </div>
    </footer>

  </div><!-- /.page-wrapper -->

  <!-- Floating Action Buttons -->
  <div class="fab-container" id="fab-container">
    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php" class="fab-button fab-person">
      <span class="fab-label" id="fab-person-label"></span>
      <div class="fab-icon">
        <i class="fa-duotone fa-solid fa-user"></i>
      </div>
    </a>
    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php" class="fab-button fab-family">
      <span class="fab-label" id="fab-family-label"></span>
      <div class="fab-icon">
        <i class="fa-duotone fa-solid fa-house-user"></i>
      </div>
    </a>
  </div>

</div><!-- /.page -->

<!-- InputMask -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/jquery.inputmask.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/inputmask.binding.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-daterangepicker/daterangepicker.js') ?>"></script>

<!-- DataTables: Core library and Bootstrap 5 integration -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.bootstrap5.min.js') ?>"></script>
<!-- DataTables: Extensions -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.buttons.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/buttons.bootstrap5.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/buttons.html5.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/buttons.print.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.responsive.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/responsive.bootstrap5.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.select.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/select.bootstrap5.min.js') ?>"></script>
<!-- PDF and Excel export dependencies -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/jszip.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/pdfmake.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/vfs_fonts.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/chartjs/chart.umd.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/select2/select2.full.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/fullcalendar/index.global.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/bootbox/bootbox.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/i18next/i18next.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/just-validate/just-validate.production.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/js/IssueReporter.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/Footer.js') ?>"></script>
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

<!-- Fullscreen toggle -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    document.getElementById('fullscreenToggle')?.addEventListener('click', function (e) {
        e.preventDefault();
        var icon = this.querySelector('i');
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
            if (icon) { icon.className = 'ti ti-minimize'; }
        } else {
            document.exitFullscreen();
            if (icon) { icon.className = 'ti ti-maximize'; }
        }
    });
</script>

<?php if (isset($sGlobalMessage) && !empty($sGlobalMessage)) { ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $("document").ready(function () {
            showGlobalMessage("<?= $sGlobalMessage ?>", "<?= $sGlobalMessageClass ?>");
        });
    </script>
<?php } ?>

<?= PluginManager::getPluginFooterContent() ?>
</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();
