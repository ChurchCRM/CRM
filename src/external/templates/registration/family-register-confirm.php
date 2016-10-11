<?php
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Registration";
require(__DIR__ . "/../../../Include/HeaderNotLoggedIn.php");
?>

<form action="<?= $sRootPath ?>/external/register/done" method="post">
  <div class="register-box" style="width: 600px;">
    <div class="register-logo">
      <a href="<?= $sRootPath ?>/"><b>Church</b>CRM</a>
    </div>

    <div class="register-box-body">

      <div class="box box-solid">
        <div class="box-header with-border">
          <h3
            class="box-title"><?= gettext("Confirm") . " <b>" . $family->getName() . "</b> " . gettext("family information") ?></h3>
        </div>
        <div class="box-body">
          <h3><?= gettext("Family")?></h3>
          <b><?= gettext("Address") ?></b>: <?= $family->getAddress(); ?><br/>
          <b><?= gettext("Home Phone")?></b>: <?= $family->getHomePhone(); ?>
          <h3><?= gettext("Member(s)")?></h3>
          <?php foreach ($family->getPeople() as $person) { ?>
                <?= $person->getFamilyRoleName() ." - ". $person->getFullName(); ?><br/>
           <?php } ?>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary btn-block btn-flat"><?= gettext("Finish"); ?></button>
        </div>
      </div>

    </div>

    <!-- /.form-box -->
  </div>
</form>
<?php
// Add the page footer
require(__DIR__ . "/../../../Include/FooterNotLoggedIn.php");
