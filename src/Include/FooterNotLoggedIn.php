<?php
use ChurchCRM\dto\SystemURLs;

?>
    <div style="background-color: white; padding-top: 5px; padding-bottom: 5px; text-align: center; position: fixed; bottom: 0; width: 100%">
      <strong><?= gettext('Copyright') ?> &copy; 2015-2017 <a href="http://www.churchcrm.io" target="_blank"><b>Church</b>CRM</a>.</strong> <?= gettext('All rights reserved')?>.
    </div>

  <!-- Bootstrap 3.3.5 -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/bootstrap/js/bootstrap.min.js"></script>
  <!-- iCheck -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/iCheck/icheck.min.js"></script>

  <!-- AdminLTE App -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/dist/js/app.min.js"></script>

  <!-- InputMask -->
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.date.extensions.js"></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.extensions.js" ></script>
  <script src="<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datepicker/bootstrap-datepicker.js" ></script>

  <script>
    $(function () {
      $('input').iCheck({
        checkboxClass: 'icheckbox_square-blue',
        radioClass: 'iradio_square-blue',
        increaseArea: '20%' // optional
      });
    });
  </script>
  <?php
    //If this is a first-run setup, do not include google analytics code.
    if (basename($_SERVER['SCRIPT_FILENAME']) != 'Setup.php') {
        include_once('analyticstracking.php');
    }
 ?>
</body>
</html>
