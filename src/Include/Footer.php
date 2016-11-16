<?php
/*******************************************************************************
 *
 *  filename    : Include/Footer.php
 *  last change : 2002-04-22
 *  description : footer that appear on the bottom of all pages
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/
?>
</section><!-- /.content -->

</div>
<!-- /.content-wrapper -->
<footer class="main-footer">
  <div class="pull-right">
    <b><?= gettext("Version") ?></b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
  </div>
  <strong><?= gettext("Copyright") ?> &copy; 2015-2016 <a href="http://www.churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext("All rights reserved") ?>
  .
</footer>

<!-- The Right Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
  <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
    <li class="active">
      <a href="#control-sidebar-tasks-tab" data-toggle="tab" aria-expanded="true">
        <i class="fa fa-flag"></i>
      </a>
    </li>
    <li>
      <a href="#control-sidebar-settings-tab" data-toggle="tab" aria-expanded="false">
        <i class="fa fa-wrench"></i>
      </a>
    </li>
    <li>
      <a href="#control-sidebar-home-tab" data-toggle="tab" aria-expanded="false">
        <i class="fa fa-home"></i>
      </a>
    </li>

  </ul>
  <div class="tab-content">
    <!-- Home tab content -->
    <div class="tab-pane" id="control-sidebar-home-tab">
      <h3 class="control-sidebar-heading">Recent Activity</h3>
      <ul class="control-sidebar-menu">
        <li>
          <a href="javascript:void(0)">
            <i class="menu-icon fa fa-birthday-cake bg-red"></i>

            <div class="menu-info">
              <h4 class="control-sidebar-subheading">Langdon's Birthday</h4>

              <p>Will be 23 on April 24th</p>
            </div>
          </a>
        </li>
        <li>
          <a href="javascript:void(0)">
            <i class="menu-icon fa fa-user bg-yellow"></i>

            <div class="menu-info">
              <h4 class="control-sidebar-subheading">Frodo Updated His Profile</h4>

              <p>New phone +1(800)555-1234</p>
            </div>
          </a>
        </li>
        <li>
          <a href="javascript:void(0)">
            <i class="menu-icon fa fa-envelope-o bg-light-blue"></i>

            <div class="menu-info">
              <h4 class="control-sidebar-subheading">Nora Joined Mailing List</h4>

              <p>nora@example.com</p>
            </div>
          </a>
        </li>
        <li>
          <a href="javascript:void(0)">
            <i class="menu-icon fa fa-file-code-o bg-green"></i>

            <div class="menu-info">
              <h4 class="control-sidebar-subheading">Cron Job 254 Executed</h4>

              <p>Execution time 5 seconds</p>
            </div>
          </a>
        </li>
      </ul>
      <!-- /.control-sidebar-menu -->

      <h3 class="control-sidebar-heading">Tasks Progress</h3>
      <ul class="control-sidebar-menu">
        <li>
          <a href="javascript:void(0)">
            <h4 class="control-sidebar-subheading">
              Custom Template Design
              <span class="label label-danger pull-right">70%</span>
            </h4>

            <div class="progress progress-xxs">
              <div class="progress-bar progress-bar-danger" style="width: 70%"></div>
            </div>
          </a>
        </li>
        <li>
          <a href="javascript:void(0)">
            <h4 class="control-sidebar-subheading">
              Update Resume
              <span class="label label-success pull-right">95%</span>
            </h4>

            <div class="progress progress-xxs">
              <div class="progress-bar progress-bar-success" style="width: 95%"></div>
            </div>
          </a>
        </li>
        <li>
          <a href="javascript:void(0)">
            <h4 class="control-sidebar-subheading">
              Laravel Integration
              <span class="label label-warning pull-right">50%</span>
            </h4>

            <div class="progress progress-xxs">
              <div class="progress-bar progress-bar-warning" style="width: 50%"></div>
            </div>
          </a>
        </li>
        <li>
          <a href="javascript:void(0)">
            <h4 class="control-sidebar-subheading">
              Back End Framework
              <span class="label label-primary pull-right">68%</span>
            </h4>

            <div class="progress progress-xxs">
              <div class="progress-bar progress-bar-primary" style="width: 68%"></div>
            </div>
          </a>
        </li>
      </ul>
      <!-- /.control-sidebar-menu -->

    </div>
    <div id="control-sidebar-settings-tab" class="tab-pane">
      <?php if ($_SESSION['user']->isAdmin()) { ?>
        <div><h4 class="control-sidebar-heading"><?= gettext("System Settings") ?></h4>
          <ul class="control-sidebar-menu">
            <li>
              <a href="<?= $sRootPath ?>/SystemSettings.php">
                <i class="menu-icon fa fa-gears bg-red"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _("Edit General Settings") ?></h4>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= $sRootPath ?>/BackupDatabase.php">
                <i class="menu-icon fa fa-database bg-green"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _("Backup Database") ?></h4>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= $sRootPath ?>/RestoreDatabase.php">
                <i class="menu-icon fa fa-database bg-yellow-gradient"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _("Restore Database") ?></h4>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= $sRootPath ?>/UserList.php">
                <i class="menu-icon fa fa-users bg-gray"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _("System Users") ?></h4>
                </div>
              </a>
            </li>
          </ul>
          <hr/>
          <?php addMenu("admin"); ?>
        </div>
      <?php } ?>
    </div>
    <!-- /.tab-pane -->

    <!-- Settings tab content -->
    <div class="tab-pane active" id="control-sidebar-tasks-tab">
      <h3 class="control-sidebar-heading"><?= gettext("Open Tasks") ?></h3>
      <?= gettext("You have") ?> &nbsp; <span class="label label-danger"><?= $taskSize ?></span>
      &nbsp; <?= gettext("task(s)") ?>
      <br/><br/>
      <?php foreach ($tasks as $task) {
        $taskIcon = "fa-info";
        if ($task["admin"]) {
          $taskIcon = "fa-lock";
        } ?>
        <!-- Task item -->
        <i class="fa fa-fw <?= $taskIcon ?>"></i>
        <a href="<?= $task["link"] ?>">
          <?= $task["title"] ?>
        </a>
        <br/>
        <!-- end task item -->
      <?php } ?>
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

<script src="<?= $sRootPath ?>/skin/adminlte/bootstrap/js/bootstrap.min.js"></script>
<!-- SlimScroll -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/slimScroll/jquery.slimscroll.min.js"></script>
<!-- FastClick -->
<script src="<?= $sRootPath ?>/skin/fastclick/fastclick.js"></script>
<!-- AdminLTE App -->
<script src="<?= $sRootPath ?>/skin/adminlte/dist/js/app.min.js"></script>

<!-- InputMask -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.js" type="text/javascript"></script>
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.date.extensions.js"
        type="text/javascript"></script>
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.extensions.js"
        type="text/javascript"></script>
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/datepicker/bootstrap-datepicker.js"
        type="text/javascript"></script>


<script src="<?= $sRootPath ?>/skin/js/DataTables.js"></script>
<script src="<?= $sRootPath ?>/skin/js/Tooltips.js"></script>
<script src="<?= $sRootPath ?>/skin/js/Events.js"></script>
<script src="<?= $sRootPath ?>/skin/js/Footer.js"></script>

<script src="<?= $sRootPath ?>/skin/locale/<?= $localeInfo->getLocale() ?>.js"></script>

<?php if ($sGlobalMessage) { ?>
  <script>
    $("document").ready(function () {
      showGlobalMessage("<?= $sGlobalMessage ?>", "<?=$sGlobalMessageClass?>");
    });
  </script>
<?php } ?>

<?php if ($_SESSION['bAdmin']) { ?>
  <script>
    ((window.gitter = {}).chat = {}).options = {
      room: 'churchcrm/crm',
      activationElement: false
    };
  </script>
  <script src="https://sidecar.gitter.im/dist/sidecar.v1.js" async defer></script>
<? } ?>

</body>
</html>
<?php

// Turn OFF output buffering
ob_end_flush();

// Reset the Global Message
$_SESSION['sGlobalMessage'] = "";

?>
