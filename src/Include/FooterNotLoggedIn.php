  <!-- Bootstrap 3.3.5 -->
  <script src="<?= $sRootPath ?>/skin/adminlte/bootstrap/js/bootstrap.min.js"></script>
  <!-- iCheck -->
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/iCheck/icheck.min.js"></script>

  <!-- AdminLTE App -->
  <script src="<?= $sRootPath ?>/skin/adminlte/dist/js/app.min.js"></script>

  <!-- InputMask -->
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.js" type="text/javascript"></script>
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.date.extensions.js" type="text/javascript"></script>
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/input-mask/jquery.inputmask.extensions.js" type="text/javascript"></script>
  <script src="<?= $sRootPath ?>/skin/adminlte/plugins/datepicker/bootstrap-datepicker.js" type="text/javascript"></script>

  <script>
    $(function () {
      $('input').iCheck({
        checkboxClass: 'icheckbox_square-blue',
        radioClass: 'iradio_square-blue',
        increaseArea: '20%' // optional
      });
    });
  </script>
</body>
</html>
