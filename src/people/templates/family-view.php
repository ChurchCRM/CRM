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
$curYear = (new DateTime)->format("Y");
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
    <div class="col-lg-6">
        <div class="row">
            <div class="col-lg-4">
                <div class="box box-primary">
                    <div class="box-header">
                        <i class="fa fa-info"></i>
                        <h3 class="box-title"><?= $family->getName() ?></h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" id="edit-family-name"><i
                                    class="fa fa-edit"></i>
                            </button>
                        </div>
                    </div>
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
            <div class="col-lg-8">
                <div class="box box-primary">
                    <div class="box-header">
                        <i class="fa fa-id-badge"></i>
                        <h3 class="box-title"><?= gettext("Properties") ?></h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" id="edit-family-properties"><i
                                    class="fa fa-edit"></i>
                            </button>
                        </div>
                    </div>
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
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="box">
                    <br/>
                    <a class="btn btn-app" href="#" data-toggle="modal" data-target="#confirm-verify"><i
                            class="fa fa-check-square"></i> <?= gettext("Verify Info") ?></a>
                    <a class="btn btn-app bg-olive"
                       href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?FamilyID=<?= $family->getId() ?>"><i
                            class="fa fa-plus-square"></i> <?= gettext('Add New Member') ?></a>
                    <?php if (($previous_id > 0)) {
                        ?>
                        <a class="btn btn-app"
                           href="<?= SystemURLs::getRootPath() ?>/FamilyView.php?FamilyID=<?= $previous_id ?>"><i
                                class="fa fa-hand-o-left"></i><?= gettext('Previous Family') ?></a>
                        <?php
                    } ?>
                    <a class="btn btn-app btn-danger" role="button"
                       href="<?= SystemURLs::getRootPath() ?>/FamilyList.php"><i
                            class="fa fa-list-ul"></i><?= gettext('Family List') ?></a>
                    <?php if (($next_id > 0)) {
                        ?>
                        <a class="btn btn-app" role="button"
                           href="<?= SystemURLs::getRootPath() ?>/FamilyView.php?FamilyID=<?= $next_id ?>"><i
                                class="fa fa-hand-o-right"></i><?= gettext('Next Family') ?> </a>
                        <?php
                    } ?>
                    <?php if ($_SESSION['bDeleteRecords']) {
                        ?>
                        <a class="btn btn-app bg-maroon"
                           href="<?= SystemURLs::getRootPath() ?>/SelectDelete.php?FamilyID=<?= $family->getId() ?>"><i
                                class="fa fa-trash-o"></i><?= gettext('Delete this Family') ?></a>
                        <?php
                    } ?>


                    <?php
                    if ($_SESSION['bNotes']) {
                        ?>
                        <a class="btn btn-app"
                           href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId() ?>"><i
                                class="fa fa-sticky-note"></i><?= gettext("Add a Note") ?></a>
                        <?php
                    } ?>
                    <a class="btn btn-app" id="AddFamilyToCart" data-familyid="<?= $family->getId() ?>"> <i
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

        <div class="row">
            <div class="col-lg-12">
                <div class="box">
                    <div class="box-header">
                        <i class="fa fa-map"></i>
                        <h3 class="box-title"><?= gettext("Address") ?></h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" id="edit-family-address"><i
                                    class="fa fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                                    class="fa fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-box-tool" data-widget="remove"><i
                                    class="fa fa-times"></i>
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
        </div>
        <!-- Maps End -->

        <div class="row">
            <div class="col-lg-12">
                <div class="box">
                    <div class="box-header">
                        <i class="fa fa-group"></i>
                        <h3 class="box-title"><?= gettext("Family Members") ?></h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                                    class="fa fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-box-tool" data-widget="remove"><i
                                    class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <?php foreach ($family->getPeople() as $person) { ?>
                            <div class="col-sm-4">
                                <div class="box box-primary">
                                    <div class="box-body box-profile">
                                        <img class="profile-user-img img-responsive img-circle initials-image"
                                             src="data:image/png;base64,<?= base64_encode($person->getPhoto()->getThumbnailBytes()) ?>">

                                        <h3 class="profile-username text-center"><?= $person->getTitle() ?> <?= $person->getFullName() ?></h3>

                                        <p class="text-muted text-center"><i
                                                class="fa fa-fw fa-<?= ($person->isMale() ? "male" : "female") ?>"></i> <?= $person->getFamilyRoleName() ?>
                                        </p>

                                        <p class="text-center">
                                            <a class="AddToPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                                            <span class="fa-stack">
                                                <i class="fa fa-square fa-stack-2x"></i>
                                                <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
                                            </span>
                                            </a>

                                            <a href="PersonEditor.php?PersonID=<?= $person->getId() ?>" class="table-link">
                                            <span class="fa-stack">
                                                <i class="fa fa-square fa-stack-2x"></i>
                                                <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                                            </span>
                                            </a>
                                            <a class="delete-person" data-person_name="<?= $person->getFullName() ?>"
                                               data-person_id="<?= $person->getId() ?>" data-view="family">
                                            <span class="fa-stack">
                                                <i class="fa fa-square fa-stack-2x"></i>
                                                <i class="fa fa-trash-o fa-stack-1x fa-inverse"></i>
                                            </span>
                                            </a>
                                        </p>

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
                                                    <i class="fa fa-fw fa-envelope-o"
                                                       title="<?= gettext("Work Email") ?>"></i>(W) <?= $person->getWorkEmail() ?>
                                                    <br/>
                                                <?php } ?>
                                                <i class="fa fa-fw fa-birthday-cake"
                                                   title="<?= gettext("Birthday") ?>"></i>
                                                <?php if ($person->hideAge()) { ?>
                                                    <?= $person->getBirthDate()->format("M d") ?>
                                                    <i class="fa fa-fw fa-eye-slash"
                                                       title="<?= gettext("Age Hidden") ?>"></i>
                                                <?php } else { ?>
                                                    <?= $person->getBirthDate()->format("M d Y") ?>
                                                <?php } ?>
                                                <br/>
                                            </li>
                                            <li class="list-group-item">
                                                <?php
                                                $classification = "";
                                                /*$cls = ListOptionQuery::create()->filterById(1)->filterByOptionId($person->getClsId())->findOne();
                                                if (!empty($cls)) {
                                                    $classification = $cls->getOptionName();
                                                }
                                                */ ?>
                                                <b>Classification:</b> <?= $classification ?>
                                            </li>
                                            <?php if (count($person->getPerson2group2roleP2g2rs()) > 0) { ?>
                                                <li class="list-group-item">
                                                    <h4>Groups</h4>
                                                    <?php foreach ($person->getPerson2group2roleP2g2rs() as $groupMembership) {
                                                        if ($groupMembership->getGroup() != null) {
                                                            $listOption = ListOptionQuery::create()->filterById($groupMembership->getGroup()->getRoleListId())->filterByOptionId($groupMembership->getRoleId())->findOne()->getOptionName();
                                                            ?>
                                                            <b><?= $groupMembership->getGroup()->getName() ?></b>: <span
                                                                class="pull-right"><?= $listOption ?></span><br/>
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
        </div>
    </div>
    <div class="col-lg-6">
        <div class="box">
            <div class="box-header">
                <i class="fa fa-history"></i>
                <h3 class="box-title"><?= gettext("Timeline") ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                            class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i
                            class="fa fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="box-body">
                <ul class="timeline">
                    <!-- timeline time label -->
                    <li class="time-label"><span class="bg-teal"><?= $curYear ?></span></li>
                    <!-- /.timeline-label -->

                    <!-- timeline item -->
                    <?php foreach ($familyTimeline as $item) {
                        if ($curYear != $item['year']) {
                            $curYear = $item['year']; ?>
                            <li class="time-label"><span class="bg-green"><?= $curYear ?></span></li>
                            <?php
                        } ?>
                        <li>
                            <!-- timeline icon -->
                            <i class="fa <?= $item['style'] ?>"></i>
                            <div class="timeline-item">
                                <span class="time">
                                    <?php if ($sessionUser->isNotesEnabled() && (isset($item["editLink"]) || isset($item["deleteLink"]))) {
                                    ?>
                                        <?php if (isset($item["editLink"])) { ?>
                                            <a href="<?= $item["editLink"] ?>"><button type="button" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></button></a>
                                        <?php }
                                        if (isset($item["deleteLink"])) { ?>
                                            <a href="<?= $item["deleteLink"] ?>"><button type="button" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button></a>
                                        <?php } ?>
                                        &nbsp;
                                    <?php
                                } ?>
                                <i class="fa fa-clock-o"></i> <?= $item['datetime'] ?></span>
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
                        </li>
                        <?php
                    } ?>
                    <!-- END timeline item -->
                </ul>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div role="tab-pane fade" class="tab-pane" id="properties">
        <div class="main-box clearfix">
            <div class="main-box-body clearfix">
                <?php
                $sAssignedProperties = ",";

                if (mysqli_num_rows($rsAssignedProperties) == 0) {
                    ?>
                    <br>
                    <div class="alert alert-warning">
                        <i class="fa fa-question-circle fa-fw fa-lg"></i>
                        <span><?= gettext("No property assignments.") ?></span>
                    </div>
                    <?php
                } else {
                    //Yes, start the table
                    echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
                    echo "<tr class=\"TableHeader\">";
                    echo "<td width=\"10%\" valign=\"top\"><b>" . gettext("Type") . "</b></td>";
                    echo "<td width=\"15%\" valign=\"top\"><b>" . gettext("Name") . "</b></td>";
                    echo "<td valign=\"top\"><b>" . gettext("Value") . "</b></td>";

                    if ($bOkToEdit) {
                        echo "<td width=\"10%\" valign=\"top\"><b>" . gettext("Edit Value") . "</td>";
                        echo "<td valign=\"top\"><b>" . gettext("Remove") . "</td>";
                    }

                    echo "</tr>";

                    $last_pro_prt_ID = "";
                    $bIsFirst = true;

                    //Loop through the rows
                    while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
                        $pro_Prompt = "";
                        $r2p_Value = "";

                        extract($aRow);

                        if ($pro_prt_ID != $last_pro_prt_ID) {
                            echo "<tr class=\"";
                            if ($bIsFirst) {
                                echo "RowColorB";
                            } else {
                                echo "RowColorC";
                            }
                            echo "\"><td><b>" . $prt_Name . "</b></td>";

                            $bIsFirst = false;
                            $last_pro_prt_ID = $pro_prt_ID;
                            $sRowClass = "RowColorB";
                        } else {
                            echo "<tr class=\"" . $sRowClass . "\">";
                            echo "<td valign=\"top\">&nbsp;</td>";
                        }

                        echo "<td valign=\"center\">" . $pro_Name . "</td>";
                        echo "<td valign=\"center\">" . $r2p_Value . "&nbsp;</td>";

                        if ($bOkToEdit) {
                            if (strlen($pro_Prompt) > 0) {
                                echo "<td valign=\"center\"><a href=\"PropertyAssign.php?FamilyID=" . $iFamilyID . "&amp;PropertyID=" . $pro_ID . "\">" . gettext("Edit Value") . "</a></td>";
                            } else {
                                echo "<td>&nbsp;</td>";
                            }

                            echo "<td valign=\"center\"><a href=\"PropertyUnassign.php?FamilyID=" . $iFamilyID . "&amp;PropertyID=" . $pro_ID . "\">" . gettext("Remove") . "</a></td>";
                        }

                        echo "</tr>";

                        //Alternate the row style
                        $sRowClass = AlternateRowStyle($sRowClass);

                        $sAssignedProperties .= $pro_ID . ",";
                    }

                    //Close the table
                    echo "</table>";
                }
                if ($bOkToEdit) {
                    ?>
                    <div class="alert alert-info">
                        <div>
                            <h4><strong><?= gettext("Assign a New Property") ?>:</strong></h4>

                            <form method="post" action="PropertyAssign.php?FamilyID=<?= $iFamilyID ?>">
                                <div class="row">
                                    <div class="form-group col-md-7 col-lg-7 col-sm-12 col-xs-12">
                                        <select name="PropertyID" class="form-control">
                                            <option selected disabled> -- <?= gettext('select an option') ?>
                                                --
                                            </option>
                                            <?php
                                            while ($aRow = mysqli_fetch_array($rsProperties)) {
                                                extract($aRow);
                                                //If the property doesn't already exist for this Person, write the <OPTION> tag
                                                if (strlen(strstr($sAssignedProperties, "," . $pro_ID . ",")) == 0) {
                                                    echo "<option value=\"" . $pro_ID . "\">" . $pro_Name . "</option>";
                                                }
                                            } ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-lg-7 col-md-7 col-sm-12 col-xs-12">
                                        <input type="submit" class="btn btn-primary"
                                               value="<?= gettext("Assign") ?>" name="Submit2">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php
                } ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="nav-tabs-custom">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <?php if ($_SESSION['bFinance']) {
                    ?>
                    <li role="presentation" class="active"><a href="#finance" aria-controls="finance" role="tab"
                                               data-toggle="tab"><?= gettext("Automatic Payments") ?></a></li>
                    <li role="presentation"><a href="#pledges" aria-controls="pledges" role="tab"
                                               data-toggle="tab"><?= gettext("Pledges and Payments") ?></a></li>
                    <?php
                } ?>

            </ul>

            <!-- Tab panes -->
            <div class="tab-content">


                <?php if ($_SESSION['bFinance']) {
                ?>
                <div role="tab-pane fade" class="tab-pane" id="finance">
                    <div class="main-box clearfix">
                        <div class="main-box-body clearfix">
                            <?php if (mysqli_num_rows($rsAutoPayments) > 0) {
                                ?>
                                <table cellpadding="5" cellspacing="0" width="100%">

                                    <tr class="TableHeader">
                                        <td><?= gettext("Type") ?></td>
                                        <td><?= gettext("Next payment date") ?></td>
                                        <td><?= gettext("Amount") ?></td>
                                        <td><?= gettext("Interval (months)") ?></td>
                                        <td><?= gettext("Fund") ?></td>
                                        <td><?= gettext("Edit") ?></td>
                                        <td><?= gettext("Delete") ?></td>
                                        <td><?= gettext("Date Updated") ?></td>
                                        <td><?= gettext("Updated By") ?></td>
                                    </tr>

                                    <?php

                                    $tog = 0;

                                    //Loop through all automatic payments
                                    while ($aRow = mysqli_fetch_array($rsAutoPayments)) {
                                        $tog = (!$tog);

                                        extract($aRow);

                                        $payType = "Disabled";
                                        if ($aut_EnableBankDraft) {
                                            $payType = "Bank Draft";
                                        }
                                        if ($aut_EnableCreditCard) {
                                            $payType = "Credit Card";
                                        }

                                        //Alternate the row style
                                        if ($tog) {
                                            $sRowClass = "RowColorA";
                                        } else {
                                            $sRowClass = "RowColorB";
                                        } ?>

                                        <tr class="<?= $sRowClass ?>">
                                            <td>
                                                <?= $payType ?>&nbsp;
                                            </td>
                                            <td>
                                                <?= $aut_NextPayDate ?>&nbsp;
                                            </td>
                                            <td>
                                                <?= $aut_Amount ?>&nbsp;
                                            </td>
                                            <td>
                                                <?= $aut_Interval ?>&nbsp;
                                            </td>
                                            <td>
                                                <?= gettext($fundName) ?>&nbsp;
                                            </td>
                                            <td>
                                                <a
                                                    href="AutoPaymentEditor.php?AutID=<?= $aut_ID ?>&amp;FamilyID=<?= $iFamilyID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= gettext("Edit") ?></a>
                                            </td>
                                            <td>
                                                <a
                                                    href="AutoPaymentDelete.php?AutID=<?= $aut_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= gettext("Delete") ?></a>
                                            </td>
                                            <td>
                                                <?= $aut_DateLastEdited ?>&nbsp;
                                            </td>
                                            <td>
                                                <?= $EnteredFirstName . " " . $EnteredLastName ?>&nbsp;
                                            </td>
                                        </tr>
                                        <?php
                                    } ?>
                                </table>
                                <?php
                            } ?>
                            <p align="center">
                                <a class="SmallText"
                                   href="AutoPaymentEditor.php?AutID=-1&FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= gettext("Add a new automatic payment") ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <div role="tab-pane fade" class="tab-pane" id="pledges">
                    <div class="main-box clearfix">
                        <div class="main-box-body clearfix">
                            <form method="post" action="FamilyView.php?FamilyID=<?= $iFamilyID ?>">
                                <input type="checkbox" name="ShowPledges"
                                       value="1" <?php if ($_SESSION['sshowPledges']) {
                                    echo " checked";
                                } ?>><?= gettext("Show Pledges") ?>
                                <input type="checkbox" name="ShowPayments"
                                       value="1" <?php if ($_SESSION['sshowPayments']) {
                                    echo " checked";
                                } ?>><?= gettext("Show Payments") ?>
                                <label for="ShowSinceDate"><?= gettext("Since") ?>:</label>
                                <?php
                                $showSince = "";
                                if ($_SESSION['sshowSince'] != null) {
                                    $showSince = $_SESSION['sshowSince']->format('Y-m-d');
                                } ?>
                                <input type="text" class="date-picker" Name="ShowSinceDate"
                                       value="<?= $showSince ?>" maxlength="10" id="ShowSinceDate" size="15">
                                <input type="submit" class="btn" <?= 'value="' . gettext("Update") . '"' ?>
                                       name="UpdatePledgeTable"
                                       style="font-size: 8pt;">
                            </form>

                            <table id="pledge-payment-table" class="table table-condensed dt-responsive" width="100%">
                                <thead>
                                <tr>
                                    <th><?= gettext("Pledge or Payment") ?></th>
                                    <th><?= gettext("Fund") ?></th>
                                    <th><?= gettext("Fiscal Year") ?></th>
                                    <th><?= gettext("Date") ?></th>
                                    <th><?= gettext("Amount") ?></th>
                                    <th><?= gettext("NonDeductible") ?></th>
                                    <th><?= gettext("Schedule") ?></th>
                                    <th><?= gettext("Method") ?></th>
                                    <th><?= gettext("Comment") ?></th>
                                    <th><?= gettext("Edit") ?></th>
                                    <th><?= gettext("Delete") ?></th>
                                    <th><?= gettext("Date Updated") ?></th>
                                    <th><?= gettext("Updated By") ?></th>
                                </tr>
                                </thead>
                                <tbody>

                                <?php
                                $tog = 0;

                                if ($_SESSION['sshowPledges'] || $_SESSION['sshowPayments']) {
                                    //Loop through all pledges
                                    while ($aRow = mysqli_fetch_array($rsPledges)) {
                                        $tog = (!$tog);

                                        $plg_FYID = "";
                                        $plg_date = "";
                                        $plg_amount = "";
                                        $plg_schedule = "";
                                        $plg_method = "";
                                        $plg_comment = "";
                                        $plg_plgID = 0;
                                        $plg_DateLastEdited = "";
                                        $plg_EditedBy = "";

                                        extract($aRow);

                                        //Display the pledge or payment if appropriate
                                        if ((($_SESSION['sshowPledges'] && $plg_PledgeOrPayment == 'Pledge') ||
                                                ($_SESSION['sshowPayments'] && $plg_PledgeOrPayment == 'Payment')
                                            ) &&
                                            ($_SESSION['sshowSince'] == "" || DateTime::createFromFormat("Y-m-d", $plg_date) > $_SESSION['sshowSince'])
                                        ) {
                                            ?>

                                            <tr>
                                                <td>
                                                    <?= $plg_PledgeOrPayment ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= $fundName ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= MakeFYString($plg_FYID) ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= $plg_date ?>&nbsp;
                                                </td>
                                                <td align=center>
                                                    <?= $plg_amount ?>&nbsp;
                                                </td>
                                                <td align=center>
                                                    <?= $plg_NonDeductible ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= $plg_schedule ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= $plg_method ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= $plg_comment ?>&nbsp;
                                                </td>
                                                <td>
                                                    <a
                                                        href="PledgeEditor.php?GroupKey=<?= $plg_GroupKey ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>">Edit</a>
                                                </td>
                                                <td>
                                                    <a
                                                        href="PledgeDelete.php?GroupKey=<?= $plg_GroupKey ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>">Delete</a>
                                                </td>
                                                <td>
                                                    <?= $plg_DateLastEdited ?>&nbsp;
                                                </td>
                                                <td>
                                                    <?= $EnteredFirstName . " " . $EnteredLastName ?>&nbsp;
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                } // if bShowPledges

                                ?>

                                </tbody>
                            </table>

                            <p align="center">
                                <a class="SmallText"
                                   href="PledgeEditor.php?FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>&amp;PledgeOrPayment=Pledge"><?= gettext("Add a new pledge") ?></a>
                                <a class="SmallText"
                                   href="PledgeEditor.php?FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>&amp;PledgeOrPayment=Payment"><?= gettext("Add a new payment") ?></a>
                            </p>

                            <?php
                            } ?>

                            <?php if ($_SESSION['bCanvasser']) {
                            ?>

                            <p align="center">
                                <a class="SmallText"
                                   href="CanvassEditor.php?FamilyID=<?= $fam_ID ?>&amp;FYID=<?= $_SESSION['idefaultFY'] ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= MakeFYString($_SESSION['idefaultFY']) . gettext(" Canvass Entry") ?></a>
                            </p>
                        </div>
                    </div>
                </div>
            <?php
            } ?>

            </div>
        </div>
    </div>
</div>

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
