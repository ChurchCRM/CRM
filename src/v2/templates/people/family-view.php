<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;

//Set the page title
$sPageTitle =  $family->getName() . " - " . gettext("Family");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';

$curYear = (new DateTime())->format("Y");
$familyAddress = $family->getAddress();
$mailchimp = new MailChimpService();

$iFYID = CurrentFY();
if (array_key_exists('idefaultFY', $_SESSION)) {
    $iFYID = MakeFYString($_SESSION['idefaultFY']);
}
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentFamily = <?= $family->getId() ?>;
    window.CRM.currentFamilyName = "<?= $family->getName() ?>";
    window.CRM.currentActive = <?= $family->isActive() ? "true" : "false" ?>;
    window.CRM.currentFamilyView = 2;
    window.CRM.plugin.mailchimp = <?= $mailchimp->isActive() ? "true" : "false" ?>;
</script>


<div id="family-deactivated" class="alert alert-warning d-none">
    <strong><?= gettext("This Family is Deactivated") ?> </strong>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="row">
            <div class="col-lg-4">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><?= $family->getName() ?> [<?= $family->getId() ?>]</h3>
                        <div class="card-tools pull-right">
                            <button type="button" class="btn btn-box-tool edit-family"><i class="fa fa-pen"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="image-container">
                            <img src="<?= SystemURLs::getRootPath() ?>/api/family/<?= $family->getId() ?>/photo"
                                 class="img-responsive profile-user-img profile-family-img"/>
                            <div class="after">
                                <div class="buttons">
                                    <a id="view-larger-image-btn" href="#" title="<?= gettext("View Photo") ?>">
                                        <i class="fa fa-search-plus"></i>
                                    </a>
                                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) : ?>
                                        &nbsp;
                                        <a href="#" data-toggle="modal" data-target="#upload-image"
                                           title="<?= gettext("Upload Photo") ?>">
                                            <i class="fa fa-camera"></i>
                                        </a>&nbsp;
                                        <a href="#" data-toggle="modal" data-target="#confirm-delete-image"
                                           title="<?= gettext("Delete Photo") ?>">
                                            <i class="fa fa-trash-can"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
                <div class="col-lg-8">
                    <div class="card">
                        <br/>
                        <div class="text-center">
                            <a class="btn btn-app" id="lastFamily"><i
                                        class="fa fa-hand-point-left"></i><?= gettext('Previous Family') ?></a>

                            <a class="btn btn-app btn-danger" role="button" href="<?= SystemURLs::getRootPath()?>/v2/family"><i
                                        class="fa fa-list-ul"></i><?= gettext('Family List') ?></a>

                            <a class="btn btn-app" role="button" id="nextFamily" ><i
                                        class="fa fa-hand-point-right"></i><?= gettext('Next Family') ?> </a>
                        </div>
                        <hr/>
                        <div class="text-center">
                        <a class="btn btn-sm btn-app" href="#" data-toggle="modal" data-target="#confirm-verify"><i
                                class="fa fa-check-square"></i> <?= gettext("Verify Info") ?></a>
                        <a class="btn btn-app bg-olive"
                           href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?FamilyID=<?=$family->getId()?>"><i
                                class="fa fa-plus-square"></i> <?= gettext('Add New Member') ?></a>

                        <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                            <button class="btn btn-app bg-orange" id="activateDeactivate">
                                <i class="fa <?= (empty($family->isActive()) ? 'fa-toggle-on' : 'fa-toggle-off') ?> "></i><?php echo(($family->isActive() ? _('Deactivate') : _('Activate')) . _(' this Family')); ?>
                            </button>
                        <?php }
                        if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) {
                            ?>
                            <a id="deleteFamilyBtn" class="btn btn-app bg-maroon"
                               href="<?= SystemURLs::getRootPath() ?>/SelectDelete.php?FamilyID=<?=$family->getId()?>"><i
                                        class="fa fa-trash-can"></i><?= gettext('Delete this Family') ?></a>
                            <?php
                        }
                        if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) {
                            ?>
                            <a class="btn btn-app"
                               href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId()?>"><i
                                    class="fa fa-sticky-note"></i><?= gettext("Add a Note") ?></a>
                            <?php
                        } ?>
                        <a class="btn btn-app" id="AddFamilyToCart" data-familyid="<?= $family->getId() ?>"> <i
                                class="fa fa-cart-plus"></i> <?= gettext("Add All Family Members to Cart") ?></a>
                        <?php if (AuthenticationManager::getCurrentUser()->isCanvasserEnabled()) { ?>
                            <a class="btn btn-app" href="<?= SystemURLs::getRootPath()?>/CanvassEditor.php?FamilyID=<?= $family->getId() ?>&FYID=<?= $iFYID ?>&linkBack=v2/family/<?= $family->getId() ?>">
                                <i class="fas fa-refresh"></i><?= $iFYID . gettext(" Canvass Entry") ?></a>
                        <?php } ?>

                        <?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) { ?>
                            <a class="btn btn-app"
                               href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=v2/family/<?= $family->getId() ?>&PledgeOrPayment=Pledge">
                                <i class="fa fa-hand-holding-dollar"></i><?= gettext("Add a new pledge") ?></a>
                            <a class="btn btn-app"
                               href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=v2/family/<?= $family->getId() ?>&PledgeOrPayment=Payment">
                                <i class="fa-solid fa-money-bill-transfer"></i><?= gettext("Add a new payment") ?></a>
                        <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"> <i class="fa fa-thumbtack"></i> <?= gettext("Metadata") ?></h3>
                        <div class="card-tools pull-right">
                            <button type="button" class="btn btn-box-tool edit-family"><i
                                    class="fa fa-pen"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="fa-ul">

                            <?php
                            if (!SystemConfig::getBooleanValue("bHideFamilyNewsletter")) { /* Newsletter can be hidden - General Settings */ ?>
                                <li><i class="fa-li fa fa-newspaper"></i><?= gettext("Send Newsletter") ?>:
                                    <span style="color:<?= ($family->isSendNewsletter() ? "green" : "red") ?>"><i
                                            class="fa fa-<?= ($family->isSendNewsletter() ? "check" : "times") ?>"></i></span>
                                </li>
                                <?php
                            }
                            if (!SystemConfig::getBooleanValue("bHideWeddingDate") && !empty($family->getWeddingdate())) { /* Wedding Date can be hidden - General Settings */ ?>
                                <li><i class="fa-li fa fa-magic"></i><?= gettext("Wedding Date") ?>:
                                    <span><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?></span></li>
                                <?php
                            }
                            if (SystemConfig::getValue("bUseDonationEnvelopes")) {
                                ?>
                                <li><i class="fa-li fa fa-phone"></i><?= gettext("Envelope Number") ?>
                                    <span><?= $family->getEnvelope() ?></span>
                                </li>
                                <?php
                            }
                            if (!empty($family->getHomePhone())) {
                                ?>
                                <li><i class="fa-li fa fa-phone"></i><?= gettext("Home Phone") ?>: <span><a
                                            href="tel:<?= $family->getHomePhone() ?>"><?= $family->getHomePhone() ?></a></span>
                                </li>
                                <?php
                            }
                            if ($family->getWorkPhone() != "") {
                                ?>
                                <li><i class="fa-li fa fa-building"></i><?= gettext("Work Phone") ?>: <span><a
                                            href="tel:<?= $family->getWorkPhone() ?>"><?= $family->getWorkPhone() ?></a></span>
                                </li>
                                <?php
                            }
                            if ($family->getCellPhone() != "") {
                                ?>
                                <li><i class="fa-li fa fa-mobile"></i><?= gettext("Mobile Phone") ?>: <span><a
                                            href="tel:<?= $family->getCellPhone() ?>"><?= $family->getCellPhone() ?></a></span>
                                </li>
                                <?php
                            }
                            if ($family->getEmail() != "") {
                                ?>
                                <li><i class="fa-li fa fa-envelope"></i><?= gettext("Email") ?>:<a
                                        href="mailto:<?= $family->getEmail() ?>">
                                        <span><?= $family->getEmail() ?></span></a></li>
                                <?php if ($mailchimp->isActive()) { ?>
                                 <li><i class="fa-li fa-regular fa-paper-plane"></i><?= gettext("Mailchimp") ?>:
                                 <span id="<?= md5($family->getEmail())?>">... <?= gettext("loading")?> ...</span></a></li>
                                <?php }
                            }
                            foreach ($familyCustom as $customField) {
                                echo '<li><i class="fa-li ' . $customField->getIcon() . '"></i>' . $customField->getDisplayValue() . ': <span>';
                                if ($customField->getLink()) {
                                    echo "<a href=\"" . $customField->getLink() . "\">" . $customField->getFormattedValue() . "</a>";
                                } else {
                                    echo $customField->getFormattedValue();
                                }
                                echo '</span></li>';
                            }  ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"> <i class="fa fa-people-roof"></i> <?= gettext("Family Members") ?></h3>
                        <div class="card-tools pull-right">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i
                                    class="fa fa-minus"></i>
                            </button>

                        </div>
                    </div>
                    <div class="card-body row row-flex row-flex-wrap">
                        <?php foreach ($family->getPeople() as $person) { ?>
                            <div class="col-sm-6">
                                <div class="card card-primary">
                                    <div class="card-body box-profile">
                                        <a href="<?= $person->getViewURI()?>" ?>
                                            <img class="profile-user-img img-responsive img-circle initials-image"
                                                 src="<?= $person->getThumbnailURL() ?>">
                                            <h3 class="profile-username text-center"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>
                                        </a>
                                        <p class="text-muted text-center"><i
                                                class="fa fa-fw fa-<?= ($person->isMale() ? "male" : "female") ?>"></i> <?= $person->getFamilyRoleName() ?>
                                        </p>

                                        <p class="text-center">
                                            <a class="AddToPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                                                <button type="button" class="btn btn-xs btn-primary"><i class="fa fa-cart-plus"></i></button>
                                            </a>

                                            <a href="<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getID()?>" class="table-link">
                                                <button type="button" class="btn btn-xs btn-primary"><i class="fas fa-pen"></i></button>
                                            </a>
                                            <a class="delete-person" data-person_name="<?= $person->getFullName() ?>"
                                               data-person_id="<?= $person->getId() ?>" data-view="family">
                                                <button type="button" class="btn btn-xs btn-danger"><i class="fa-solid fa-trash-can"></i></button>
                                            </a>
                                        </p>
                                        <?php if ($person->getClsId()) { ?>
                                        <li class="list-group">
                                            <b>Classification:</b> <?= Classification::getName($person->getClsId()) ?>
                                        </li>
                                        <?php } ?>
                                        <ul class="list-group list-group-unbordered">
                                            <li class="list-group-item">
                                                <?php if (!empty($person->getHomePhone())) { ?>
                                                    <i class="fa fa-fw fa-phone"
                                                       title="<?= gettext("Home Phone") ?>"></i>(H) <?= $person->getHomePhone() ?>
                                                    <br/>
                                                <?php }
                                                if (!empty($person->getWorkPhone())) { ?>
                                                    <i class="fa fa-fw fa-briefcase"
                                                       title="<?= gettext("Work Phone") ?>"></i>(W) <?= $person->getWorkPhone() ?>
                                                    <br/>
                                                <?php }
                                                if (!empty($person->getCellPhone())) { ?>
                                                    <i class="fa fa-fw fa-mobile"
                                                       title="<?= gettext("Mobile Phone") ?>"></i>(M) <?= $person->getCellPhone() ?>
                                                    <br/>
                                                <?php }
                                                if (!empty($person->getEmail())) { ?>
                                                    <i class="fa fa-fw fa-envelope"
                                                       title="<?= gettext("Email") ?>"></i>(H) <?= $person->getEmail() ?>
                                                    <br/>
                                                <?php }
                                                if (!empty($person->getWorkEmail())) { ?>
                                                    <i class="fa fa-fw fa-inbox"
                                                       title="<?= gettext("Work Email") ?>"></i>(W) <?= $person->getWorkEmail() ?>
                                                    <br/>
                                                <?php }
                                                $formattedBirthday = $person->getFormattedBirthDate();
                                                if ($formattedBirthday) {?>
                                                <i class="fa fa-fw fa-birthday-cake"
                                                   title="<?= gettext("Birthday") ?>"></i>
                                                    <?= $formattedBirthday ?>  <?= $person->getAge() ?? sprintf('(%s)', gettext('not found')) ?>
                                                </i>
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
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-hashtag"></i> <?= gettext("Properties") ?></h3>
                <div class="card-tools pull-right">
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                    <button id="add-family-property" type="button" class="btn btn-box-tool" style="display: block;">
                        <i class="fa fa-plus-circle text-blue"></i>
                    </button>
                    <?php } ?>

                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">

                <div id="family-property-loading" class="col-xs-12 text-center">
                    <i class="btn btn-default btn-lrg ajax">
                        <i class="fa fa-spin fa-refresh"></i>&nbsp; <?= gettext("Loading") ?>
                    </i>
                </div>

                <div id="family-property-no-data" class="alert alert-warning" style="display: block;">
                    <i class="fa fa-question-circle fa-fw fa-lg"></i>
                    <span><?= gettext("No property assignments.") ?></span>
                </div>

                <table id="family-property-table" class="table table-striped table-bordered data-table" cellspacing="0" width="100%" style="display: block;">
                    <thead>
                        <tr>
                            <th width="50"></th>
                            <th width="250" class="text-center"><?= gettext("Name") ?></th>
                            <th class="text-center"><?= gettext("Value") ?></th>
                        </tr>
                    </thead>
                </table>
            <p/>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-map"></i> <?= gettext("Address") ?></h3>
                        <div class="card-tools pull-right">
                            <button type="button" class="btn btn-box-tool edit-family"><i class="fa fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <a href="http://maps.google.com/?q=<?= $familyAddress ?>"
                           target="_blank"><?= $familyAddress ?></a></span>
                        <p/>
                        <!-- Maps Start -->
                        <?php if (!empty(SystemConfig::getValue("sGoogleMapsRenderKey")) && !empty($family->getLatitude())) : ?>
                            <div class="border-right border-left">
                                <section id="map">
                                    <div id="map1"></div>
                                </section>
                            </div>
                            <!-- Map Scripts -->
                            <script
                                    src="//maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue("sGoogleMapsRenderKey") ?>&sensor=false"></script>
                            <script>
                                var LatLng = new google.maps.LatLng(<?= $family->getLatitude() ?>, <?= $family->getLongitude() ?>)
                            </script>
                            <script src="<?= SystemURLs::getRootPath() ?>/skin/js/Map.js"></script>
                            <style>
                                #map1 {
                                    height: 200px;
                                }
                            </style>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Maps End -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-history"></i> <?= gettext("Timeline") ?></h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <!-- timeline time label -->
                    <div class="time-label"><span class="bg-teal"><?= $curYear ?></span></div>
                    <!-- /.timeline-label -->
                    <!-- timeline item -->
                    <?php foreach ($familyTimeline as $item) {
                        if ($curYear != $item['year']) {
                            $curYear = $item['year']; ?>
                            <div class="time-label"><span class="bg-teal"><?= $curYear ?></span></div>
                            <?php
                        } ?>
                        <div class="timeline-item">
                            <!-- timeline icon -->
                            <i class="fa <?= $item['style'] ?>"></i>
                            <div class="timeline-item">
                                <span class="time">
                                    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled() && (isset($item["editLink"]) || isset($item["deleteLink"]))) {
                                        ?>
                                        <?php if (isset($item["editLink"])) { ?>
                                            <a href="<?= $item["editLink"] ?>"><button type="button" class="btn btn-xs btn-primary"><i class="fa fa-pen"></i></button></a>
                                        <?php }
                                        if (isset($item["deleteLink"])) { ?>
                                            <a href="<?= $item["deleteLink"] ?>"><button type="button" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button></a>
                                        <?php } ?>
                                        &nbsp;
                                        <?php
                                    } ?>
                                <i class="fa fa-clock"></i> <?= $item['datetime'] ?></span>
                                <?php if ($item['slim']) { ?>
                                    <h4 class="timeline-header">
                                        <?= $item['text'] ?> <?= gettext($item['header']) ?>
                                    </h4>
                                <?php } else { ?>
                                    <h3 class="timeline-header">
                                        <?php if (in_array('headerlink', $item)) {
                                            ?>
                                            <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                                            <?php
                                        } else {
                                            ?>
                                            <?= gettext($item['header']) ?>
                                            <?php
                                        } ?>
                                    </h3>

                                    <div class="timeline-body">
                                        <pre><?= $item['text'] ?></pre>
                                    </div>



                                <?php } ?>
                            </div>
                        </div>
                        <?php
                    } ?>
                    <!-- END timeline item -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    ?>
