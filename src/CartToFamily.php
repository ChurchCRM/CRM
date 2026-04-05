<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\view\PageHeader;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have add records permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isAddRecordsEnabled(), 'AddRecords');

// Was the form submitted?
if (isset($_POST['Submit']) && count($_SESSION['aPeopleCart']) > 0) {
    // Get the FamilyID
    $iFamilyID = InputUtils::legacyFilterInput($_POST['FamilyID'], 'int');

    // Are we creating a new family
    if ($iFamilyID === 0) {
        $sFamilyName = InputUtils::legacyFilterInput($_POST['FamilyName']);

        $dWeddingDate = InputUtils::legacyFilterInput($_POST['WeddingDate']);
        if (strlen($dWeddingDate) === 0) {
            $dWeddingDate = null;
        }

        $iPersonAddress = InputUtils::legacyFilterInput($_POST['PersonAddress'], 'int');

        // Note: PersonAddress is used to pre-fill fields, but the code below uses form input only
        // TODO: Implement address pre-fill from selected person if needed

        // Use form input only - each person must enter their own data
        $sAddress1 = InputUtils::legacyFilterInput($_POST['Address1']);
        $sAddress2 = InputUtils::legacyFilterInput($_POST['Address2']);
        $sCity = InputUtils::legacyFilterInput($_POST['City']);
        $sZip = InputUtils::legacyFilterInput($_POST['Zip']);
        $sCountry = InputUtils::legacyFilterInput($_POST['Country']);

        // State handling: API determines which countries have states; JS toggles UI visibility
        $sState = '';
        if (array_key_exists('State', $_POST)) {
            $sState = InputUtils::legacyFilterInput($_POST['State']);
        } elseif (array_key_exists('StateTextbox', $_POST)) {
            $sState = InputUtils::legacyFilterInput($_POST['StateTextbox']);
        }

        // Get phone data from the form (store as entered by user).
        $sHomePhone = InputUtils::legacyFilterInput($_POST['HomePhone']);

        $sEmail = InputUtils::legacyFilterInput($_POST['Email']);

        if (strlen($sFamilyName) === 0) {
            $sError = '<p class="alert alert-warning text-danger text-center">' . gettext('No family name entered!') . '</p>';
            $bError = true;
        } else {
            // Use Propel ORM to create family
            $family = new Family();
            $family->setName($sFamilyName);
            if (!empty($sAddress1)) $family->setAddress1($sAddress1);
            if (!empty($sAddress2)) $family->setAddress2($sAddress2);
            if (!empty($sCity)) $family->setCity($sCity);
            if (!empty($sState)) $family->setState($sState);
            if (!empty($sZip)) $family->setZip($sZip);
            if (!empty($sCountry)) $family->setCountry($sCountry);
            if (!empty($sHomePhone)) $family->setHomePhone($sHomePhone);
            if (!empty($sEmail)) $family->setEmail($sEmail);
            if ($dWeddingDate !== null) $family->setWeddingDate($dWeddingDate);
            $family->setDateEntered(date('YmdHis'));
            $family->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
            $family->save();

            $iFamilyID = $family->getId();
        }
    }

    if (!$bError) {
        // Loop through the cart array
        $iCount = 0;
        foreach ($_SESSION['aPeopleCart'] as $element) {
            $iPersonID = (int)$element;
            $person = PersonQuery::create()->findOneById($iPersonID);
            if ($person === null) {
                throw new \Exception(sprintf('person (%d) not found', $iPersonID));
            }
            $per_fam_ID = $person->getFamId();

            // Make sure they are not already in a family
            if ($per_fam_ID !== 0) {
                throw new \Exception(sprintf('person (%d) is already assigned to family (%s)', $iPersonID, $per_fam_ID));
            }
            $iFamilyRoleID = InputUtils::legacyFilterInput($_POST['role' . $iPersonID], 'int');
            if (empty($iFamilyRoleID)) {
                throw new \Exception(sprintf('person (%d) does not have role in post body', $iPersonID));
            }

            $person
                ->setFamId($iFamilyID)
                ->setFmrId($iFamilyRoleID);
            $person->save();

            $iCount++;
        }

        $_SESSION['sGlobalMessage'] = sprintf(ngettext('%d Person successfully added to selected Family.', '%d People successfully added to selected Family.', $iCount), $iCount);
        $_SESSION['sGlobalMessageClass'] = 'success';

        RedirectUtils::redirect('v2/family/' . $iFamilyID . '&Action=EmptyCart');
    }
}

