<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require(__DIR__ ."/../../../Include/HeaderNotLoggedIn.php");
?>

  <div class="register-box" style="width: 600px;">
    <div class="register-logo">
      <a href="<?= $sRootPath ?>/"><b>Church</b>CRM</a>
    </div>

    <div class="register-box-body">
      <p class="login-box-msg"><?= gettext("Register your family") ?></p>

      <form action="<?= $sRootPath ?>/external/register/" method="post">
        <div class="form-group has-feedback">
          <input name="familyName" type="text" class="form-control" placeholder="<?= gettext("Family Name") ?>" required>
          <span class="glyphicon glyphicon-user form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <input name="familyAddress1" class="form-control" placeholder="<?= gettext("Address") ?>" required>
          <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <div class="row">
            <div class="col-lg-6">
              <input name="familyCity" class="form-control" placeholder="<?= gettext("City") ?>" required>
            </div>
            <div class="col-lg-6">
              <input name="familyState" class="form-control" placeholder="<?= gettext("State") ?>" required>
            </div>
          </div>
        </div>
        <div class="form-group has-feedback">
          <input name="familyCountry" class="form-control" placeholder="<?= gettext("Country") ?>" required>
        </div>
        <div class="form-group has-feedback">
          <input name="familyHomePhone" class="form-control" placeholder="<?= gettext("Home Phone") ?>">
          <span class="glyphicon glyphicon-phone-alt form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback">
          <label><?= gettext("How many people are in your family") ?></label>
          <select name="familyCount" class="form-control">
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
          <div class="checkbox">
            <label>
              <input type="checkbox" name="familyPrimaryChurch" checked>&nbsp;
              <?= gettext("This will be my primary church.") ?>
            </label>
          </div>
        </div>
        <div class="row">
          <div class="col-xs-12 text-center">
            <button type="submit" class="btn bg-olive"><?= gettext("Next"); ?></button>
          </div>
          <!-- /.col -->
        </div>
      </form>
    </div>
    <!-- /.form-box -->
  </div>

<?php
// Add the page footer
require(__DIR__ ."/../../../Include/FooterNotLoggedIn.php");
