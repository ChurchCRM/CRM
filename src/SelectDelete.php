<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyCustomQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: User must have Delete records permission
// Otherwise, re-direct them to the main menu.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

$iFamilyID = 0;
$iDonationFamilyID = 0;

if (!empty($_GET['FamilyID'])) {
    $iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
}

if (!empty($_GET['DonationFamilyID'])) {
    $iDonationFamilyID = InputUtils::legacyFilterInput($_GET['DonationFamilyID'], 'int');
}

if (isset($_GET['CancelFamily'])) {
    RedirectUtils::redirect("v2/family/$iFamilyID");
}

$DonationMessage = '';

// Move Donations from 1 family to another
if (AuthenticationManager::getCurrentUser()->isFinanceEnabled() && isset($_GET['MoveDonations']) && $iFamilyID && $iDonationFamilyID && $iFamilyID != $iDonationFamilyID) {
    $pledges = PledgeQuery::create()->filterByFamId((int) $iFamilyID)->find();
    foreach ($pledges as $pledge) {
        $pledge
            ->setFamId((int) $iDonationFamilyID)
            ->setDateLastEdited(date('Y-m-d'))
            ->setEditedBy(AuthenticationManager::getCurrentUser()->getId());
        $pledge->save();
    }

            $DonationMessage = '<p><b><span class="text-error">' . gettext('All donations from this family have been moved to another family.') . '</span></b></p>';
}

//Set the Page Title
$sPageTitle = gettext('Delete Confirmation') . ': ' . gettext('Family');

//Do we have deletion confirmation?
if (isset($_GET['Confirmed'])) {
    $iFamilyIDInt = (int) $iFamilyID;

    // Delete Family
    // Delete all associated Notes associated with this Family record
    NoteQuery::create()->filterByFamId($iFamilyIDInt)->delete();

    // Delete Family pledges
    PledgeQuery::create()
        ->filterByPledgeOrPayment('Pledge')
        ->filterByFamId($iFamilyIDInt)
        ->delete();

    // Remove family property data
    $familyPropertyIds = PropertyQuery::create()
        ->filterByProClass('f')
        ->select(['ProId'])
        ->find()
        ->toArray();
    if (!empty($familyPropertyIds)) {
        RecordPropertyQuery::create()
            ->filterByPropertyId($familyPropertyIds)
            ->filterByRecordId($iFamilyIDInt)
            ->delete();
    }

    if (isset($_GET['Members'])) {
        // Delete all persons that were in this family
        PersonQuery::create()->filterByFamId($iFamilyIDInt)->find()->delete();
    } else {
        // Reset previous members' family ID to 0 (undefined)
        $members = PersonQuery::create()->filterByFamId($iFamilyIDInt)->find();
        foreach ($members as $member) {
            $member->setFamId(0);
            $member->save();
        }
    }

    // Delete the specified Family record
    FamilyQuery::create()->findPk($iFamilyIDInt)?->delete();

    // Remove custom field data
    FamilyCustomQuery::create()->filterByFamId($iFamilyIDInt)->delete();

    // Delete the photo files, if they exist
    $photoFile = 'Images/Family/' . $iFamilyID . '.png';
    if (file_exists($photoFile)) {
        unlink($photoFile);
    }

    // Redirect back to the family listing
    RedirectUtils::redirect(SystemURLs::getRootPath() . '/v2/family');
}