<div class="row">
    <div class="col-lg-12">
        <div class="row">
            <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-circle-dollar-to-slot"></i> <?= gettext("Pledges and Payments") ?></h3>
                    <div class="card-tools pull-right">
                        <input type="checkbox" id="ShowPledges" <?= AuthenticationManager::getCurrentUser()->isShowPledges() ? "checked" : "" ?>> <?= gettext("Show Pledges") ?>
                        <input type="checkbox" id="ShowPayments" <?= AuthenticationManager::getCurrentUser()->isShowPayments() ? "checked" : "" ?>> <?= gettext("Show Payments") ?>
                        <label for="ShowSinceDate"><?= gettext("Since") ?>:</label>
                        <input type="text" class="date-picker" id="ShowSinceDate"
                               value="<?= AuthenticationManager::getCurrentUser()->getShowSince() ?>" maxlength="10" id="ShowSinceDate" size="15">
                    </div>
                </div>
                <div class="card-body">
                    <table id="pledge-payment-v2-table" class="table table-striped table-bordered table-responsive data-table">
                        <tbody></tbody>
                    </table>
<?php } ?>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>




<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MemberView.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyView.js"></script>


<!-- Photos start -->
<div id="photoUploader"></div>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery-photo-uploader/PhotoUploader.js"></script>

