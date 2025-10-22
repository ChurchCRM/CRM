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
                            <i class="menu-icon fa fa-fw <?= $task['admin'] ? 'fa-lock' : 'fa-info' ?>"></i> <?= $task['title'] ?>
                        </a>
                    </div>
                    <!-- end task item -->
                <?php } ?>
            <!-- /.control-sidebar-menu -->
        </div>
        <!-- /.tab-pane -->
    </div>
</aside>
<!-- ./wrapper -->
</div><!-- ./wrapper -->

<!-- Locale/i18n strings (must load before custom scripts which use i18next) -->
<script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= Bootstrapper::getCurrentLocale()->getLocale() ?>.js" defer></script>

<!-- Custom ChurchCRM scripts (load after webpack bundles and locale with defer) -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/IssueReporter.js" defer></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/DataTables.js" defer></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Footer.js" defer></script>
<?php if (isset($sGlobalMessage)) {
    ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        document.addEventListener('DOMContentLoaded', function() {
            $("document").ready(function () {
                showGlobalMessage("<?= $sGlobalMessage ?>", "<?=$sGlobalMessageClass?>");
            });
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
$_SESSION['sGlobalMessage'] = '';
