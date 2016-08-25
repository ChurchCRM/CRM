<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require("../Include/HeaderNotLoggedIn.php");
?>

  <div class="register-box">
    <div class="register-logo">
      <a href="<?= $sRootPath ?>/"><b>Church</b>CRM</a>
    </div>

    <div class="register-box-body">
      <p class="login-box-msg"><?= gettext("Register your family") ?></p>

      <form action="<?= $sRootPath ?>/external/family/register" method="post">
        <div class="form-group has-feedback">
          <input type="text" class="form-control" placeholder="<?= gettext("Family Name") ?>" required>
          <span class="glyphicon glyphicon-user form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input class="form-control" placeholder="<?= gettext("Address") ?>" required>
          <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input class="form-control" placeholder="<?= gettext("City") ?>" required>
          <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input class="form-control" placeholder="<?= gettext("State") ?>" required>
          <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input class="form-control" placeholder="<?= gettext("Country") ?>" required>
          <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input class="form-control" placeholder="<?= gettext("Home Phone") ?>">
          <span class="glyphicon glyphicon-phone-alt form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <label><?= gettext("How many people are in your family") ?></label>
          <select class="form-control">
            <option>1</option>
            <option>2</option>
            <option>3</option>
            <option selected>4</option>
            <option>5</option>
            <option>6</option>
            <option>7</option>
            <option>8</option>
          </select>
        </div>
        <div class="form-group has-feedback">
          <hr/>
        </div>
        <div class="form-group has-feedback">
          <div class="radio">
            <label>
              <input type="radio" name="primaryChurch" id="optionsRadios1" value="Yes" checked>
              <?= gettext("This will be my primary church.") ?>
            </label>
          </div>
          <div class="radio">
            <label>
              <input type="radio" name="primaryChurch" id="optionsRadios2" value="No">
              <?= gettext("This will NOT be my primary church.") ?>
            </label>
          </div>
        </div>
        <div class="row">
          <div class="col-xs-6">
            <button type="submit" class="btn btn-primary btn-block btn-flat"><?= gettext("Register"); ?></button>
          </div>
          <!-- /.col -->
        </div>
      </form>
    </div>
    <!-- /.form-box -->
  </div>

<?php
// Add the page footer
require("../Include/FooterNotLoggedIn.php");
