<?php
/*******************************************************************************
 *
 *  filename    : Include/Footer.php
 *  last change : 2002-04-22
 *  description : footer that appear on the bottom of all pages
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker, Philippe Logel
  *
 ******************************************************************************/

use ChurchCRM\dto\SystemURLs;

$isAdmin = $_SESSION['user']->isAdmin();

?>
</section><!-- /.content -->

</div>
<!-- /.content-wrapper -->
<footer class="main-footer">
    <div class="pull-right">
        <b><?= gettext('Version') ?></b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
    </div>
    <strong><?= gettext('Copyright') ?> &copy; 2015-2017 <a href="http://www.churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved') ?>
    .
</footer>

<!-- The Right Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
        <li class="active">
            <a href="#control-sidebar-tasks-tab" data-toggle="tab" aria-expanded="true">
                <i class="fa fa-tasks"></i>
            </a>
        </li>
        <li>
            <a href="#control-sidebar-settings-tab" data-toggle="tab" aria-expanded="false">
                <i class="fa fa-wrench"></i>
            </a>
        </li>
        <li>
            <a href="#control-sidebar-settings-other-tab" data-toggle="tab" aria-expanded="false">
                <i class="fa fa-sliders"></i>
            </a>
        </li>

    </ul>
    <div class="tab-content">
        <!-- Home tab content -->
        <div class="tab-pane" id="control-sidebar-settings-other-tab">
            <h4 class="control-sidebar-heading"><i class="fa fa-cogs"></i> <?= _('Family') ?></h4>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/OptionManager.php?mode=famroles">
                        <i class="fa fa-cog"></i> <?= _('Family Roles') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/PropertyList.php?Type=f">
                        <i class="fa fa-cog"></i> <?= _('Family Properties') ?>
                    </a>
                </li>
                <?php if ($isAdmin) {
    ?>
                    <li>
                        <a href="<?= SystemURLs::getRootPath() ?>/FamilyCustomFieldsEditor.php">
                            <i class="fa fa-cog"></i> <?= _('Edit Custom Family Fields') ?>
                        </a>
                    </li>
                    <?php
} ?>
            </ul>
            <br/>
            <h4 class="control-sidebar-heading"><i class="fa fa-cogs"></i> <?= _('Person') ?></h4>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/OptionManager.php?mode=classes">
                        <i class="fa fa-cog"></i> <?= _('Classifications Manager') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/PropertyList.php?Type=p">
                        <i class="fa fa-cog"></i> <?= _('People Properties') ?>
                    </a>
                </li>
                <?php if ($isAdmin) {
        ?>
                    <li>
                        <a href="<?= SystemURLs::getRootPath() ?>/PersonCustomFieldsEditor.php">
                            <i class="fa fa-cog"></i> <?= _('Edit Custom Person Fields') ?>
                        </a>
                    </li>
                    <?php
    } ?>
            </ul>
            <br/>
            <h4 class="control-sidebar-heading"><i class="fa fa-cogs"></i> <?= _('Group') ?></h4>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/PropertyList.php?Type=g">
                        <i class="fa fa-cog"></i> <?= _('Group Properties') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/OptionManager.php?mode=grptypes">
                        <i class="fa fa-cog"></i> <?= _('Edit Group Types') ?>
                    </a>
                </li>
            </ul>
            <br/>
            <h4 class="control-sidebar-heading"><i class="fa fa-cogs"></i> <?= _('Other') ?></h4>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="<?= SystemURLs::getRootPath() ?>/PropertyTypeList.php">
                        <i class="fa fa-cog"></i> <?= _('Property Types') ?>
                    </a>
                </li>
                <?php if ($isAdmin) {
        ?>
                    <li>
                        <a href="<?= SystemURLs::getRootPath() ?>/VolunteerOpportunityEditor.php">
                            <i class="fa fa-cog"></i> <?= _('Volunteer Opportunities') ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?= SystemURLs::getRootPath() ?>/DonationFundEditor.php">
                            <i class="fa fa-cog"></i> <?= _('Edit Donation Funds') ?>
                        </a>
                    </li>
                    <?php
    } ?>
            </ul>
            <!-- /.control-sidebar-menu -->

        </div>
        <div id="control-sidebar-settings-tab" class="tab-pane">
            <div><h4 class="control-sidebar-heading"><?= gettext('System Settings') ?></h4>
                <?php if ($isAdmin) {
        ?>
                    <ul class="control-sidebar-menu">
                        <li>
                            <a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php">
                                <i class="menu-icon fa fa-gears bg-red"></i>
                                <div class="menu-info">
                                    <h4 class="control-sidebar-subheading"><?= _('Edit General Settings') ?></h4>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="<?= SystemURLs::getRootPath() ?>/UserList.php">
                                <i class="menu-icon fa fa-user-secret bg-gray"></i>
                                <div class="menu-info">
                                    <h4 class="control-sidebar-subheading"><?= _('System Users') ?></h4>
                                </div>
                            </a>
                        </li>
                    </ul>
                    <hr/>
                    <?php
    } ?>
                <ul class="control-sidebar-menu">
                    <?php if ($isAdmin) {
        ?>
                        <li>
                            <a href="<?= SystemURLs::getRootPath() ?>/RestoreDatabase.php">
                                <i class="menu-icon fa fa-database bg-yellow-gradient"></i>
                                <div class="menu-info">
                                    <h4 class="control-sidebar-subheading"><?= _('Restore Database') ?></h4>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="<?= SystemURLs::getRootPath() ?>/BackupDatabase.php">
                                <i class="menu-icon fa fa-database bg-green"></i>
                                <div class="menu-info">
                                    <h4 class="control-sidebar-subheading"><?= _('Backup Database') ?></h4>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="<?= SystemURLs::getRootPath() ?>/CSVImport.php">
                                <i class="menu-icon fa fa-upload bg-yellow-gradient"></i>
                                <div class="menu-info">
                                    <h4 class="control-sidebar-subheading"><?= _('CSV Import') ?></h4>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="<?= SystemURLs::getRootPath() ?>/KioskManager.php">
                                <i class="menu-icon fa fa-laptop bg-blue-gradient"></i>
                                <div class="menu-info">
                                    <h4 class="control-sidebar-subheading"><?= _('Kiosk Manager') ?></h4>
                                </div>
                            </a>
                        </li>
                        <?php
    } else {
        echo _('Please contact your admin to change the system settings.');
    } ?>
                    <li>
                        <a href="<?= SystemURLs::getRootPath() ?>/CSVExport.php">
                            <i class="menu-icon fa fa-download bg-green"></i>
                            <div class="menu-info">
                                <h4 class="control-sidebar-subheading"><?= _('CSV Export Records') ?></h4>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- /.tab-pane -->

        <!-- Settings tab content -->
        <div class="tab-pane active" id="control-sidebar-tasks-tab">
            <h3 class="control-sidebar-heading"><?= gettext('Open Tasks') ?></h3>
            <?= gettext('You have') ?> &nbsp; <span class="label label-danger"><?= $taskSize ?></span>
            &nbsp; <?= gettext('task(s)') ?>
            <br/><br/>
            <ul class="control-sidebar-menu">
                <?php foreach ($tasks as $task) {
        $taskIcon = 'fa-info bg-green';
        if ($task['admin']) {
            $taskIcon = 'fa-lock bg-yellow-gradient';
        } ?>
                    <!-- Task item -->
                    <li>
                        <a href="<?= $task['link'] ?>">
                            <i class="menu-icon fa fa-fw <?= $taskIcon ?>"></i>
                            <div class="menu-info">
                                <h4 class="control-sidebar-subheading"
                                    title="<?= $task['desc'] ?>"><?= $task['title'] ?></h4>
                            </div>
                        </a>

                    </li>
                    <!-- end task item -->
                    <?php
    } ?>
            </ul>
            <!-- /.control-sidebar-menu -->

        </div>
        <!-- /.tab-pane -->
    </div>
</aside>
<!-- The sidebar's background -->
<!-- This div must placed right after the sidebar for it to work-->
<div class="control-sidebar-bg"></div>
</div>
<!-- ./wrapper -->
</div><!-- ./wrapper -->

<!-- Bootstrap 3.3.5 -->


<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/bootstrap/js/bootstrap.min.js"></script>
<!-- SlimScroll -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/slimScroll/jquery.slimscroll.min.js"></script>
<!-- AdminLTE App -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/dist/js/app.min.js"></script>

<!-- InputMask -->
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.date.extensions.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.extensions.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/daterangepicker/daterangepicker.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/timepicker/bootstrap-timepicker.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/Responsive/js/dataTables.responsive.min.js" ></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/Select/dataTables.select.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/chartjs/Chart.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/pace/pace.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/select2/select2.full.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/fullcalendar/fullcalendar.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootbox/bootbox.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/fastclick/fastclick.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-toggle/bootstrap-toggle.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/i18next/i18next.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/locale/js/<?= $localeInfo->getLocale() ?>.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bootstrap-validator/validator.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/IssueReporter.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/DataTables.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Tooltips.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Events.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Footer.js"></script>

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
$_SESSION['sGlobalMessage'] = '';

?>
