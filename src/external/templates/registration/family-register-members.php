<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require(__DIR__ ."/../../../Include/HeaderNotLoggedIn.php");
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
                        <input name="memberLastName-<?= $x ?>" class="form-control" value="<?= $family->getName()?>"
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
                      <div class="col-lg-6">
                          <div class="input-group">
                            <div class="input-group-addon">
                              <i class="fa fa-birthday-cake"></i>
                            </div>
                            <input type="text" class="form-control inputDatePicker" id="birthday-<?= $x ?>" class="form-control" data-inputmask="'alias': 'mm/dd/yyyy'" data-mask="">
                          </div>
                          <!-- /.input group -->
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
  <script type="text/javascript">
    $(function() {
      $("[data-mask]").inputmask();
    });
  </script>
<?php
// Add the page footer
require(__DIR__ ."/../../../Include/FooterNotLoggedIn.php");
