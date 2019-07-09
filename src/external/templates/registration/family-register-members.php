<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\GenderTypeQuery;

// Set the page title and include HTML header
$sPageTitle = gettext("Family Registration");
require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
$genderlist = GenderTypeQuery::create()->find();
?>
  <form action="<?= SystemURLs::getRootPath() ?>/external/register/confirm" method="post">
    <div class="register-box" style="width: 600px;">
      <div class="register-logo">
        <a href="<?= SystemURLs::getRootPath() ?>/"><b>Church</b>CRM</a>
      </div>

      <div class="register-box-body">

        <div class="box box-solid">
          <div class="box-header with-border">
            <h3
              class="box-title"><?= gettext('Register').' <b>'.$family->getName().'</b> '.gettext('Family Members') ?></h3>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <?php for ($x = 1;
                       $x <= $familyCount;
                       $x++) {
    ?>
              <div class="box">
                <div class="box-header with-border">
                  <h4 class="box-title">
                    <?= gettext ("Family Member") . " #". $x ?>
                  </h4>
                </div>
                <div class="box-body">
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-8">
                        <select name="memberRole-<?= $x ?>" class="form-control">
                          <?php
                          switch ($x) {
                              case 1:
                                  $defaultRole = SystemConfig::getValue('sDirRoleHead');
                                  break;
                              case 2:
                                  $defaultRole = SystemConfig::getValue('sDirRoleSpouse');
                                  break;
                              default:
                                  $defaultRole = SystemConfig::getValue('sDirRoleChild');
                                  break;
                          }

                          foreach ($familyRoles as $role) { ?>
                            <option value="<?= $role->getOptionId() ?>" <?php if ( $role->getOptionId() == $defaultRole) { echo "selected"; } ?>><?= $role->getOptionName() ?></option>
                          <?php } ?>
                        </select>
                      </div>
                      <div class="col-lg-4">
                        <select name="memberGender-<?= $x ?>" class="form-control">
                        <?PHP
                          foreach($genderlist as $gender) {
                            echo '<option value=>' . $gender->getId() . '>' . gettext($gender->getName()) . '</option>';
                          }
                        ?>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-6">
                        <input name="memberFirstName-<?= $x ?>" class="form-control" maxlength="50"
                               placeholder="<?= gettext('First Name') ?>" required>
                      </div>
                      <div class="col-lg-6">
                        <input name="memberLastName-<?= $x ?>" class="form-control" value="<?= $family->getName() ?>" maxlength="50"
                               placeholder="<?= gettext('Last Name') ?>" required>
                      </div>
                    </div>
                  </div>
                  <div class="form-group has-feedback">
                    <div class="input-group">
                      <div class="input-group-addon">
                        <i class="fa fa-envelope"></i>
                      </div>
                      <input name="memberEmail-<?= $x ?>" class="form-control" maxlength="50"
                             placeholder="<?= gettext('Email') ?>">
                    </div>
                  </div>
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-4">
                        <div class="input-group">
                          <div class="input-group-addon">
                            <i class="fa fa-phone"></i>
                          </div>
                          <select name="memberPhoneType-<?= $x ?>" class="form-control">
                            <option value="mobile"><?= gettext('Mobile') ?></option>
                            <option value="home"><?= gettext('Home') ?></option>
                            <option value="work"><?= gettext('Work') ?></option>
                          </select>
                        </div>
                      </div>
                      <div class="col-lg-8">
                        <div class="input-group">
                          <input name="memberPhone-<?= $x ?>" class="form-control" maxlength="30" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat')?>"' data-mask
                                 placeholder="<?= gettext('Phone') ?>">
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-6">
                        <div class="input-group">
                          <div class="input-group-addon">
                            <i class="fa fa-birthday-cake"></i>
                          </div>
                          <input type="text" class="form-control inputDatePicker" name="memberBirthday-<?= $x ?>">
                        </div>
                      </div>
                      <div class="col-lg-6">
                          <label>
                            <input type="checkbox" name="memberHideAge-<?= $x ?>">&nbsp; <?= gettext('Hide Age') ?>
                          </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php
} ?>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary btn-block btn-flat"><?= gettext('Next'); ?></button>
          </div>
        </div>
      </div>
    </div>

  </form>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>" >
    $(function () {
      $(".inputDatePicker").datepicker({
        autoclose: true
      });
        $("[data-mask]").inputmask();
    });
  </script>
<?php
// Add the page footer
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");
