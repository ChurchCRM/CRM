<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require("../Include/HeaderNotLoggedIn.php");
?>
  <form action="<?= $sRootPath ?>/external/family/register/members" method="post">
    <div class="register-box" style="width: 600px;">
      <div class="register-logo">
        <a href="<?= $sRootPath ?>/"><b>Church</b>CRM</a>
      </div>

      <div class="register-box-body">

        <div class="box box-solid">
          <div class="box-header with-border">
            <h3
              class="box-title"><?= gettext("Register") . " <b>" . $family->getName() . "</b> " . gettext("family members") ?></h3>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <?php for ($x = 1;
                       $x <= $familyCount;
                       $x++) { ?>
              <div class="box">
                <div class="box-header with-border">
                  <h4 class="box-title">
                    Family Member #<?= $x ?>
                  </h4>
                </div>
                <div class="box-body">
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-8">
                        <select name="memberRole-<?= $x ?>" class="form-control">
                          <?php foreach ($familyRoles as $role) { ?>
                            <option value="<?= $role->getOptionId() ?>"><?= $role->getOptionName() ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <div class="col-lg-4">
                        <select name="memberGender-<?= $x ?>" class="form-control">
                          <option value="1"><?= gettext("Male") ?></option>
                          <option value="2"><?= gettext("Female") ?></option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-6">
                        <input name="memberFirstName-<?= $x ?>" class="form-control"
                               placeholder="<?= gettext("First Name") ?>" required>
                      </div>
                      <div class="col-lg-6">
                        <input name="memberLastName-<?= $x ?>" class="form-control"
                               placeholder="<?= gettext("Last Name") ?>" required>
                      </div>
                    </div>
                  </div>
                  <div class="form-group has-feedback">
                    <input name="memberEmail-<?= $x ?>" class="form-control"
                           placeholder="<?= gettext("Email") ?>">
                  </div>
                  <div class="form-group has-feedback">
                    <input name="memberPhone-<?= $x ?>" class="form-control"
                           placeholder="<?= gettext("Phone") ?>">
                  </div>
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-4">
                        <select name="memberBirthDay-<?= $x ?>" class="form-control">
                          <?php for ($y = 1; $y <= 31; $y++) { ?>
                            <option><?= $y ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <div class="col-lg-4">
                        <select name="memberBirthMonth-<?= $x ?>" class="form-control">
                          <?php for ($z = 1; $z < 13; $z++) {
                            $month = gettext(date('F', mktime(0, 0, 0, $z, 1, 2016))) ?>
                            <option value="<?= $z ?>"><?= $month ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <div class="col-lg-4">
                        <input name="memberBirthYear-<?= $x ?>" class="form-control"
                               placeholder="<?= gettext("Birth Year") ?>" required>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php } ?>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary btn-block btn-flat"><?= gettext("Register"); ?></button>
          </div>
        </div>
      </div>
    </div>

  </form>

<?php
// Add the page footer
require("../Include/FooterNotLoggedIn.php");
