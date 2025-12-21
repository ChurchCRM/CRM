<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
?>
</div>
</section><!-- /.content -->

</div>
<!-- /.content-wrapper -->
<footer class="main-footer">
    <div class="float-right d-none d-sm-block">
        <b><?= gettext('Version') ?></b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
    </div>
    <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved') ?>.
    | <a href="https://twitter.com/church_crm" target="_blank"><?= gettext("Follow us on") ?> <i class="fa-brands fa-x-twitter"></i></a>
</footer>

<!-- Floating Action Buttons -->
<div class="fab-container" id="fab-container">
    <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php" class="fab-button fab-person">
        <span class="fab-label" id="fab-person-label"></span>
        <div class="fab-icon">
            <i class="fa-solid fa-user"></i>
        </div>
    </a>
    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php" class="fab-button fab-family">
        <span class="fab-label" id="fab-family-label"></span>
        <div class="fab-icon">
            <i class="fa-solid fa-users"></i>
        </div>
    </a>
</div>

<!-- ./wrapper -->
</div><!-- ./wrapper -->

<!-- Bootstrap 3.3.5 -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<!-- AdminLTE App -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/adminlte/adminlte.min.js') ?>"></script>

<!-- InputMask -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/jquery.inputmask.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/inputmask/inputmask.binding.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-daterangepicker/daterangepicker.js') ?>"></script>

<!-- DataTables: Core library and Bootstrap 4 integration -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/jquery.dataTables.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.bootstrap4.min.js') ?>"></script>
<!-- DataTables: Extensions -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.buttons.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/buttons.bootstrap4.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/buttons.html5.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/buttons.print.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.responsive.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/responsive.bootstrap4.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/dataTables.select.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/select.bootstrap4.min.js') ?>"></script>
<!-- PDF and Excel export dependencies -->
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/jszip.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/pdfmake.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/datatables/vfs_fonts.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/chartjs/chart.umd.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/select2/select2.full.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/external/fullcalendar/index.global.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/bootbox/bootbox.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/bootstrap-toggle/bootstrap-toggle.js') ?>"></script>
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
<?php if (isset($sGlobalMessage)) {
    ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $("document").ready(function () {
            showGlobalMessage("<?= $sGlobalMessage ?>", "<?=$sGlobalMessageClass?>");
        });
    </script>
    <?php
} ?>

<?php  include_once('analyticstracking.php'); ?>
</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();

// Reset the Global Message
unset($_SESSION['sGlobalMessage']);
unset($_SESSION['sGlobalMessageClass']);
