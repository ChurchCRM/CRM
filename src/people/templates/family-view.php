<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';

//Set the page title
$sPageTitle = gettext("Family View") . " - " . $family->getName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';

/**
 * @var $sessionUser \ChurchCRM\User
 */
$sessionUser = $_SESSION['user'];

$familyAddress = $family->getAddress();
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentFamily = <?= $family->getId() ?>;
    window.CRM.currentActive = <?= $family->isActive() ? "true" : "false" ?>;
</script>


<div id="family-deactivated" class="alert alert-warning hide">
    <strong><?= gettext("This Family is Deactivated") ?> </strong>
</div>

<div class="row">
    <div class="col-lg-2">
        <div class="box box-primary">
            <div class="box-body">
                <div class="image-container">
                    <img src="<?= SystemURLs::getRootPath() ?>/api/families/<?= $family->getId() ?>/photo"
                         class="img-responsive profile-user-img profile-family-img"/>
                    <div class="after">
                        <div class="buttons">
                            <a id="view-larger-image-btn" href="#" title="<?= gettext("View Photo") ?>">
                                <i class="fa fa-search-plus"></i>
                            </a>
                            <?php if ($sessionUser->isEditRecordsEnabled()): ?>
                                &nbsp;
                                <a href="#" data-toggle="modal" data-target="#upload-image"
                                   title="<?= gettext("Upload Photo") ?>">
                                    <i class="fa fa-camera"></i>
                                </a>&nbsp;
                                <a href="#" data-toggle="modal" data-target="#confirm-delete-image"
                                   title="<?= gettext("Delete Photo") ?>">
                                    <i class="fa fa-trash-o"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box box-primary">
            <div class="box-body">
                <ul class="fa-ul">

                    <?php
                    if (!SystemConfig::getBooleanValue("bHideFamilyNewsletter")) { /* Newsletter can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa fa-hacker-news"></i><?= gettext("Send Newsletter") ?>:
                            <span style="color:<?= ($fam_SendNewsLetter == "TRUE" ? "green" : "red") ?>"><i
                                    class="fa fa-<?= (!$family->getSendNewsletter() ? "check" : "times") ?>"></i></span>
                        </li>
                        <?php
                    }
                    if (!SystemConfig::getBooleanValue("bHideWeddingDate") && !empty($family->getWeddingdate())) { /* Wedding Date can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa fa-magic"></i><?= gettext("Wedding Date") ?>:
                            <span><?= FormatDate($family->getWeddingdate(), false) ?></span></li>
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
                        <?php /**if ($mailchimp->isActive()) {
                         * ?>
                         * <li><i class="fa-li fa fa-send"></i><?= gettext("Email") ?>:
                         * <span><?= $mailchimp->isEmailInMailChimp($family->getEmail()) ?></span>
                         * </a></li>
                         * <?php
                         * }*/
                    }

                    /**
                     * // Display the left-side custom fields
                     * while ($Row = mysqli_fetch_array($rsFamCustomFields)) {
                     * extract($Row);
                     * if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
                     * $currentData = trim($aFamCustomData[$fam_custom_Field]);
                     * if ($type_ID == 11) {
                     * $fam_custom_Special = $sPhoneCountry;
                     * }
                     * echo "<li><i class=\"fa-li fa fa-tag\"></i>" . $fam_custom_Name . ": <span>" . displayCustomField($type_ID, $currentData, $fam_custom_Special) . "</span></li>";
                     * }
                     * } */
                    ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="box">
            <br/>
            <a class="btn btn-app" href="#" data-toggle="modal" data-target="#confirm-verify"><i
                    class="fa fa-check-square"></i> <?= gettext("Verify Info") ?></a>
            <a class="btn btn-app bg-olive" href="PersonEditor.php?FamilyID=<?= $iFamilyID ?>"><i
                    class="fa fa-plus-square"></i> <?= gettext('Add New Member') ?></a>
            <?php if (($previous_id > 0)) {
                ?>
                <a class="btn btn-app" href="FamilyView.php?FamilyID=<?= $previous_id ?>"><i
                        class="fa fa-hand-o-left"></i><?= gettext('Previous Family') ?></a>
                <?php
            } ?>
            <a class="btn btn-app btn-danger" role="button" href="FamilyList.php"><i
                    class="fa fa-list-ul"></i><?= gettext('Family List') ?></a>
            <?php if (($next_id > 0)) {
                ?>
                <a class="btn btn-app" role="button" href="FamilyView.php?FamilyID=<?= $next_id ?>"><i
                        class="fa fa-hand-o-right"></i><?= gettext('Next Family') ?> </a>
                <?php
            } ?>
            <?php if ($_SESSION['bDeleteRecords']) {
                ?>
                <a class="btn btn-app bg-maroon" href="SelectDelete.php?FamilyID=<?= $iFamilyID ?>"><i
                        class="fa fa-trash-o"></i><?= gettext('Delete this Family') ?></a>
                <?php
            } ?>
            <br/>

            <?php
            if ($_SESSION['bNotes']) {
                ?>
                <a class="btn btn-app" href="NoteEditor.php?FamilyID=<?= $iFamilyID ?>"><i
                        class="fa fa-sticky-note"></i><?= gettext("Add a Note") ?></a>
                <?php
            } ?>
            <a class="btn btn-app" id="AddFamilyToCart" data-familyid="<?= $iFamilyID ?>"> <i
                    class="fa fa-cart-plus"></i> <?= gettext("Add All Family Members to Cart") ?></a>


            <?php if ($bOkToEdit) {
                ?>
                <button class="btn btn-app bg-orange" id="activateDeactivate">
                    <i class="fa <?= (empty($fam_DateDeactivated) ? 'fa-times-circle-o' : 'fa-check-circle-o') ?> "></i><?php echo((empty($fam_DateDeactivated) ? _('Deactivate') : _('Activate')) . _(' this Family')); ?>
                </button>
                <?php
            } ?>
        </div>
    </div>
</div>
<div class="row col-lg-12">
    <div class="box">
        <div class="box-header">
            <i class="fa fa-map"></i>
            <h3 class="box-title"><?= gettext("Address") ?></h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                </button>
            </div>
        </div>
        <div class="box-body">
            <a href="http://maps.google.com/?q=<?= $familyAddress ?>"
               target="_blank"><?= $familyAddress ?></a></span>
            <p/>
            <!-- Maps Start -->
            <?php if (!empty($family->getLatitude())) : ?>
                <div class="border-right border-left">
                    <section id="map">
                        <div id="map1"></div>
                    </section>
                </div>
                <!-- Map Scripts -->
                <script
                    src="//maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue("sGoogleMapKey") ?>&sensor=false"></script>
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
<!-- Maps End -->


<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyView.js"></script>


<!-- Photos start -->
<div id="photoUploader"></div>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery-photo-uploader/PhotoUploader.js"></script>

<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label"
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
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.photoUploader = $("#photoUploader").PhotoUploader({
        url: window.CRM.root + "/api/families/" + window.CRM.currentFamily + "/photo",
        maxPhotoSize: window.CRM.maxUploadSize,
        photoHeight: <?= SystemConfig::getValue("iPhotoHeight") ?>,
        photoWidth: <?= SystemConfig::getValue("iPhotoWidth") ?>,
        done: function (e) {
            location.reload();
        }
    });
</script>
<!-- Photos end -->

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
