 <?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\ChurchMetaData;

// Set the page title and include HTML header
$sPageTitle = gettext("Family Verification");

require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
?>
  <div class="row">
    <div id="right-buttons" class="btn-group" role="group">
      <button type="button" id="verify" class="btn btn-sm" data-toggle="modal" data-target="#confirm-Verify"><div class="btn-txt"><?=gettext("Confirm")?></div><i class="fa fa-check fa-5x"></i>  </button>
    </div>
  </div>
  <div class="box box-info" id="verifyBox">
    <div class="panel-body">
      <img class="img-circle center-block pull-right img-responsive initials-image" width="200" height="200"
           data-name="<?= $family->getName() ?>"
           <?php if ( $family->getThumbnailBytes() != FALSE ) { ?>
           src="data:image/png;base64,<?= base64_encode($family->getThumbnailBytes()) ?>"
           <?php } ?>>
      <h2><?= $family->getName() ?></h2>
      <div class="text-muted font-bold m-b-xs">
        <i class="fa fa-fw fa-map-marker" title="<?= gettext("Home Address")?>"></i><?= $family->getAddress() ?><br/>
          <?php if (!empty($family->getHomePhone()))  { ?>
          <i class="fa fa-fw fa-phone" title="<?= gettext("Home Phone")?>"> </i>(H) <?= $family->getHomePhone() ?><br/>
          <?php }  if (!empty($family->getEmail())) { ?>
          <i class="fa fa-fw fa-envelope" title="<?= gettext("Family Email") ?>"></i><?= $family->getEmail() ?><br/>
              <?php
          }
          if( $family->getWeddingDate() !== null) {
        ?>
            <i class="fa fa-fw fa-heart" title="<?= gettext("Wedding Date")?>"></i><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?><br/>
        <?php
          }
        ?>

        <i class="fa fa-fw fa-newspaper-o" title="<?= gettext("Send Newsletter")?>"></i><?= $family->getSendNewsletter() ?><br/>
      </div>
    </div>
    <div class="border-right border-left">
      <section id="map">
        <div id="map1"></div>
      </section>
    </div>
    <div class="box box-solid">
      <div class="box-header">
        <i class="fa fa-users"></i>
        <h3 class="box-title"><?= gettext("Family Member(s)")?></h3>
      </div>
      <div class="row">
        <?php foreach ($family->getPeopleSorted() as $person) { ?>
          <div class="col-md-3 col-sm-4">
            <div class="box box-primary">
              <div class="box-body box-profile">
                 <img class="profile-user-img img-responsive img-circle initials-image"
                      data-name="<?= $person->getFullName() ?>"
                       <?php if ( $person->getThumbnailBytes() != FALSE)
                        {?>
                      src="data:image/png;base64,<?= base64_encode($person->getThumbnailBytes()) ?>">
                        <?php } ?>


                <h3 class="profile-username text-center"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>

                <p class="text-muted text-center"><i
                    class="fa fa-fw fa-<?= ($person->isMale() ? "male" : "female") ?>"></i> <?= $person->getFamilyRoleName() ?>
                </p>

                <ul class="list-group list-group-unbordered">
                  <li class="list-group-item">
                      <?php if (!empty($person->getHomePhone()))  { ?>
                    <i class="fa fa-fw fa-phone" title="<?= gettext("Home Phone")?>"></i>(H) <?= $person->getHomePhone() ?><br/>
                      <?php }  if (!empty($person->getWorkPhone()))  { ?>
                    <i class="fa fa-fw fa-briefcase" title="<?= gettext("Work Phone")?>"></i>(W) <?= $person->getWorkPhone() ?><br/>
                      <?php }  if (!empty($person->getCellPhone()))  { ?>
                    <i class="fa fa-fw fa-mobile" title="<?= gettext("Mobile Phone")?>"></i>(M) <?= $person->getCellPhone() ?><br/>
                      <?php }  if (!empty($person->getEmail()))  { ?>
                    <i class="fa fa-fw fa-envelope" title="<?= gettext("Email")?>"></i>(H) <?= $person->getEmail() ?><br/>
                      <?php }  if (!empty($person->getWorkEmail()))  { ?>
                    <i class="fa fa-fw fa-envelope-o" title="<?= gettext("Work Email")?>"></i>(W) <?= $person->getWorkEmail() ?><br/>
                      <?php }  ?>
                    <i class="fa fa-fw fa-birthday-cake"
                       title="Birthday"></i><?= $person->getBirthDate()->format("M d Y") ?> <?php if ($person->hideAge()) { ?>
                      <i class="fa fa-fw fa-eye-slash" title="<?= gettext("Age Hidden")?>"></i><?php } ?><br/>
                  </li>
                  <li class="list-group-item">
                    <?php
                    $classification = "";
                    $cls = ListOptionQuery::create()->filterById(1)->filterByOptionId($person->getClsId())->findOne();
                    if (!empty($cls)) {
                        $classification = $cls->getOptionName();
                    }
                    ?>
                    <b>Classification:</b> <?= $classification ?>
                  </li>
                  <?php if (count($person->getPerson2group2roleP2g2rs()) > 0) {?>
                  <li class="list-group-item">
                    <h4>Groups</h4>
                    <?php foreach ($person->getPerson2group2roleP2g2rs() as $groupMembership) {
                        if ($groupMembership->getGroup() != null) {
                            $listOption = ListOptionQuery::create()->filterById($groupMembership->getGroup()->getRoleListId())->filterByOptionId($groupMembership->getRoleId())->findOne()->getOptionName();
                    ?>
                        <b><?= $groupMembership->getGroup()->getName() ?></b>: <span class="pull-right"><?= $listOption ?></span><br/>
                    <?php
                        }
                    }
                    ?>
                  </li>
                  <?php } ?>
                </ul>
              </div>
              <!-- /.box-body -->
            </div>
            <!-- /.box -->
          </div>
        <?php } ?>
      </div>
    </div>
  </div>


  <script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue("sGoogleMapKey") ?>&sensor=false"></script>

  <script>
    var LatLng = new google.maps.LatLng(<?= $family->getLatitude() ?>, <?= $family->getLongitude() ?>)
    var token = '<?= $token->getToken()?>';
  </script>

  <div class="modal fade" id="confirm-Verify" tabindex="888" role="dialog" aria-labelledby="Verify-label"
       aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm") ?></h4>
        </div>

        <div class="modal-body" id="confirm-modal-collect">
          <p><?= gettext("Please let us know what information to update if any") ?></p>
          <textarea id="confirm-info-data" class="form-control" rows="10"></textarea>
        </div>

        <div class="modal-body" id="confirm-modal-done">
          <p><?= gettext("Your verification request is complete") ?></p>
        </div>

        <div class="modal-body" id="confirm-modal-error">
          <p><?= gettext("We encountered an error submitting with your verification data") ?></p>
        </div>

        <div class="modal-footer">
          <button id="onlineVerifyCancelBtn" type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
          <button id="onlineVerifyBtn" class="btn btn-success"><?= gettext("Verify") ?></button>
          <a href="<?= ChurchMetaData::getChurchWebSite() ?>" id="onlineVerifySiteBtn" class="btn btn-success"><?= gettext("Visit our Site") ?></a>
        </div>
      </div>
    </div>
  </div>



<style>

  #verifyBox {
    padding: 5px;
  }

  #map1 {
    height: 200px;
  }

  .btn-sm {
    vertical-align: center;
    position: relative;
    margin: 0px;
    padding: 20px 20px;
    font-size: 4px;
    color: white;
    text-align: center;
    background: #62b1d0;
  }
  .btn-txt {
    font-size: 15px;
  }

  #right-buttons {
    z-index: 999;
    position: fixed;
    left: 45%;
  }

  #success-alert, #error-alert {
    z-index: 888;
  }

</style>

<script src="<?= SystemURLs::getRootPath(); ?>/skin/randomcolor/randomColor.js"></script>
<script src="<?= SystemURLs::getRootPath(); ?>/skin/js/initial.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyVerify.js"></script>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");