$sPageTitle = gettext('Add Cart to Family');
$sPageSubtitle = gettext('Assign cart items to a family record');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People'), '/people/dashboard'],
    [gettext('Add Cart to Family')],
]);
require_once __DIR__ . '/Include/Header.php';

echo $sError;
?>
<div class="card">
    <form method="post">

        <?php
        if (count($_SESSION['aPeopleCart']) > 0) {
            // Get all the families using ORM
            $families = FamilyQuery::create()->orderByName()->find();

            // Get the family roles using ORM (lst_ID = 2 is family roles)
            $familyRoles = ListOptionQuery::create()
                ->filterByListId(2)
                ->orderByOptionSequence()
                ->find();

            $sRoleOptionsHTML = '';
            foreach ($familyRoles as $roleOption) {
                $sRoleOptionsHTML .= sprintf(
                    '<option value="%s">%s</option>',
                    $roleOption->getOptionId(),
                    InputUtils::escapeHTML($roleOption->getOptionName())
                );
            }

            // Get cart items using ORM
            $cartPersons = PersonQuery::create()
                ->filterById($_SESSION['aPeopleCart'])
                ->orderByLastName()
                ->find();

            echo"<table class='table'>";
            echo '<thead><tr>';
            echo '<th style="width:40px">#</th>';
            echo '<th>' . gettext('Name') . '</th>';
            echo '<th class="text-center">' . gettext('Assign Role') . '</th>';
            echo '</tr></thead><tbody>';

            $count = 1;
            foreach ($cartPersons as $cartPerson) {

                echo '<tr>';
                echo '<td class="text-center">' . $count++ . '</td>';
                $personPhoto = new \ChurchCRM\dto\Photo('person', $cartPerson->getId());
                $photoIcon = '';
                if ($personPhoto->hasUploadedPhoto()) {
                    $photoIcon = ' <button class="btn btn-sm btn-outline-secondary view-person-photo" data-person-id="' . $cartPerson->getId() . '" title="' . gettext('View Photo') . '"><i class="fa-solid fa-camera"></i></button>';
                }
                echo '<td><a href="PersonView.php?PersonID=' . $cartPerson->getId() . '">' . $cartPerson->getFullName() . '</a>' . $photoIcon . '</td>';

                echo '<td class="text-center">';
                if ($cartPerson->getFamId() === 0) {
                    echo '<select name="role' . $cartPerson->getId() . '">' . $sRoleOptionsHTML . '</select>';
                } else {
                    echo gettext('Already in a family');
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>'; ?>
</div>
<div class="card">
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label"><?= gettext('Add to Family') ?>:</label>
            <?php
            echo '<select name="FamilyID" class="form-select">';
            echo '<option value="0">' . gettext('Create new family') . '</option>';
            foreach ($families as $family) {
                echo sprintf('<option value="%s">%s</option>', $family->getId(), InputUtils::escapeHTML($family->getName()));
            }
            echo '</select>'; ?>
        </div>
        <p class="text-muted"><?= gettext('If adding a new family, enter data below.') ?></p>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Family Name') ?>:</label>
            <input type="text" class="form-control" name="FamilyName" value="<?= InputUtils::escapeAttribute($sName ?? '') ?>" maxlength="48">
            <?php if (!empty($sNameError)): ?><div class="text-danger small"><?= InputUtils::escapeHTML($sNameError) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Wedding Date') ?>:</label>
            <input type="text" class="form-control date-picker" name="WeddingDate" value="<?= InputUtils::escapeAttribute($dWeddingDate ?? '') ?>" maxlength="10" id="sel1">
            <?php if (!empty($sWeddingDateError)): ?><div class="text-danger small"><?= InputUtils::escapeHTML($sWeddingDateError) ?></div><?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Use address/contact data from') ?>:</label>
            <?php
            echo '<select name="PersonAddress" class="form-select">';
            echo '<option value="0">' . gettext('Only the new data below') . '</option>';
            mysqli_data_seek($rsCartItems, 0);
            while ($aRow = mysqli_fetch_array($rsCartItems)) {
                if ($per_fam_ID === 0) {
                    echo sprintf('<option value="%s">%s %s</option>', $aRow['per_ID'], InputUtils::escapeHTML($aRow['per_FirstName']), InputUtils::escapeHTML($aRow['per_LastName']));
                }
            }
            echo '</select>'; ?>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Address') ?> 1:</label>
            <input type="text" class="form-control" name="Address1" value="<?= InputUtils::escapeAttribute($sAddress1 ?? '') ?>" maxlength="250">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Address') ?> 2:</label>
            <input type="text" class="form-control" name="Address2" value="<?= InputUtils::escapeAttribute($sAddress2 ?? '') ?>" maxlength="250">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('City') ?>:</label>
            <input type="text" class="form-control" name="City" value="<?= InputUtils::escapeAttribute($sCity ?? '') ?>" maxlength="50">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('State') ?>:</label>
            <div id="stateOptionDiv">
                <select id="State" name="State" class="form-select" data-user-selected="<?= InputUtils::escapeAttribute($sState ?? '') ?>" data-system-default="<?= InputUtils::escapeAttribute(SystemConfig::getValue('sDefaultState')) ?>">
                </select>
            </div>
            <div id="stateInputDiv" class="d-none">
                <input type="text" class="form-control" name="StateTextbox" id="StateTextbox" value="" maxlength="30">
                <small class="text-muted"><?= gettext('(Enter state/province for countries without predefined states)') ?></small>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Zip') ?>:</label>
            <input type="text" class="form-control" name="Zip" value="<?= InputUtils::escapeAttribute($sZip ?? '') ?>" maxlength="10" style="max-width:120px">
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Country') ?>:</label>
            <select id="Country" name="Country" class="form-select" data-user-selected="<?= InputUtils::escapeAttribute($sCountry ?? '') ?>" data-system-default="<?= InputUtils::escapeAttribute(SystemConfig::getValue('sDefaultCountry')) ?>">
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Home Phone') ?>:</label>
            <input type="text" class="form-control" name="HomePhone" value="<?= InputUtils::escapeAttribute($sHomePhone ?? '') ?>" maxlength="30" data-inputmask='"mask":"<?= SystemConfig::getValueForAttr('sPhoneFormat') ?>"' data-mask>
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="NoFormat_HomePhone" value="1" id="NoFormat_HomePhone" <?= (!empty($bNoFormat_HomePhone)) ? 'checked' : '' ?>>
                <label class="form-check-label" for="NoFormat_HomePhone"><?= gettext('Do not auto-format') ?></label>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Email') ?>:</label>
            <input type="text" class="form-control" name="Email" value="<?= InputUtils::escapeAttribute($sEmail ?? '') ?>" maxlength="50">
        </div>
        <div class="d-flex gap-2">
            <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Add to Family') ?>">
        </div>
    </div>
<?php
        } else {
            echo '<p class="alert alert-warning text-center">' . gettext('Your cart is empty!') . '</p>';
        }
?>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/DropdownManager.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/CartToFamily.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.CRM && window.CRM.formUtils && typeof window.CRM.formUtils.togglePhoneMask === 'function') {
            try {
                window.CRM.formUtils.togglePhoneMask('NoFormat_HomePhone','HomePhone');
            } catch (e) {
                // noop - fail silently if toggle not available
            }
        }
    });
</script>

<?php
require_once __DIR__ . '/Include/Footer.php';
