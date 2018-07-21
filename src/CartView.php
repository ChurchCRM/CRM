<?php
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/LabelFunctions.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\SessionUser;

// Set the page title and include HTML header
$sPageTitle = gettext('View Your Cart');
require 'Include/Header.php'; ?>
<div class="box box-body">
<?php
// Confirmation message that people where added to Event from Cart
if (!Cart::HasPeople()) {

} else {
} ?>

    <!-- END CART FUNCTIONS -->

    <!-- BEGIN CART LISTING -->
    <?php if (isset($iNumPersons) && $iNumPersons > 0): ?>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <?= gettext('Your cart contains') . ' ' . $iNumPersons . ' ' . gettext('persons from') . ' ' . $iNumFamilies . ' ' . gettext('families') ?>
                    .</h3>
            </div>
            <div class="box-body">
                <table class="table table-hover dt-responsive" id="cart-listing-table" style="width:100%;">
                    <thead>
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('Address') ?></th>
                        <th><?= gettext('Email') ?></th>
                        <th><?= gettext('Remove') ?></th>
                        <th><?= gettext('Classification') ?></th>
                        <th><?= gettext('Family Role') ?></th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php
                    $sEmailLink = '';
                    $iEmailNum = 0;
                    $email_array = [];

                    while ($aRow = mysqli_fetch_array($rsCartItems)) {
                        extract($aRow);

                        $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
                        if (strlen($sEmail) == 0 && strlen($per_WorkEmail) > 0) {
                            $sEmail = $per_WorkEmail;
                        }

                        if (strlen($sEmail)) {
                            $sValidEmail = gettext('Yes');
                            if (!stristr($sEmailLink, $sEmail)) {
                                $email_array[] = $sEmail;

                                if ($iEmailNum == 0) {
                                    // Comma is not needed before first email address
                                    $sEmailLink .= $sEmail;
                                    $iEmailNum++;
                                } else {
                                    $sEmailLink .= $sMailtoDelimiter . $sEmail;
                                }
                            }
                        } else {
                            $sValidEmail = gettext('No');
                        }

                        $sAddress1 = SelectWhichInfo($per_Address1, $fam_Address1, false);
                        $sAddress2 = SelectWhichInfo($per_Address2, $fam_Address2, false);

                        if (strlen($sAddress1) > 0 || strlen($sAddress2) > 0) {
                            $sValidAddy = gettext('Yes');
                        } else {
                            $sValidAddy = gettext('No');
                        }

                        $personName = $per_FirstName . ' ' . $per_LastName;
                        $thumbnail = SystemURLs::getRootPath() . '/api/person/' . $per_ID . '/thumbnail'; ?>

                        <tr>
                            <td>
                                <img src="<?= $thumbnail ?>" class="direct-chat-img initials-image">&nbsp
                                <a href="PersonView.php?PersonID=<?= $per_ID ?>">
                                    <?= FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 1) ?>
                                </a>
                            </td>
                            <td><?= $sValidAddy ?></td>
                            <td><?= $sValidEmail ?></td>
                            <td><a class="RemoveFromPeopleCart" data-personid="<?= $per_ID ?>"><?= gettext('Remove') ?></a>
                            </td>
                            <td><?= $aClassificationName[$per_cls_ID] ?></td>
                            <td><?= $aFamilyRoleName[$per_fmr_ID] ?></td>
                        </tr>
                        <?php
                    } ?>

                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <!-- END CART LISTING -->

    <script nonce="<?= SystemURLs::getCSPNonce() ?>" >
        $(document).ready(function () {
          $("#cart-listing-table").DataTable(window.CRM.plugin.dataTable);

          $(document).on("click", ".emptyCart", function (e) {
            window.CRM.cart.empty(function(){
              document.location.reload();
            });
          });

          $(document).on("click", ".RemoveFromPeopleCart", function (e) {
            clickedButton = $(this);
            e.stopPropagation();
            window.CRM.cart.removePerson([clickedButton.data("personid")],function() {
              document.location.reload();
            });
          });

         });
    </script>

    <?php

require 'Include/Footer.php';
?>
