<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require(__DIR__ . "/../../../Include/HeaderNotLoggedIn.php");
?>

  <div class="register-box" style="width: 600px;">
    <div class="register-logo">
      <a href="<?= $sRootPath ?>/"><b>Church</b>CRM</a>
    </div>

    <div class="register-box-body">
      <h3><?= gettext("Registration Complete") ?></h3>

      <?= gettext("Thank you for registering your family."); ?>

      <p/>

      <div class="text-center">
        <a href="<?= $sRootPath ?>/" class="btn btn-success">Done</a>
      </div>
    </div>
    <!-- /.form-box -->
  </div>

<?php
// Add the page footer
require(__DIR__ . "/../../../Include/FooterNotLoggedIn.php");
