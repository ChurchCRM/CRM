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
    <div class="pull-right hidden-xs">
      <b>Version</b> <?= $_SESSION['sSoftwareInstalledVersion'] ?>
    </div>
    <strong>Copyright &copy; 2015-2016 <a href="http://www.churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> All rights reserved.
  </footer>

    <!-- Add the sidebar's background. This div must be placed
         immediately after the control sidebar -->
    <div class="control-sidebar-bg"></div>
    </div>
    <!-- ./wrapper -->
  </div><!-- ./wrapper -->

<!-- Bootstrap 3.3.5 -->

  <script src="<?= $sRootPath ?>/skin/adminlte/bootstrap/js/bootstrap.min.js"></script>
  <!-- SlimScroll -->
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/slimScroll/jquery.slimscroll.min.js"></script>
  <!-- FastClick -->
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/fastclick/fastclick.js"></script>
  <!-- AdminLTE App -->
  <script src="<?= $sRootPath ?>/skin/adminlte/dist/js/app.min.js"></script>

  <script src="<?= $sRootPath ?>/skin/js/DataTables.js"></script>
  <script src="<?= $sRootPath ?>/skin/js/Tooltips.js"></script>
  <script src="<?= $sRootPath ?>/skin/js/Events.js"></script>
  <script src="<?= $sRootPath ?>/skin/js/Footer.js"></script>
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
