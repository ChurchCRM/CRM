<?php
/**
 * View: /people/cart/to-family
 *
 * Assign all eligible cart persons to a new or existing family.
 * Tabler-styled form with empty state, role table, family selector,
 * and progressive new-family fieldset.
 *
 * Variables injected by the route handler:
 *   $sPageTitle      string
 *   $sPageSubtitle   string
 *   $aBreadcrumbs    array (from PageHeader::breadcrumbs)
 *   $cartPersons     ObjectCollection<Person>
 *   $familyRoles     ObjectCollection<ListOption>
 *   $families        ObjectCollection<Family>
 *   $sErrorText      string (HTML-encoded; empty = no error)
 *   $sSuccessText    string
 *   $formValues      array  (sticky POST values on re-render)
 */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$hasCartPersons = count($cartPersons) > 0;
$rootPath       = SystemURLs::getRootPath();
?>

<div class="container-xl">

<?php if (!$hasCartPersons): ?>
    <!-- ------------------------------------------------------------------ -->
    <!-- Empty state                                                         -->
    <!-- ------------------------------------------------------------------ -->
    <div class="empty mt-4">
        <div class="empty-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shopping-cart-off" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>
                <path d="M3 3h2l3.5 12.5"/>
                <path d="M9 3h11l-1.68 8.39m-1.32 .61h-6l-1 -4"/>
                <path d="M3 3l18 18"/>
            </svg>
        </div>
        <p class="empty-title"><?= gettext('Your cart is empty') ?></p>
        <p class="empty-subtitle text-secondary">
            <?= gettext('Add people to your cart before assigning them to a family.') ?>
        </p>
        <div class="empty-action">
            <a href="<?= $rootPath ?>/people/cart" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M5 12l14 0"/><path d="M5 12l6 6"/><path d="M5 12l6 -6"/>
                </svg>
                <?= gettext('Back to Cart') ?>
            </a>
        </div>
    </div>

