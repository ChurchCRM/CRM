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

$isAdmin = $_SESSION['user']->isAdmin();

?>
</section><!-- /.content -->

</div>
<!-- /.content-wrapper -->
<footer class="main-footer">
  <div class="pull-right">
    <b><?= gettext('Version') ?></b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
  </div>
  <strong><?= gettext('Copyright') ?> &copy; 2015-2016 <a href="http://www.churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved') ?>
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
          <a href="<?= $sRootPath ?>/OptionManager.php?mode=famroles">
            <i class="fa fa-cog"></i> <?= _('Family Roles') ?>
          </a>
        </li>
        <li>
          <a href="<?= $sRootPath ?>/PropertyList.php?Type=f">
            <i class="fa fa-cog"></i> <?= _('Family Properties') ?>
          </a>
        </li>
        <?php if ($isAdmin) {
    ?>
          <li>
            <a href="<?= $sRootPath ?>/FamilyCustomFieldsEditor.php">
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
          <a href="<?= $sRootPath ?>/OptionManager.php?mode=classes">
            <i class="fa fa-cog"></i> <?= _('Classifications Manager') ?>
          </a>
        </li>
        <li>
          <a href="<?= $sRootPath ?>/PropertyList.php?Type=p">
            <i class="fa fa-cog"></i> <?= _('People Properties') ?>
          </a>
        </li>
        <?php if ($isAdmin) {
    ?>
          <li>
            <a href="<?= $sRootPath ?>/PersonCustomFieldsEditor.php">
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
          <a href="<?= $sRootPath ?>/PropertyList.php?Type=g">
            <i class="fa fa-cog"></i>  <?= _('Group Properties') ?>
          </a>
        </li>
        <li>
          <a href="<?= $sRootPath ?>/OptionManager.php?mode=grptypes">
            <i class="fa fa-cog"></i>  <?= _('Edit Group Types') ?>
          </a>
        </li>
      </ul>
      <br/>
      <h4 class="control-sidebar-heading"><i class="fa fa-cogs"></i> <?= _('Other') ?></h4>
      <ul class="control-sidebar-menu">
        <li>
          <a href="<?= $sRootPath ?>/PropertyTypeList.php">
            <i class="fa fa-cog"></i> <?= _('Property Types') ?>
          </a>
        </li>
        <?php if ($isAdmin) {
    ?>
          <li>
            <a href="<?= $sRootPath ?>/VolunteerOpportunityEditor.php">
              <i class="fa fa-cog"></i> <?= _('Volunteer Opportunities') ?>
            </a>
          </li>
          <li>
            <a href="<?= $sRootPath ?>/DonationFundEditor.php">
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
              <a href="<?= $sRootPath ?>/SystemSettings.php">
                <i class="menu-icon fa fa-gears bg-red"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _('Edit General Settings') ?></h4>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= $sRootPath ?>/UserList.php">
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
              <a href="<?= $sRootPath ?>/RestoreDatabase.php">
                <i class="menu-icon fa fa-database bg-yellow-gradient"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _('Restore Database') ?></h4>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= $sRootPath ?>/BackupDatabase.php">
                <i class="menu-icon fa fa-database bg-green"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _('Backup Database') ?></h4>
                </div>
              </a>
            </li>
            <li>
              <a href="<?= $sRootPath ?>/CSVImport.php">
                <i class="menu-icon fa fa-upload bg-yellow-gradient"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _('CSV Import') ?></h4>
                </div>
              </a>
            </li>
          <?php

} ?>
          <li>
            <a href="<?= $sRootPath ?>/CSVExport.php">
              <i class="menu-icon fa fa-download bg-green"></i>
              <div class="menu-info">
                <h4 class="control-sidebar-subheading"><?= _('CSV Export Records') ?></h4>
              </div>
            </a>
          </li>
        </ul>
        <?php if ($isAdmin) {
    ?>
          <hr/>
          <ul class="control-sidebar-menu">
            <li>
              <a href="<?= $sRootPath ?>/GenerateSeedData.php">
                <i class="menu-icon fa fa-plus-square bg-teal"></i>
                <div class="menu-info">
                  <h4 class="control-sidebar-subheading"><?= _('Generate Seed Data') ?></h4>
                </div>
              </a>
            </li>
          </ul>
        <?php

} else {
    echo _('Please contact your admin to change the system settings.');
} ?>
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
              <h4 class="control-sidebar-subheading" title="<?= $task['desc'] ?>"><?= $task['title'] ?></h4>
            </div>
          </a>

        </li>
        <!-- end task item -->
      <?php

} ?>
      </ul>
      <!--
      <h3 class="control-sidebar-heading"><?= gettext('Data Quality') ?></h3>
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
      </ul>
      -->
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

<?php if ($sGlobalMessage) {
    ?>
  <script>
    $("document").ready(function () {
      showGlobalMessage("<?= $sGlobalMessage ?>", "<?=$sGlobalMessageClass?>");
    });
  </script>
<?php

} ?>

<?php if ($_SESSION['bAdmin']) {
    ?>
  <script>
    ((window.gitter = {}).chat = {}).options = {
      room: 'churchcrm/crm',
      activationElement: false
    };
  </script>
  <script src="https://sidecar.gitter.im/dist/sidecar.v1.js" async defer></script>
  <?php

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