//Get the family record in question
$family = FamilyQuery::create()->findPk((int) $iFamilyID);
if ($family === null) {
    RedirectUtils::redirect(SystemURLs::getRootPath() . '/v2/family');
}
$fam_Name = $family->getName();

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Delete Confirmation')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
    <div class="card-body">
        <?php
        // Delete Family Confirmation
        // See if this family has any donations
        $bIsDonor = PledgeQuery::create()
            ->filterByPledgeOrPayment('Payment')
            ->filterByFamId((int) $iFamilyID)
            ->exists();

        if ($bIsDonor && !AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
            // Donations from Family. Current user not authorized for Finance
            echo '<p class="lead">' . gettext('Sorry, there are records of donations from this family. This family may not be deleted.') . '<br><br>';
            echo '<a href="v2/family/' . $iFamilyID . '">' . gettext('Return to Family View') . '</a></p>';
        } elseif ($bIsDonor && AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
            // Donations from Family. Current user authorized for Finance.
            // Select another family to move donations to.
            echo '<p class="lead">' . gettext('WARNING: This family has records of donations and may NOT be deleted until these donations are associated with another family.') . '</p>';
            echo '<form name=SelectFamily method=get action=SelectDelete.php>';
            echo '<div class="card-body">';
            echo '<div class="card-header"><strong>' . gettext('Family Name') . ':' ." $fam_Name</strong></div>";
            echo '<p>' . gettext('Please select another family with whom to associate these donations:');
            echo '<br><b>' . gettext('WARNING: This action can not be undone and may have legal implications!') . '</b></p>';
            echo"<input name=FamilyID value=$iFamilyID type=hidden>";
            echo '<select name=DonationFamilyID><option value=0 selected>' . gettext('Unassigned') . '</option>';

            //Get Families for the drop-down
            $families = FamilyQuery::create()->orderByName()->find();

            // Build list of Head of Household roles
            $headRoles = array_map('intval', explode(',', SystemConfig::getValue('sDirRoleHead') ?: '1'));
            if (intval(SystemConfig::getValue('sDirRoleSpouse')) > 0) {
                $headRoles[] = intval(SystemConfig::getValue('sDirRoleSpouse'));
            }
            // Build array of Head of Households and Spouses with fam_ID as the key
            $heads = PersonQuery::create()
                ->filterByFamId(0, \Propel\Runtime\ActiveQuery\Criteria::GREATER_THAN)
                ->filterByFmrId($headRoles)
                ->orderByFamId()
                ->find();
            $aHead = [];
            foreach ($heads as $head) {
                $headFamId = $head->getFamId();
                $firstName = $head->getFirstName();
                if ($firstName && isset($aHead[$headFamId])) {
                    $aHead[$headFamId] .= ' & ' . $firstName;
                } elseif ($firstName) {
                    $aHead[$headFamId] = $firstName;
                }
            }
            foreach ($families as $fam) {
                $famId = $fam->getId();
                echo '<option value="' . (int) $famId . '"';
                if ($famId == $iFamilyID) {
                    echo ' selected';
                }
                echo '>' . InputUtils::escapeHTML($fam->getName());
                if (isset($aHead[$famId])) {
                    echo ', ' . InputUtils::escapeHTML($aHead[$famId]);
                }
                if ($famId == $iFamilyID) {
                    echo ' -- ' . gettext('CURRENT FAMILY WITH DONATIONS');
                } else {
                    echo ' ' . InputUtils::escapeHTML(MiscUtils::formatAddressLine($fam->getAddress1(), $fam->getCity(), $fam->getState()));
                }
            }
            echo '</select><br><br>';
            echo '<input type="submit" class="btn btn-secondary me-2" name="CancelFamily" value="' . gettext('Cancel and Return to Family View') . '">';
            echo '<input type="submit" class="btn btn-primary" name="MoveDonations" value="' . gettext('Move Donations to Selected Family') . '">';
            echo '</div></form>';

            // Show payments connected with family
            // -----------------------------------
            echo '<br><br>';
            //Get the pledges for this family
            $sSQL = 'SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method,
                 plg_comment, plg_DateLastEdited, plg_PledgeOrPayment, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, b.fun_Name AS fundName
                 FROM pledge_plg
                 LEFT JOIN person_per a ON plg_EditedBy = a.per_ID
                 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
                 WHERE plg_famID = ' . (int) $iFamilyID . ' ORDER BY pledge_plg.plg_date';
            $rsPledges = RunQuery($sSQL); ?>
            <table class="table w-100">
                <tr class="TableHeader">
                    <td><?= gettext('Type') ?></td>
                    <td><?= gettext('Fund') ?></td>
                    <td><?= gettext('Fiscal Year') ?></td>
                    <td><?= gettext('Date') ?></td>
                    <td><?= gettext('Amount') ?></td>
                    <td><?= gettext('Schedule') ?></td>
                    <td><?= gettext('Method') ?></td>
                    <td><?= gettext('Comment') ?></td>
                    <td><?= gettext('Date Updated') ?></td>
                    <td><?= gettext('Updated By') ?></td>
                </tr>
                <?php
                $tog = 0;
                //Loop through all pledges
                while ($aRow = mysqli_fetch_array($rsPledges)) {
                    $tog = (!$tog);
                    $plg_FYID = '';
                    $plg_date = '';
                    $plg_amount = '';
                    $plg_schedule = '';
                    $plg_method = '';
                    $plg_comment = '';
                    $plg_plgID = 0;
                    $plg_DateLastEdited = '';
                    $plg_EditedBy = '';
                    extract($aRow);

                    ?>
                    <tr>
                        <td><?= InputUtils::escapeHTML($plg_PledgeOrPayment) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($fundName) ?>&nbsp;</td>
                        <td><?= $plg_FYID ? FinancialService::formatFiscalYear((int) $plg_FYID) : '' ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($plg_date) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($plg_amount) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($plg_schedule) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($plg_method) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($plg_comment) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($plg_DateLastEdited) ?>&nbsp;</td>
                        <td><?= InputUtils::escapeHTML($EnteredFirstName) . ' ' . InputUtils::escapeHTML($EnteredLastName) ?>&nbsp;</td>
                    </tr>
            <?php
                }
                echo '</table>';
            } else {
                // No Donations from family.  Normal delete confirmation
                echo $DonationMessage;
                echo"<div class='alert alert-warning'><b>" . gettext('Please confirm deletion of this family record:') . '</b><br/>';
                echo gettext('Note: This will also delete all Notes associated with this Family record.');
                echo gettext('(this action cannot be undone)') . '</div>';
                echo '<div>';
                echo '<strong>' . gettext('Family Name') . ':</strong>';
                echo '&nbsp;' . InputUtils::escapeHTML($fam_Name);
                echo '</div><br/>';
                echo '<div><strong>' . gettext('Family Members:') . '</strong><ul>';
                //List Family Members
                $familyMembers = PersonQuery::create()->filterByFamId((int) $iFamilyID)->find();
                foreach ($familyMembers as $person) {
                    echo '<li>' . InputUtils::escapeHTML($person->getFirstName()) . ' ' . InputUtils::escapeHTML($person->getLastName()) . '</li>';
                }
                echo '</ul></div>';
                echo"<p id=\"deleteFamilyOnlyBtn\" class=\"text-center\"><a class='btn btn-danger' href=\"SelectDelete.php?Confirmed=Yes&FamilyID=" . $iFamilyID . '">' . gettext('Delete Family Record ONLY') . '</a> ';
                echo"<a id=\"deleteFamilyAndMembersBtn\" class='btn btn-danger' href=\"SelectDelete.php?Confirmed=Yes&Members=Yes&FamilyID=" . $iFamilyID . '">' . gettext('Delete Family Record AND Family Members') . '</a> ';
                echo"<a class='btn btn-secondary ms-2' href=\"v2/family/" . $iFamilyID . '">' . gettext('No, cancel this deletion') . '</a></p>';
            }
            ?>
    </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