<?php else: ?>
    <!-- ------------------------------------------------------------------ -->
    <!-- Assign form                                                         -->
    <!-- ------------------------------------------------------------------ -->
    <form id="cartToFamilyForm" method="post" novalidate
          action="<?= $rootPath ?>/people/cart/to-family">
        <?= CSRFUtils::getTokenInputField('cart_to_family') ?>

        <?php if (!empty($sErrorText)): ?>
        <div class="alert alert-danger" role="alert">
            <div class="d-flex">
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.871l-8.106 -13.534a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/>
                    </svg>
                </div>
                <div id="cartToFamilyError"><?= $sErrorText ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ---- Role assignment card ------------------------------------ -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h3 class="card-title mb-0"><?= gettext('People to assign') ?></h3>
                <div class="d-flex align-items-center gap-2">
                    <label for="assignRoleToAll" class="form-label mb-0 text-secondary small">
                        <?= gettext('Assign role to all') ?>:
                    </label>
                    <select id="assignRoleToAll" class="form-select form-select-sm" style="width:auto">
                        <option value=""><?= gettext('— pick —') ?></option>
                        <?php foreach ($familyRoles as $role): ?>
                        <option value="<?= $role->getOptionId() ?>">
                            <?= InputUtils::escapeHTML($role->getOptionName()) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th style="width:52px"></th>
                            <th><?= gettext('Name') ?></th>
                            <th style="width:220px"><?= gettext('Family Role') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cartPersons as $cartPerson): ?>
                        <?php $alreadyInFamily = $cartPerson->getFamId() !== 0; ?>
                        <tr<?= $alreadyInFamily ? ' class="text-secondary"' : '' ?>>
                            <td>
                                <img
                                    data-image-entity-type="person"
                                    data-image-entity-id="<?= $cartPerson->getId() ?>"
                                    class="avatar avatar-sm rounded-circle"
                                    alt=""
                                />
                            </td>
                            <td>
                                <a href="<?= $cartPerson->getViewURI() ?>">
                                    <?= InputUtils::escapeHTML($cartPerson->getFullName()) ?>
                                </a>
                                <?php if ($alreadyInFamily): ?>
                                <?php $fam = $cartPerson->getFamily(); ?>
                                <span class="badge bg-blue-lt ms-1">
                                    <?php if ($fam): ?>
                                        <?= gettext('Already in') ?>
                                        <a href="<?= $rootPath ?>/people/family/<?= $fam->getId() ?>" class="text-reset">
                                            <?= InputUtils::escapeHTML($fam->getName()) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= gettext('Already in a family') ?>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$alreadyInFamily): ?>
                                <select
                                    id="role<?= $cartPerson->getId() ?>"
                                    name="role<?= $cartPerson->getId() ?>"
                                    class="form-select form-select-sm role-select">
                                    <option value=""><?= gettext('— Select role —') ?></option>
                                    <?php foreach ($familyRoles as $role): ?>
                                    <option
                                        value="<?= $role->getOptionId() ?>"
                                        <?php
                                        $savedRole = $formValues['role' . $cartPerson->getId()] ?? '';
                                        if ((string)$savedRole === (string)$role->getOptionId()) {
                                            echo 'selected';
                                        }
                                        ?>>
                                        <?= InputUtils::escapeHTML($role->getOptionName()) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                <span class="text-secondary small">
                                    <?= gettext('Not included') ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ---- Family selector card ------------------------------------ -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Choose family') ?></h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="FamilyID" class="form-label">
                        <?= gettext('Target family') ?>
                    </label>
                    <select id="FamilyID" name="FamilyID" class="form-select">
                        <option value="0"><?= gettext('Create new family…') ?></option>
                        <?php foreach ($families as $family): ?>
                        <option
                            value="<?= $family->getId() ?>"
                            <?php
                            $savedFamilyId = $formValues['FamilyID'] ?? '0';
                            if ((string)$savedFamilyId === (string)$family->getId()) {
                                echo 'selected';
                            }
                            ?>>
                            <?= InputUtils::escapeHTML($family->getName()) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ---- New-family fieldset (progressive disclosure) ---- -->
                <fieldset id="newFamilyFieldset">
                    <legend class="fieldset-label text-secondary mb-3">
                        <?= gettext('New family details') ?>
                    </legend>

                    <div class="mb-3">
                        <label for="familyNameInput" class="form-label required">
                            <?= gettext('Family Name') ?>
                        </label>
                        <input
                            type="text"
                            id="familyNameInput"
                            name="FamilyName"
                            class="form-control"
                            maxlength="48"
                            value="<?= InputUtils::escapeAttribute($formValues['FamilyName'] ?? '') ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="WeddingDate" class="form-label">
                            <?= gettext('Wedding Date') ?>
                        </label>
                        <input
                            type="date"
                            id="WeddingDate"
                            name="WeddingDate"
                            class="form-control"
                            value="<?= InputUtils::escapeAttribute($formValues['WeddingDate'] ?? '') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="Address1" class="form-label">
                                <?= gettext('Address') ?> 1
                            </label>
                            <input type="text" id="Address1" name="Address1" class="form-control"
                                   maxlength="250"
                                   value="<?= InputUtils::escapeAttribute($formValues['Address1'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="Address2" class="form-label">
                                <?= gettext('Address') ?> 2
                            </label>
                            <input type="text" id="Address2" name="Address2" class="form-control"
                                   maxlength="250"
                                   value="<?= InputUtils::escapeAttribute($formValues['Address2'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="City" class="form-label"><?= gettext('City') ?></label>
                            <input type="text" id="City" name="City" class="form-control"
                                   maxlength="50"
                                   value="<?= InputUtils::escapeAttribute($formValues['City'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="Zip" class="form-label"><?= gettext('Zip / Postal Code') ?></label>
                            <input type="text" id="Zip" name="Zip" class="form-control"
                                   maxlength="10"
                                   value="<?= InputUtils::escapeAttribute($formValues['Zip'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="State" class="form-label"><?= gettext('State / Province') ?></label>
                            <div id="stateOptionDiv">
                                <select id="State" name="State" class="form-select"
                                    data-user-selected="<?= InputUtils::escapeAttribute($formValues['State'] ?? '') ?>"
                                    data-system-default="<?= InputUtils::escapeAttribute(SystemConfig::getValue('sDefaultState')) ?>">
                                </select>
                            </div>
                            <div id="stateInputDiv" class="d-none">
                                <input type="text" class="form-control" name="StateTextbox" id="StateTextbox"
                                       value="<?= InputUtils::escapeAttribute($formValues['StateTextbox'] ?? '') ?>"
                                       maxlength="30">
                                <small class="text-secondary">
                                    <?= gettext('Enter state/province for countries without predefined states') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="Country" class="form-label"><?= gettext('Country') ?></label>
                            <select id="Country" name="Country" class="form-select"
                                data-user-selected="<?= InputUtils::escapeAttribute($formValues['Country'] ?? '') ?>"
                                data-system-default="<?= InputUtils::escapeAttribute(SystemConfig::getValue('sDefaultCountry')) ?>">
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="HomePhone" class="form-label"><?= gettext('Home Phone') ?></label>
                            <input type="text" id="HomePhone" name="HomePhone" class="form-control"
                                   maxlength="30"
                                   value="<?= InputUtils::escapeAttribute($formValues['HomePhone'] ?? '') ?>"
                                   data-inputmask='"mask":"<?= SystemConfig::getValueForAttr('sPhoneFormat') ?>"'
                                   data-mask>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox"
                                       name="NoFormat_HomePhone" id="NoFormat_HomePhone" value="1"
                                       <?= !empty($formValues['NoFormat_HomePhone']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="NoFormat_HomePhone">
                                    <?= gettext('Do not auto-format') ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="Email" class="form-label"><?= gettext('Email') ?></label>
                            <input type="email" id="Email" name="Email" class="form-control"
                                   maxlength="50"
                                   value="<?= InputUtils::escapeAttribute($formValues['Email'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>
            </div><!-- /card-body -->
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="<?= $rootPath ?>/people/cart" class="btn btn-secondary">
                    <?= gettext('Cancel') ?>
                </a>
                <button id="cartToFamilySubmit" type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M5 12l5 5l10 -10"/>
                    </svg>
                    <?= gettext('Assign to Family') ?>
                </button>
            </div>
        </div><!-- /card -->

    </form><!-- /cartToFamilyForm -->

<?php endif; ?>
</div><!-- /container-xl -->

<script src="<?= SystemURLs::assetVersioned('/skin/js/DropdownManager.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/CartToFamily.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    'use strict';

    // ── Progressive disclosure: show/hide new-family fieldset ──────────────
    var familySelect   = document.getElementById('FamilyID');
    var newFamilyFs    = document.getElementById('newFamilyFieldset');
    var familyNameInput = document.getElementById('familyNameInput');

    function toggleNewFamily() {
        var isNew = familySelect.value === '0';
        newFamilyFs.style.display = isNew ? '' : 'none';
        if (familyNameInput) {
            familyNameInput.required = isNew;
        }
    }

    if (familySelect && newFamilyFs) {
        familySelect.addEventListener('change', toggleNewFamily);
        toggleNewFamily(); // apply on load
    }

    // ── Assign role to all eligible rows ───────────────────────────────────
    var assignAllSelect = document.getElementById('assignRoleToAll');
    if (assignAllSelect) {
        assignAllSelect.addEventListener('change', function () {
            var val = this.value;
            document.querySelectorAll('select.role-select').forEach(function (sel) {
                if (!sel.disabled && val !== '') {
                    sel.value = val;
                }
            });
        });
    }
}());
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
