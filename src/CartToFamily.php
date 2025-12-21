<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
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

        // Get and format any phone data from the form.
        $sHomePhone = InputUtils::legacyFilterInput($_POST['HomePhone']);
        if (!isset($_POST['NoFormat_HomePhone'])) {
            $sHomePhone = CollapsePhoneNumber($sHomePhone, $sCountry);
        }

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
            if ($per_fam_ID != 0) {
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
                    $roleOption->getOptionName()
                );
            }

            // Get cart items using ORM
            $cartPersons = PersonQuery::create()
                ->filterById($_SESSION['aPeopleCart'])
                ->orderByLastName()
                ->find();

            echo "<table class='table'>";
            echo '<tr>';
            echo '<td>&nbsp;</td>';
            echo '<td><b>' . gettext('Name') . '</b></td>';
            echo '<td class="text-center"><b>' . gettext('Assign Role') . '</b></td>';

            $count = 1;
            $sRowClass = 'RowColorA';
            foreach ($cartPersons as $cartPerson) {
                $sRowClass = AlternateRowStyle($sRowClass);

                echo '<tr class="' . $sRowClass . '">';
                echo '<td class="text-center">' . $count++ . '</td>';
                $personPhoto = new \ChurchCRM\dto\Photo('person', $cartPerson->getId());
                $photoIcon = '';
                if ($personPhoto->hasUploadedPhoto()) {
                    $photoIcon = ' <button class="btn btn-xs btn-outline-secondary view-person-photo" data-person-id="' . $cartPerson->getId() . '" title="' . gettext('View Photo') . '"><i class="fa-solid fa-camera"></i></button>';
                }
                echo '<td><a href="PersonView.php?PersonID=' . $cartPerson->getId() . '">' . FormatFullName($cartPerson->getTitle(), $cartPerson->getFirstName(), $cartPerson->getMiddleName(), $cartPerson->getLastName(), $cartPerson->getSuffix(), 1) . '</a>' . $photoIcon . '</td>';

                echo '<td class="text-center">';
                if ($cartPerson->getFamId() == 0) {
                    echo '<select name="role' . $cartPerson->getId() . '">' . $sRoleOptionsHTML . '</select>';
                } else {
                    echo gettext('Already in a family');
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</table>'; ?>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="mx-auto table table-hover">
            <tr>
                <td class="LabelColumn"><?= gettext('Add to Family') ?>:</td>
                <td class="TextColumn">
                    <?php
                    // Create the family select drop-down
                    echo '<select name="FamilyID">';
                    echo '<option value="0">' . gettext('Create new family') . '</option>';
                    foreach ($families as $family) {
                        echo sprintf('<option value="%s">%s</option>', $family->getId(), $family->getName());
                    }
                    echo '</select>'; ?>
                </td>
            </tr>

            <tr>
                <td></td>
                <td>
                    <p class="MediumLargeText"><?= gettext('If adding a new family, enter data below.') ?></p>
                </td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Family Name') ?>:</td>
                <td class="TextColumnWithBottomBorder"><input type="text" Name="FamilyName" value="<?= $sName ?>" maxlength="48"><span class="text-danger"><?= $sNameError ?></span></td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Wedding Date') ?>:</td>
                <td class="TextColumnWithBottomBorder"><input type="text" Name="WeddingDate" value="<?= $dWeddingDate ?>" maxlength="10" id="sel1" size="15" class="form-control pull-right active date-picker"><span class="text-danger"><?php echo '<BR>' . $sWeddingDateError ?></span></td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Use address/contact data from') ?>:</td>
                <td class="TextColumn">
                    <?php
                    echo '<select name="PersonAddress">';
                    echo '<option value="0">' . gettext('Only the new data below') . '</option>';

                    mysqli_data_seek($rsCartItems, 0);
                    while ($aRow = mysqli_fetch_array($rsCartItems)) {
                        if ($per_fam_ID == 0) {
                            echo sprintf('<option value="%s">%s %s</option>', $aRow['per_ID'], $aRow['per_FirstName'], $aRow['per_LastName']);
                        }
                    }

                    echo '</select>'; ?>
                </td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Address') ?> 1:</td>
                <td class="TextColumn"><input type="text" Name="Address1" value="<?= $sAddress1 ?>" size="50" maxlength="250"></td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Address') ?> 2:</td>
                <td class="TextColumn"><input type="text" Name="Address2" value="<?= $sAddress2 ?>" size="50" maxlength="250"></td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('City') ?>:</td>
                <td class="TextColumn"><input type="text" Name="City" value="<?= $sCity ?>" maxlength="50"></td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('State') ?>:</td>
                <td class="TextColumn">
                    <div id="stateOptionDiv">
                        <select id="State" name="State" class="form-control" data-user-selected="<?= $sState ?>" data-system-default="<?= SystemConfig::getValue('sDefaultState') ?>">
                        </select>
                    </div>
                    <div id="stateInputDiv" style="display: none;">
                        <input type="text" name="StateTextbox" id="StateTextbox" value="" size="20" maxlength="30">
                        <BR><?= gettext('(Enter state/province for countries without predefined states)') ?>
                    </div>
                </td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Zip') ?>:</td>
                <td class="TextColumn">
                    <input type="text" Name="Zip" value="<?= $sZip ?>" maxlength="10" size="8">
                </td>

            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Country') ?>:</td>
                <td class="TextColumnWithBottomBorder">
                    <select id="Country" name="Country" class="form-control" data-user-selected="<?= $sCountry ?>" data-system-default="<?= SystemConfig::getValue('sDefaultCountry') ?>">
                    </select>
                </td>
            </tr>

            <tr>
                <td>&nbsp;</td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Home Phone') ?>:</td>
                <td class="TextColumn">
                    <input type="text" Name="HomePhone" value="<?= $sHomePhone ?>" size="30" maxlength="30">
                    <input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) {
                                                                                    echo ' checked';
                                                                                } ?>><?= gettext('Do not auto-format') ?>
                </td>
            </tr>

            <tr>
                <td class="LabelColumn"><?= gettext('Email') ?>:</td>
                <td class="TextColumnWithBottomBorder"><input type="text" Name="Email" value="<?= $sEmail ?>" size="30" maxlength="50"></td>
            </tr>

        </table>
    </div>


    <p class="text-center">
        <BR>
        <input type="submit" class="btn btn-secondary" name="Submit" value="<?= gettext('Add to Family') ?>">
        <BR><BR>
    </p>
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

<?php
require_once __DIR__ . '/Include/Footer.php';