<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog"
     aria-labelledby="delete-Image-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm Delete") ?></h4>
            </div>

            <div class="modal-body">
                <p><?= gettext("You are about to delete the profile photo, this procedure is irreversible.") ?></p>

                <p><?= gettext("Do you want to proceed?") ?></p>
            </div>


            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                        data-dismiss="modal"><?= gettext("Cancel") ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        window.CRM.photoUploader = $("#photoUploader").PhotoUploader({
            url: window.CRM.root + "/api/family/" + window.CRM.currentFamily + "/photo",
            maxPhotoSize: window.CRM.maxUploadSize,
            photoHeight: <?= SystemConfig::getValue("iPhotoHeight") ?>,
            photoWidth: <?= SystemConfig::getValue("iPhotoWidth") ?>,
            done: function (e) {
                location.reload();
            }
        });

        $(".edit-family").click(function () {
            window.location.href = window.CRM.root + '/FamilyEditor.php?FamilyID=' + window.CRM.currentFamily;
        });
    });
</script>
<!-- Photos end -->
<div class="modal fade" id="confirm-verify" tabindex="-1" role="dialog" aria-labelledby="confirm-verify-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"
                    id="confirm-verify-label"><?= gettext("Request Family Info Verification") ?></h4>
            </div>
            <div class="modal-body">
                <b><?= gettext("Select how do you want to request the family information to be verified") ?></b>
                <p>
                    <?php if (count($family->getEmails()) > 0) {
                        ?>
                <p><?= gettext("You are about to email copy of the family information to the following emails") ?>
                <ul>
                        <?php foreach ($family->getEmails() as $tmpEmail) { ?>
                        <li><?= $tmpEmail ?></li>
                        <?php } ?>
                </ul>
                </p>
            </div>
                        <?php
                    } ?>
            <div class="modal-footer text-center">
                <?php if (count($family->getEmails()) > 0 && !empty(SystemConfig::getValue('sSMTPHost'))) {
                    ?>
                    <button type="button" id="onlineVerify"
                            class="btn btn-warning warning"><i
                            class="fa fa-envelope"></i> <?= gettext("Online Verification") ?>
                    </button>
                    <?php
                } ?>
                <button type="button" id="verifyURL"
                        class="btn btn-default"><i class="fa fa-chain"></i> <?= gettext("URL") ?></button>
                <button type="button" id="verifyDownloadPDF"
                        class="btn btn-info"><i class="fa fa-download"></i> <?= gettext("PDF") ?></button>
                <button type="button" id="verifyNow"
                        class="btn btn-success"><i class="fa fa-check"></i> <?= gettext("Verified In Person") ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
