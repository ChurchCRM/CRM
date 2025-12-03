<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Utils\InputUtils;

$sPageTitle =  $family->getName() . " - " . gettext("Family");
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$curYear = (new DateTime())->format("Y");
$familyAddress = $family->getAddress();
$mailchimp = new MailChimpService();

$iFYID = CurrentFY();
if (array_key_exists('idefaultFY', $_SESSION)) {
    $iFYID = MakeFYString((int) $_SESSION['idefaultFY']);
}

$memberCount = count($family->getPeople());
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
    <!-- LEFT COLUMN: Photo, Address, Metadata -->
    <div class="col-lg-4">
        <!-- Family Photo Card -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title m-0"><?= $family->getName() ?> [<?= $family->getId() ?>]</h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-box-tool edit-family"><i class="fa-solid fa-pen"></i>
                    </button>
                </div>
            </div>
            <div class="card-body text-center">
                <div class="image-container position-relative d-inline-block">
                    <img data-image-entity-type="family" 
                         data-image-entity-id="<?= $family->getId() ?>" 
                         class="photo-large"/>
                    <div class="position-absolute w-100 text-center" style="bottom: 10px;">
                        <a id="view-larger-image-btn" href="#" class="btn btn-sm btn-primary mr-1" title="<?= gettext("View Photo") ?>">
                            <i class="fa-solid fa-search-plus"></i>
                        </a>
                        <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) : ?>
                            <a id="uploadImageButton" href="#" class="btn btn-sm btn-info mr-1"
                               title="<?= gettext("Upload Photo") ?>">
                                <i class="fa-solid fa-camera"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#confirm-delete-image"
                               title="<?= gettext("Delete Photo") ?>">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge badge-<?= $family->isActive() ? 'success' : 'secondary' ?> mr-1">
                        <i class="fa-solid fa-circle"></i> <?= $family->isActive() ? gettext('Active') : gettext('Inactive') ?>
                    </span>
                    <span class="badge badge-info mr-1">
                        <i class="fa-solid fa-users"></i> <?= $memberCount ?> <?= $memberCount == 1 ? gettext('Member') : gettext('Members') ?>
                    </span>
                    <?php if ($family->getEnvelope()) { ?>
                        <span class="badge badge-primary">
                            <i class="fa-solid fa-envelope"></i> #<?= $family->getEnvelope() ?>
                        </span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Family Actions -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-bolt"></i> <?= gettext("Actions") ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-2">
                        <a class="btn btn-default btn-block" href="#" data-toggle="modal" data-target="#confirm-verify">
                            <i class="fa-solid fa-clipboard-check"></i><br><?= gettext('Verify') ?>
                        </a>
                    </div>
                    <div class="col-6 mb-2">
                        <a class="btn btn-default btn-block AddToCart" id="AddFamilyToCart" data-cart-id="<?= $family->getId() ?>" data-cart-type="family">
                            <i class="fa-solid fa-cart-plus"></i><br><?= gettext('Cart') ?>
                        </a>
                    </div>
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                    <div class="col-6 mb-2">
                        <a class="btn btn-warning btn-block" id="activateDeactivate">
                            <i class="fa-solid fa-power-off"></i><br><?= ($family->isActive() ? gettext('Deactivate') : gettext('Activate')) ?>
                        </a>
                    </div>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                    <div class="col-6 mb-2">
                        <a class="btn btn-danger btn-block" id="deleteFamilyBtn" href="<?= SystemURLs::getRootPath() ?>/SelectDelete.php?FamilyID=<?=$family->getId()?>">
                            <i class="fa-solid fa-trash-can"></i><br><?= gettext('Delete') ?>
                        </a>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Address Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-map"></i> <?= gettext("Address") ?></h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-box-tool edit-family"><i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <a href="http://maps.google.com/?q=<?= $familyAddress ?>"
                   target="_blank"><?= $familyAddress ?></a>
                <!-- Maps Start -->
                <?php if (!empty(SystemConfig::getValue("sGoogleMapsRenderKey")) && !empty($family->getLatitude())) : ?>
                    <div class="border-right border-left mt-2">
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

        <!-- Contact Info Card -->
        <div class="card card-primary mb-3">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-address-book"></i> <?= gettext("Contact Info") ?></h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-box-tool edit-family"><i
                            class="fa-solid fa-pen"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <ul class="fa-ul">
                    <?php
                    if (!SystemConfig::getBooleanValue("bHideFamilyNewsletter")) { /* Newsletter can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa-solid fa-newspaper"></i><?= gettext("Send Newsletter") ?>:
                            <span class="<?= ($family->isSendNewsletter() ? "text-success" : "text-danger") ?>"><i
                                    class="fa-solid fa-<?= ($family->isSendNewsletter() ? "check" : "times") ?>"></i></span>
                        </li>
                        <?php
                    }
                    if (!SystemConfig::getBooleanValue("bHideWeddingDate") && !empty($family->getWeddingdate())) { /* Wedding Date can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa-solid fa-magic"></i><?= gettext("Wedding Date") ?>:
                            <span><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?></span></li>
                        <?php
                    }
                    if (SystemConfig::getValue("bUseDonationEnvelopes")) {
                        ?>
                        <li><i class="fa-li fa-solid fa-envelope"></i><?= gettext("Envelope Number") ?>
                            <span><?= $family->getEnvelope() ?></span>
                        </li>
                        <?php
                    }
                    if (!empty($family->getHomePhone())) {
                        ?>
                        <li><i class="fa-li fa-solid fa-phone"></i><?= gettext("Home Phone") ?>: <span><a
                                    href="tel:<?= $family->getHomePhone() ?>"><?= $family->getHomePhone() ?></a></span>
                        </li>
                        <?php
                    }
                    if ($family->getWorkPhone() !== "") {
                        ?>
                        <li><i class="fa-li fa-solid fa-building"></i><?= gettext("Work Phone") ?>: <span><a
                                    href="tel:<?= $family->getWorkPhone() ?>"><?= $family->getWorkPhone() ?></a></span>
                        </li>
                        <?php
                    }
                    if ($family->getCellPhone() !== "") {
                        ?>
                        <li><i class="fa-li fa-solid fa-mobile"></i><?= gettext("Mobile Phone") ?>: <span><a
                                    href="tel:<?= $family->getCellPhone() ?>"><?= $family->getCellPhone() ?></a></span>
                        </li>
                        <?php
                    }
                    if ($family->getEmail() !== "") {
                        ?>
                        <li><i class="fa-li fa-solid fa-envelope"></i><?= gettext("Email") ?>:<a
                                href="mailto:<?= $family->getEmail() ?>">
                                <span><?= $family->getEmail() ?></span></a></li>
                        <?php if ($mailchimp->isActive()) { ?>
                         <li><i class="fa-li fa-regular fa-paper-plane"></i><?= gettext("Mailchimp") ?>:
                         <span id="<?= md5($family->getEmail())?>">... <?= gettext("loading")?> ...</span></li>
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

        <!-- Properties Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-hashtag"></i> <?= gettext("Properties") ?></h3>
                <div class="card-tools pull-right">
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                    <button id="add-family-property" type="button" class="btn btn-box-tool d-block">
                        <i class="fa-solid fa-plus-circle text-blue"></i>
                    </button>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <div id="family-property-loading" class="w-100 text-center">
                    <i class="btn btn-secondary btn-lg ajax">
                        <i class="fa-solid fa-spinner fa-spin"></i>&nbsp; <?= gettext("Loading") ?>
                    </i>
                </div>

                <div id="family-property-no-data" class="alert alert-warning" style="display: block;">
                    <i class="fa-solid fa-question-circle fa-fw fa-lg"></i>
                    <span><?= gettext("No property assignments.") ?></span>
                </div>

                <div class="table-responsive">
                    <table id="family-property-table" class="table table-striped table-bordered data-table">
                        <thead>
                            <tr>
                                <th width="50"></th>
                                <th width="250" class="text-center"><?= gettext("Name") ?></h3></th>
                                <th class="text-center"><?= gettext("Value") ?></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Timeline, Family Members -->
    <div class="col-lg-8">
        <!-- Family Navigation -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= SystemURLs::getRootPath()?>/v2/family" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left"></i> <?= gettext('Back to Families'); ?>
                    </a>
                    <div class="btn-group" role="group" aria-label="<?= gettext('Family Navigation'); ?>">
                        <a id="lastFamily" class="btn btn-outline-primary">
                            <i class="fa-solid fa-chevron-left"></i> <?= gettext('Previous'); ?>
                        </a>
                        <a id="nextFamily" class="btn btn-outline-primary">
                            <?= gettext('Next'); ?> <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Card -->
        <div class="card card-outline card-info collapsed-card mb-3">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-history"></i> <?= gettext("Timeline") ?></h3>
                <div class="card-tools pull-right">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" style="display: none;">
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
                                            <a href="<?= $item["editLink"] ?>"><button type="button" class="btn btn-sm btn-warning"><i class="fa-solid fa-pen fa-sm"></i></button></a>
                                        <?php }
                                        if (isset($item["deleteLink"])) { ?>
                                            <a href="<?= $item["deleteLink"] ?>"><button type="button" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash fa-sm"></i></button></a>
                                        <?php } ?>
                                        &nbsp;
                                        <?php
                                    } ?>
                                <i class="fa-solid fa-clock"></i> <?= $item['datetime'] ?></span>
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

        <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) {
            $familyNotes = [];
            foreach ($familyTimeline as $item) {
                if ($item['type'] === 'note') {
                    $familyNotes[] = $item;
                }
            }
            $latestNote = !empty($familyNotes) ? $familyNotes[0] : null;
            ?>
            <!-- Notes Card -->
            <div class="card card-outline card-secondary collapsed-card mb-3">
                <div class="card-header">
                    <h3 class="card-title m-0"><i class="fa-solid fa-sticky-note"></i> <?= gettext("Notes") ?></h3>
                    <div class="card-tools d-flex align-items-center">
                        <a class="btn btn-outline-success btn-sm mr-2" href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId() ?>" title="<?= gettext('Add a Note') ?>">
                            <i class="fa-solid fa-plus"></i> <?= gettext('Add Note') ?>
                        </a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body" style="display: none;">
                    <?php if ($latestNote) { ?>
                        <div class="mb-3 border rounded p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong><?= gettext('Latest Note') ?></strong>
                                <small class="text-muted"><?= date('Y-m-d H:i', strtotime($latestNote['datetime'])) ?></small>
                            </div>
                            <p class="mb-1"><?= InputUtils::escapeHTML($latestNote['text']) ?></p>
                            <small class="text-muted"><i class="fa-solid fa-user"></i> <?= InputUtils::escapeHTML($latestNote['header']) ?></small>
                        </div>
                    <?php } ?>
                    <?php if (empty($familyNotes)) { ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle fa-fw fa-lg"></i>
                            <span><?= gettext('No notes have been added for this family.') ?></span>
                        </div>
                    <?php } else { ?>
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 1%; white-space: nowrap;"><?= gettext('Date') ?></th>
                                    <th><?= gettext('Note') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($familyNotes as $note) { ?>
                                    <tr>
                                        <td style="width: 1%; white-space: nowrap; vertical-align: top;">
                                            <div style="text-align: center;">
                                                <i class="fa-solid fa-calendar"></i><br>
                                                <?= date('Y-m-d', strtotime($note['datetime'])) ?><br>
                                                <small class="text-muted"><?= date('h:i A', strtotime($note['datetime'])) ?></small>
                                                <div style="margin-top: 10px;">
                                                    <?php if (isset($note['editLink']) && $note['editLink']) { ?>
                                                        <a href="<?= $note['editLink'] ?>" class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>">
                                                            <i class="fa-solid fa-pen fa-sm"></i>
                                                        </a>
                                                    <?php }
                                                    if (isset($note['deleteLink'])) { ?>
                                                        <a href="<?= $note['deleteLink'] ?>" class="btn btn-sm btn-danger" title="<?= gettext('Delete') ?>">
                                                            <i class="fa-solid fa-trash fa-sm"></i>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="width: 99%; vertical-align: top;">
                                            <div style="margin-bottom: 8px;">
                                                <?= InputUtils::escapeHTML($note['text']) ?>
                                            </div>
                                            <small class="text-muted"><i class="fa-solid fa-user"></i> <?= InputUtils::escapeHTML($note['header']) ?></small>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <!-- Family Members Card -->
        <div class="card card-outline card-success mb-3">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-people-roof"></i> <?= gettext("Family Members") ?></h3>
                <div class="card-tools d-flex align-items-center">
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                        <a class="btn btn-outline-success btn-sm mr-2" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?FamilyID=<?= $family->getId() ?>" title="<?= gettext('Add New Member') ?>">
                            <i class="fa-solid fa-user-plus"></i> <?= gettext('Add Member') ?>
                        </a>
                    <?php } ?>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                <?php foreach ($family->getPeople() as $person) { ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card card-primary h-100">
                            <div class="card-body box-profile">
                                <div class="text-center">
                                    <a href="<?= $person->getViewURI()?>" ?>
                                        <img class="photo-medium"
                                             data-image-entity-type="person" 
                                             data-image-entity-id="<?= $person->getId() ?>">
                                        <h3 class="profile-username"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>
                                    </a>
                                    <p class="text-muted"><i
                                            class="fa-solid fa-<?= ($person->isMale() ? "person" : "person-dress") ?>"></i> <?= $person->getFamilyRoleName() ?>
                                    </p>
                                </div>

                                <p class="text-center">
                                    <?php 
                                        $isInCart = isset($_SESSION['aPeopleCart']) && in_array($person->getId(), $_SESSION['aPeopleCart'], false);
                                    ?>
                                    <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $person->getID()?>" class="btn-link">
                                        <button type="button" class="btn btn-sm btn-info" title="<?= gettext('View') ?>"><i class="fa-solid fa-eye fa-sm"></i></button>
                                    </a>
                                    
                                    <a href="<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getID()?>" class="btn-link">
                                        <button type="button" class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen fa-sm"></i></button>
                                    </a>
                                    
                                    <?php if ($isInCart) { ?>
                                        <button type="button" class="RemoveFromCart btn btn-sm btn-danger" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Remove from Cart') ?>"><i class="fa-solid fa-times fa-sm"></i></button>
                                    <?php } else { ?>
                                        <button type="button" class="AddToCart btn btn-sm btn-primary" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Add to Cart') ?>"><i class="fa-solid fa-cart-plus fa-sm"></i></button>
                                    <?php } ?>
                                    
                                    <a class="delete-person" data-person_name="<?= $person->getFullName() ?>"
                                       data-person_id="<?= $person->getId() ?>" data-view="family">
                                        <button type="button" class="btn btn-sm btn-danger" title="<?= gettext('Delete') ?>"><i class="fa-solid fa-trash-can fa-sm"></i></button>
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
                                            <i class="fa-solid fa-phone"
                                               title="<?= gettext("Home Phone") ?>"></i>(H) <?= $person->getHomePhone() ?>
                                            <br/>
                                        <?php }
                                        if (!empty($person->getWorkPhone())) { ?>
                                            <i class="fa-solid fa-briefcase"
                                               title="<?= gettext("Work Phone") ?>"></i>(W) <?= $person->getWorkPhone() ?>
                                            <br/>
                                        <?php }
                                        if (!empty($person->getCellPhone())) { ?>
                                            <i class="fa-solid fa-mobile"
                                               title="<?= gettext("Mobile Phone") ?>"></i>(M) <?= $person->getCellPhone() ?>
                                            <br/>
                                        <?php }
                                        if (!empty($person->getEmail())) { ?>
                                            <i class="fa-solid fa-envelope"
                                               title="<?= gettext("Email") ?>"></i>(H) <?= $person->getEmail() ?>
                                            <br/>
                                        <?php }
                                        if (!empty($person->getWorkEmail())) { ?>
                                            <i class="fa-solid fa-inbox"
                                               title="<?= gettext("Work Email") ?>"></i>(W) <?= $person->getWorkEmail() ?>
                                            <br/>
                                        <?php }
                                        $formattedBirthday = $person->getFormattedBirthDate();
                                        if ($formattedBirthday) {?>
                                        <i class="fa-solid fa-birthday-cake"
                                           title="<?= gettext("Birthday") ?>"></i>
                                            <?= $formattedBirthday ?>  <?= $person->getAge() ?? sprintf('(%s)', gettext('not found')) ?>
                                        </i>
                                        <?php } ?>
                                    </li>
                                </ul>

                            </div>
                        </div>
                    </div>
                <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title m-0"><i class="fa-solid fa-circle-dollar-to-slot"></i> <?= gettext("Pledges and Payments") ?></h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <a class="btn btn-success btn-block" href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=v2/family/<?= $family->getId() ?>&PledgeOrPayment=Pledge">
                            <i class="fa-solid fa-hand-holding-dollar"></i> <?= gettext('Add Pledge') ?>
                        </a>
                    </div>
                    <div class="col-md-6 mb-2">
                        <a class="btn btn-success btn-block" href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=v2/family/<?= $family->getId() ?>&PledgeOrPayment=Payment">
                            <i class="fa-solid fa-money-bill-wave"></i> <?= gettext('Add Payment') ?>
                        </a>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-center">
                            <label class="mb-0 mr-3">
                                <input type="checkbox" id="ShowPledges" <?= AuthenticationManager::getCurrentUser()->isShowPledges() ? "checked" : "" ?>> 
                                <?= gettext("Show Pledges") ?>
                            </label>
                            <label class="mb-0 mr-3">
                                <input type="checkbox" id="ShowPayments" <?= AuthenticationManager::getCurrentUser()->isShowPayments() ? "checked" : "" ?>> 
                                <?= gettext("Show Payments") ?>
                            </label>
                            <label class="mb-0 mr-2"><?= gettext("Since") ?>:</label>
                            <input type="text" class="date-picker form-control form-control-sm" id="ShowSinceDate" style="width: 150px;"
                                   value="<?= AuthenticationManager::getCurrentUser()->getShowSince() ?>" maxlength="10">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="pledge-payment-v2-table" class="table table-striped table-bordered data-table" style="width: 100%;">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- Photo uploader bundle - loaded only on this page -->
<link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/photo-uploader.min.css">
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/photo-uploader.min.js"></script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MemberView.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyView.js"></script>

<!-- Photos start -->
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
                <button type="button" class="btn btn-secondary"
                        data-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Copy photo uploader function from temporary storage to window.CRM
    // This must happen after Header-function.php initializes window.CRM
    if (window._CRM_createPhotoUploader) {
        window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
    } else {
        console.error('Photo uploader function not found in window._CRM_createPhotoUploader');
    }

    // Initialize photo uploader when window loads
    window.addEventListener('load', function() {
        if (typeof window.CRM.createPhotoUploader !== 'function') {
            console.error('window.CRM.createPhotoUploader is not a function');
            return;
        }
        
        window.CRM.photoUploader = window.CRM.createPhotoUploader({
            uploadUrl: window.CRM.root + "/api/family/" + window.CRM.currentFamily + "/photo",
            maxFileSize: window.CRM.maxUploadSizeBytes,
            photoHeight: <?= Photo::PHOTO_HEIGHT ?>,
            photoWidth: <?= Photo::PHOTO_WIDTH ?>,
            onComplete: function() {
                location.reload();
            }
        });
    });

    // Set up click handlers (use event delegation)
    $(document).on('click', '#uploadImageButton', function(e) {
        e.preventDefault();
        if (window.CRM && window.CRM.photoUploader) {
            window.CRM.photoUploader.show();
        } else {
            console.error('Photo uploader not initialized!');
        }
    });

    $(document).on('click', '.edit-family', function() {
        window.location.href = window.CRM.root + '/FamilyEditor.php?FamilyID=' + window.CRM.currentFamily;
    });
</script>
<!-- Photos end -->

<!-- FAB Container for Family View -->
<div id="fab-family-view" class="fab-container fab-family-view">
    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId() ?>" class="fab-button fab-note" aria-label="<?= gettext('Add a Note') ?>">
        <span class="fab-label"><?= gettext('Add a Note') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-sticky-note"></i>
        </div>
    </a>
    <?php } ?>
    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>" class="fab-button fab-edit" aria-label="<?= gettext('Edit Family') ?>">
        <span class="fab-label"><?= gettext('Edit Family') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-pen"></i>
        </div>
    </a>
    <?php } ?>
</div>

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
                            class="fa-solid fa-envelope"></i> <?= gettext("Online Verification") ?>
                    </button>
                    <?php
                } ?>
                <button type="button" id="verifyURL"
                        class="btn btn-secondary"><i class="fa-solid fa-link"></i> <?= gettext("URL") ?></button>
                <button type="button" id="verifyDownloadPDF"
                        class="btn btn-info"><i class="fa-solid fa-download"></i> <?= gettext("PDF") ?></button>
                <button type="button" id="verifyNow"
                        class="btn btn-success"><i class="fa-solid fa-check"></i> <?= gettext("Verified In Person") ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
