<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

// Determine if user is logged in using AuthenticationManager
$isLoggedIn = AuthenticationManager::isUserLoggedIn();

if ($isLoggedIn) {
    $isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
}
?>

    <?php if ($isLoggedIn): ?>
        <!-- ======= LOGGED-IN USER FOOTER ======= -->
                    </div>
                </section><!-- /.content -->

            </div>
            <!-- /.content-wrapper -->
            <footer class="main-footer">
                <div class="float-right d-none d-sm-block">
                    <b><?= gettext('Version') ?></b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
                </div>
                <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved') ?>.
                | <a href="https://twitter.com/church_crm" target="_blank"><?= gettext("Follow us on") ?> <i class="fa-brands fa-x-twitter"></i> </a>
                | <span class="fi fi-squared"></span>
            </footer>

            <!-- The Right Sidebar -->
            <aside class="control-sidebar control-sidebar-dark">
                <div class="tab-content">
                    <div class="tab-pane active" id="control-sidebar-tasks-tab">
                        <?= gettext('You have') ?> <span class="badge badge-warning"><?= $taskSize ?></span> <?= gettext('task(s)') ?>
                        <br/><br/>
                        <?php foreach ($tasks as $task) { ?>
                            <!-- Task item -->
                            <div class="mb-1">
                                <a target="blank" href="<?= $task['link'] ?>">
                                    <i class="menu-icon fa-solid fa-fw <?= $task['admin'] ? 'fa-lock' : 'fa-info' ?>"></i> <?= $task['title'] ?>
                                </a>
                            </div>
                            <!-- end task item -->
                        <?php } ?>
                        <!-- /.control-sidebar-menu -->
                    </div>
                    <!-- /.tab-pane -->
                </div>
            </aside>

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

    <?php else: ?>
        <!-- ======= NOT LOGGED-IN (LOGIN PAGE) FOOTER ======= -->
        <div class="text-center" style="background-color: white; padding-top: 5px; padding-bottom: 5px; position: fixed; bottom: 0; width: 100%">
            <strong><?= gettext('Copyright') ?> &copy; <?= SystemService::getCopyrightDate() ?> <a href="https://churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
        </div>
    <?php endif; ?>

    <!-- ======= COMMON SCRIPTS (ALL USERS) ======= -->

    <!-- Bootstrap JS -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap/js/bootstrap<?php echo $isLoggedIn ? '.bundle' : ''; ?>.min.js"></script>

    <!-- AdminLTE App -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/adminlte/adminlte.min.js"></script>

    <!-- InputMask -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/jquery.inputmask.min.js"></script>
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/inputmask/inputmask.binding.js"></script>

    <!-- Datepicker -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>

    <!-- Select2 -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/select2/select2.full.min.js"></script>

    <!-- Bootbox -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootbox/bootbox.min.js"></script>

    <!-- i18next -->
    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/i18next/i18next.min.js"></script>
    <script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= Bootstrapper::getCurrentLocale()->getLocale() ?>.js"></script>

    <?php if ($isLoggedIn): ?>
        <!-- ======= LOGGED-IN USER SCRIPTS ======= -->

        <!-- Date Range Picker -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-daterangepicker/daterangepicker.js"></script>

        <!-- DataTables -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/datatables/pdfmake.min.js"></script>
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/datatables/vfs_fonts.js"></script>
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/datatables/datatables.min.js"></script>

        <!-- Chart.js -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/chartjs/chart.umd.js"></script>

        <!-- Bootstrap Notify -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-notify/bootstrap-notify.min.js"></script>

        <!-- FullCalendar -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/fullcalendar/index.global.min.js"></script>

        <!-- FastClick -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/fastclick/fastclick.js"></script>

        <!-- Bootstrap Toggle -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-toggle/bootstrap-toggle.js"></script>

        <!-- Bootstrap Validator -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-validator/validator.min.js"></script>

        <!-- Custom Scripts -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/js/IssueReporter.js"></script>
        <script src="<?= SystemURLs::getRootPath() ?>/skin/js/Footer.js"></script>

    <?php else: ?>
        <!-- ======= NOT LOGGED-IN (LOGIN PAGE) SCRIPTS ======= -->
        
        <!-- Bootstrap Show Password (login only) -->
        <script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-show-password/bootstrap-show-password.min.js"></script>

    <?php endif; ?>

    <!-- ======= i18next INITIALIZATION (ALL USERS) ======= -->
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        i18nextOpt = {
            lng: window.CRM.shortLocale || "en",
            nsSeparator: false,
            keySeparator: false,
            pluralSeparator: false,
            contextSeparator: false,
            fallbackLng: false,
            resources: {}
        };

        i18nextOpt.resources[window.CRM.shortLocale || "en"] = {
            translation: window.CRM.i18keys || {}
        };
        i18next.init(i18nextOpt);
    </script>

    <?php if (isset($sGlobalMessage)) { ?>
        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
            $("document").ready(function () {
                showGlobalMessage("<?= $sGlobalMessage ?>", "<?=$sGlobalMessageClass?>");
            });
        </script>
    <?php } ?>

    <?php 
    // Analytics tracking - exclude setup pages
    if ($_SERVER['SCRIPT_NAME'] != '/setup/index.php') {
        include_once('analyticstracking.php');
    }
    ?>

</body>
</html>

<?php
// Turn OFF output buffering
ob_end_flush();

// Reset the Global Message
$_SESSION['sGlobalMessage'] = '';
?>
