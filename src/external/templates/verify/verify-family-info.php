<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\dto\SystemConfig;

$familyService = new FamilyService();
// Set the page title and include HTML header
$sPageTitle = "ChurchCRM - Family Verification";
$sRootPath = SystemURLs::getRootPath();

require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
?>
  <div class="row">
    <div id="right-buttons" class="btn-group" role="group">
      <button type="button" id="verify" class="btn btn-sm" data-toggle="modal" data-target="#confirm-Verify" title="Looks Good"><i class="fa fa-check fa-5x"></i></button>
      <button type="button" id="verifyWithUpdates" class="btn btn-sm" data-toggle="modal" data-target="#confirm-Update" title="Have Updates"><i class="fa fa-pencil fa-5x" ></i></button>
      <button type="button" id="verifyRemove" class="btn btn-sm" data-toggle="modal" data-target="#confirm-Unlink" title="Remove From Church"><i class="fa fa-chain-broken fa-5x" ></i></button>
    </div>
  </div>
  <div class="box box-info" id="verifyBox">
    <div class="panel-body">
      <img class="img-circle center-block pull-right img-responsive" width="200" height="200"
           src="<?= $sRootPath ?>/<?= $familyService->getFamilyPhoto($family->getId()) ?>">
      <h2><a href=""><?= $family->getName() ?></a></h2>
      <div class="text-muted font-bold m-b-xs">
        <i class="fa fa-fw fa-map-marker" title="Home Address"></i><?= $family->getAddress() ?><br/>
        <i class="fa fa-fw fa-phone" title="Home Phone"></i><?= $family->getHomePhone() ?><br/>
        <i class="fa fa-fw fa-envelope" title="Family Email"></i><?= $family->getEmail() ?><br/>
        <i class="fa fa-fw fa-heart" title="Wedding Date"></i><?= $family->getWeddingDate() ?><br/>
        <i class="fa fa-fw fa-newspaper-o" title="Send Newsletter"></i><?= $family->getWeddingDate() ?><br/>
      </div>
    </div>
    <div class="border-right border-left">
      <section id="map">
        <div id="map1" style="height: 200px"></div>
      </section>
    </div>
    <div class="box box-solid">
      <div class="box-header">
        <i class="fa fa-users"></i>
        <h3 class="box-title">Family Member(s)</h3>
      </div>
      <div class="row">
        <?php foreach ($family->getPeopleSorted() as $person) { ?>
          <div class="col-md-3 col-sm-4">
            <div class="box box-primary">
              <div class="box-body box-profile">
                <img class="profile-user-img img-responsive img-circle" src="<?= $person->getPhoto() ?>">

                <h3 class="profile-username text-center"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>

                <p class="text-muted text-center"><i
                    class="fa fa-fw fa-<?= ($person->isMale() ? "male" : "female") ?>"></i> <?= $person->getFamilyRoleName() ?>
                </p>

                <ul class="list-group list-group-unbordered">
                  <li class="list-group-item">
                    <i class="fa fa-fw fa-phone" title="Home Phone"></i><?= $person->getHomePhone() ?><br/>
                    <i class="fa fa-fw fa-briefcase" title="Work Phone"></i><?= $person->getWorkPhone() ?><br/>
                    <i class="fa fa-fw fa-mobile" title="Mobile Phone"></i><?= $person->getCellPhone() ?><br/>
                    <i class="fa fa-fw fa-envelope" title="Email"></i><?= $person->getEmail() ?><br/>
                    <i class="fa fa-fw fa-envelope-o" title="Work Email"></i><?= $person->getWorkEmail() ?><br/>
                    <i class="fa fa-fw fa-birthday-cake"
                       title="Birthday"></i><?= $person->getBirthDate()->format("M d Y") ?> <?php if ($person->hideAge()) { ?>
                      <i class="fa fa-fw fa-eye-slash" title="Age Hidden"></i><?php } ?><br/>
                  </li>
                  <li class="list-group-item">
                    <?php $classification = ListOptionQuery::create()->filterById(1)->filterByOptionId($person->getClsId())->findOne()->getOptionName(); ?>
                    <b>Classification:</b> <?= $classification ?>
                  </li>
                  <li class="list-group-item">
                    <h4>Groups</h4>
                    <?php foreach ($person->getPerson2group2roleP2g2rs() as $groupMembership) {
                      $listOption = ListOptionQuery::create()->filterById($groupMembership->getGroup()->getRoleListId())->filterByOptionId($groupMembership->getRoleId())->findOne()->getOptionName();
                      ?>
                      <b><?= $groupMembership->getGroup()->getName() ?></b>: <span
                        class="pull-right"><?= $listOption ?></span>
                    <?php } ?>
                  </li>
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

  <div class="modal fade" id="confirm-Unlink" tabindex="888" role="dialog" aria-labelledby="Unlink-label"
       aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm") ?></h4>
        </div>

        <div class="modal-body">
          <p><?= gettext("You are about to request to be removed from the church.") ?></p>

          <p><?= gettext("Do you want to proceed?") ?></p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
          <a href="#"
             class="btn btn-danger danger"><?= gettext("Request") ?></a>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="confirm-Verify" tabindex="888" role="dialog" aria-labelledby="Verify-label"
       aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm") ?></h4>
        </div>

        <div class="modal-body">
          <p><?= gettext("You are about to confirm that all data is correct") ?></p>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
          <a href="#" class="btn btn-success"><?= gettext("Verify") ?></a>
        </div>
      </div>
    </div>
  </div>


  <div class="modal fade" id="confirm-Update" tabindex="888" role="dialog" aria-labelledby="Update-label"
       aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm") ?></h4>
        </div>

        <div class="modal-body">
          <p><?= gettext("Please let us know what information to update") ?></p>
          <textarea id="confirm-info-data" class="form-control" rows="10"></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
          <a href="#" class="btn btn-success"><?= gettext("Verify") ?></a>
        </div>
      </div>
    </div>
  </div>


<style>
  #verifyBox {
    padding: 5px;
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

  #right-buttons {
    z-index: 999;
    position: fixed;
    left: 35%;
  }

</style>

  <script src="<?= $sRootPath; ?>/skin/js/FamilyVerify.js"></script>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");
