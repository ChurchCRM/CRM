<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;

// Set the page title and include HTML header
$sPageTitle = gettext("Family Registration");

require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
     var tempFamilyData = <?=  json_encode($family) ?>;
</script>

<div class="register-box" style="width: 600px;">
  <div class="register-logo">
    <a href="<?= SystemURLs::getRootPath() ?>/"><b>Church</b>CRM</a>
  </div>

  <div class="register-box-body">

    <div class="box box-solid">
      <div class="box-header with-border">
        <h3
          class="box-title"><?= gettext('Register').' <b>'.$family["Name"].'</b> '.gettext('Family Members') ?></h3>
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
                    <select id="memberRole-<?= $x ?>" class="form-control">
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
                    <select id="memberGender-<?= $x ?>" class="form-control">
                      <option value="1"><?= gettext('Male') ?></option>
                      <option value="2"><?= gettext('Female') ?></option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-group has-feedback">
                <div class="row">
                  <div class="col-lg-6">
                    <input id="memberFirstName-<?= $x ?>" class="form-control" maxlength="50"
                           placeholder="<?= gettext('First Name') ?>" required>
                  </div>
                  <div class="col-lg-6">
                    <input id="memberLastName-<?= $x ?>" class="form-control" value="<?= $family["Name"] ?>" maxlength="50"
                           placeholder="<?= gettext('Last Name') ?>" required>
                  </div>
                </div>
              </div>
              <div class="form-group has-feedback">
                <div class="input-group">
                  <div class="input-group-addon">
                    <i class="fa fa-envelope"></i>
                  </div>
                  <input id="memberEmail-<?= $x ?>" class="form-control" maxlength="50"
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
                      <select id="memberPhoneType-<?= $x ?>" class="form-control">
                        <option value="mobile"><?= gettext('Mobile') ?></option>
                        <option value="home"><?= gettext('Home') ?></option>
                        <option value="work"><?= gettext('Work') ?></option>
                      </select>
                    </div>
                  </div>
                  <div class="col-lg-8">
                    <div class="input-group">
                      <input id="memberPhone-<?= $x ?>" class="form-control" maxlength="30" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat')?>"' data-mask
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
                      <input type="text" class="form-control inputDatePicker" id="memberBirthday-<?= $x ?>">
                    </div>
                  </div>
                  <div class="col-lg-6">
                      <label>
                        <input type="checkbox" id="memberHideAge-<?= $x ?>">&nbsp; <?= gettext('Hide Age') ?>
                      </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
      <div class="box-footer">
        <button id="familyMemberSubmit" type="submit" class="btn btn-primary btn-block btn-flat"><?= gettext('Next'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Done UI -->
<div id="doneUI" class="hidden">
    <div class="register-box" style="width: 600px;">
        <div class="register-logo">
            <a href="<?= SystemURLs::getRootPath() ?>/"><b>Church</b>CRM</a>
        </div>

        <div class="register-box-body">

            <div class="box box-solid">

                <h3><?= gettext('Registration Complete') ?></h3>


                <div class="box-header with-border">
                    <h3
                            class="box-title"><?= gettext('Thank you for registering your family.'); ?></h3>
                </div>
                <div class="box-body">
                    <h3><?= $family["Name"].' '.gettext('Family')?></h3>
                    <b><?= gettext('Address') ?></b>: <?= $family["Address1"]; ?><br/>
                </div>

                <p/>

                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/" class="btn btn-success"> <?= gettext ("Done") ?> </a>
                    <button type="button" id="regFamilyNPeople" href="<?= SystemURLs::getRootPath() ?>/" class="btn btn-success"> <?= gettext ("Done") ?> </button>
                </div>
            </div>

        </div>

        <!-- /.form-box -->
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
      $(".inputDatePicker").datepicker({
          autoclose: true
      });
      $("[data-mask]").inputmask();

      $("#familyMemberSubmit").click(function () {
          var completeFamilyData = tempFamilyData;
          completeFamilyData["people"] = [];
          <?php for ($x = 1; $x <= $familyCount; $x++) { ?>
          let person = {
              role: $("#<?= "memberRole-" . $x ?>").val(),
              gender: $("#<?= "memberGender-" . $x ?>").val(),
              firstName: $("#<?= "memberFirstName-" . $x ?>").val(),
              lastName: $("#<?= "memberLastName-" . $x ?>").val(),
              email: $("#<?= "memberEmail-" . $x ?>").val(),
              birthday: $("#<?= "memberBirthday-" . $x ?>").val(),
              hideAge: $("#<?= "memberHideAge-" . $x ?>").prop('checked')
          };

          let phoneType = $("#<?= "memberPhoneType-" . $x ?>").val();
          let phoneNumber = $("#<?= "memberPhone-" . $x ?>").val();
          if (phoneType == "mobile") {
              person["cellPhone"] = phoneNumber;
          } else if (phoneType == "work") {
              person["workPhone"] = phoneNumber;
          } else if (phoneType == "home") {
              person["homePhone"] = phoneNumber;
          }
          completeFamilyData["people"].push(person);
          <?php } ?>

          alert(JSON.stringify(completeFamilyData));

          window.CRM.APIRequest({
              method: "POST",
              path: "public/register/family",
              data: JSON.stringify(completeFamilyData)
              success: function (data) {
                  alert("Done");
              }
          });
      })
  });
</script>
<?php
// Add the page footer
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");
