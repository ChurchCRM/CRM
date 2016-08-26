<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require("../Include/HeaderNotLoggedIn.php");
?>
  <form action="<?= $sRootPath ?>/external/family/register/members" method="post">
    <?= print_r($body)?>

    <div class="box box-solid">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Register") . " " . $familyName . gettext(" members") . $familyCount ?></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <div class="box-group" id="accordion">
          <?php for ($x = 1; $x <= $familyCount; $x++) { ?>
            <div class="panel box box-primary">
              <div class="box-header with-border">
                <h4 class="box-title">
                  <a data-toggle="collapse" data-parent="#accordion" href="#collapse<?= $x ?>" aria-expanded="true"
                     class="">
                    Family Member #<?= $x ?>
                  </a>
                </h4>
              </div>
              <div id="collapseOne" class="panel-collapse collapse in" aria-expanded="true">
                <div class="box-body">
                  <div class="form-group has-feedback">
                    <div class="row">
                      <div class="col-lg-4">
                        <input name="memberFirstName-1" class="form-control" placeholder="<?= gettext("First Name") ?>"
                               required>
                      </div>
                      <div class="col-lg-4">
                        <input name="memberLastName-1" class="form-control" placeholder="<?= gettext("Last Name") ?>"
                               required>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </form>

<?php
// Add the page footer
require("../Include/FooterNotLoggedIn.php");
