<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;

// Set the page title and include HTML header
$sPageTitle = gettext("Family Verification");

require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");

$doShowMap = !(empty($family->getLatitude()) && empty($family->getLongitude()));
?>
  <div class="row">
    <div id="right-buttons" class="btn-group" role="group">
      <button type="button" id="verify" class="btn btn-sm" data-toggle="modal" data-target="#confirm-Verify"><div class="btn-txt"><?=gettext("Confirm")?></div><i class="fa fa-check fa-5x"></i>  </button>
    </div>
  </div>
  <div class="card card-info" id="verifyBox">
    <div class="panel-body">
      <img class="img-circle center-block pull-right img-responsive initials-image" width="200" height="200" src="data:image/png;base64,<?= base64_encode($family->getPhoto()->getThumbnailBytes()) ?>" >
      <h2><?= $family->getName() ?></h2>
      <div class="text-muted font-bold m-b-xs">
        <i class="fa fa-fw fa-map-marker" title="<?= gettext("Home Address")?>"></i><?= $family->getAddress() ?><br/>
          <?php if (!empty($family->getHomePhone())) { ?>
          <i class="fa fa-fw fa-phone" title="<?= gettext("Home Phone")?>"> </i>(H) <?= $family->getHomePhone() ?><br/>
          <?php }  if (!empty($family->getEmail())) { ?>
          <i class="fa fa-fw fa-envelope" title="<?= gettext("Family Email") ?>"></i><?= $family->getEmail() ?><br/>
              <?php
          }
          if ($family->getWeddingDate() !== null) {
                ?>
            <i class="fa fa-fw fa-heart" title="<?= gettext("Wedding Date")?>"></i><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?><br/>
              <?php
          }
            ?>

        <i class="fa fa-fw fa-newspaper" title="<?= gettext("Send Newsletter")?>"></i><?= $family->getSendNewsletter() ?><br/>
      </div>
    </div>
    <div class="border-right border-left">
      <?php if ($doShowMap) { ?>
        <section id="map">
          <div id="map1"></div>
        </section>
      <?php } ?>
    </div>
    <div class="card card-solid">
      <div class="card-header">
        <i class="fa fa-users"></i>
        <h3 class="card-title"><?= gettext("Family Member(s)")?></h3>
      </div>
      <div class="row row-flex row-flex-wrap">
        <?php foreach ($family->getPeopleSorted() as $person) { ?>
          <div class="col-md-4 col-sm-4">
            <div class="card card-primary">
              <div class="card-body box-profile">
                 <img class="profile-user-img img-responsive img-circle initials-image" src="data:image/png;base64,<?= base64_encode($person->getPhoto()->getThumbnailBytes()) ?>">

                <h3 class="profile-username text-center"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>

                <p class="text-muted text-center"><i
                    class="fa fa-fw fa-<?= ($person->isMale() ? "male" : "female") ?>"></i> <?= $person->getFamilyRoleName() ?>
                </p>

                <ul class="list-group list-group-unbordered">
                  <li class="list-group-item">
                      <?php if (!empty($person->getHomePhone())) { ?>
                    <i class="fa fa-fw fa-phone" title="<?= gettext("Home Phone")?>"></i>(H) <?= $person->getHomePhone() ?><br/>
                      <?php }  if (!empty($person->getWorkPhone())) { ?>
                    <i class="fa fa-fw fa-briefcase" title="<?= gettext("Work Phone")?>"></i>(W) <?= $person->getWorkPhone() ?><br/>
                      <?php }  if (!empty($person->getCellPhone())) { ?>
                    <i class="fa fa-fw fa-mobile" title="<?= gettext("Mobile Phone")?>"></i>(M) <?= $person->getCellPhone() ?><br/>
                      <?php }  if (!empty($person->getEmail())) { ?>
                    <i class="fa fa-fw fa-envelope" title="<?= gettext("Email")?>"></i>(H) <?= $person->getEmail() ?><br/>
                      <?php }  if (!empty($person->getWorkEmail())) { ?>
                    <i class="fa fa-fw fa-envelope" title="<?= gettext("Work Email")?>"></i>(W) <?= $person->getWorkEmail() ?><br/>
                      <?php }  ?>
                    <i class="fa fa-fw fa-cake-candles" title="<?= gettext("Birthday")?>"></i> <?= $person->getFormattedBirthDate() ?><br/>
                  </li>
                  <li class="list-group-item">
                    <b>Classification:</b> <?= Classification::getName($person->getClsId()) ?>
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


  <script  src="//maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue("sGoogleMapsRenderKey") ?>"></script>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    <?php if ($doShowMap) { ?>
      var LatLng = new google.maps.LatLng(<?= $family->getLatitude() ?>, <?= $family->getLongitude() ?>)
    <?php } else { ?>
      var LatLng = null;
    <?php } ?>
    var token = '<?= $token->getToken()?>';
  </script>

  <div class="modal fade" id="confirm-Verify" tabindex="888" role="dialog" aria-labelledby="Verify-label"
       aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header info">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm") ?></h4>
        </div>

        <div class="modal-body" id="confirm-modal-collect">
            <form id="verifyForm">
            <div class="form-group">
                  <div class="radio">
                    <label>
                      <input type="radio" name="verifyType" id="NoChanges" value="no-change" checked="">
                      <?= gettext('All information on this page is correct.') ?>
                    </label>
                  </div>
                  <div class="radio">
                    <label>
                      <input type="radio" name="verifyType" id="UpdateNeeded" value="change-needed">
                      <?= gettext('Please update the my family information with the following') ?>
                    </label>
                  </div>
                </div>
          <textarea id="confirm-info-data" class="form-control" rows="10"></textarea>
        </form>
        </div>

        <div class="modal-body" id="confirm-modal-done">
          <p><?= gettext("Your verification request is complete") ?></p>
        </div>

        <div class="modal-body" id="confirm-modal-error">
          <p><?= gettext("We encountered an error submitting with your verification data") ?></p>
        </div>

        <div class="modal-footer">
          <button id="onlineVerifyCancelBtn" type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
          <button id="onlineVerifyBtn" class="btn btn-success"><?= gettext("Send") ?></button>
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

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyVerify.js"></script>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
