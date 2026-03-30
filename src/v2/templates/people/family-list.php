<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Family Listing');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-filter"></i> <?= gettext('Filters') ?></h3>
    </div>
    <div class="card-body">
        <form id="family-filters" method="get" action="<?= SystemURLs::getRootPath() ?>/v2/family/">
            <div class="row">
                <div class="col-lg-6">
                    <div class="mb-3">
                        <label for="filterCity"><?= gettext('City') ?></label>
                        <input type="text" class="form-control family-filter-field" id="filterCity" name="City" value="<?= InputUtils::escapeAttribute($filterCity ?? '') ?>" placeholder="<?= gettext('City') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="familyActiveStatus"><?= gettext('Status') ?></label>
                        <select id="familyActiveStatus" name="familyActiveStatus" class="form-select family-filter-field">
                            <option value="all" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'all') ? 'selected' : '' ?>><?= gettext('All') ?></option>
                            <option value="active" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'active') ? 'selected' : '' ?>><?= gettext('Active') ?></option>
                            <option value="inactive" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'inactive') ? 'selected' : '' ?>><?= gettext('Inactive') ?></option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mb-3">
                        <label for="filterState"><?= gettext('State') ?></label>
                        <input type="text" class="form-control family-filter-field" id="filterState" name="State" value="<?= InputUtils::escapeAttribute($filterState ?? '') ?>" placeholder="<?= gettext('State') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="filterGeocoded"><?= gettext('Address Status') ?></label>
                        <select id="filterGeocoded" name="geocoded" class="form-select family-filter-field">
                            <option value="all" <?= (isset($filterGeocoded) && $filterGeocoded === 'all') ? 'selected' : '' ?>><?= gettext('All') ?></option>
                            <option value="unverified" <?= (isset($filterGeocoded) && $filterGeocoded === 'unverified') ? 'selected' : '' ?>><?= gettext('Unverified Addresses') ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button id="ClearFamilyFilter" type="button" class="btn btn-secondary w-100">
                        <i class="fa-solid fa-times"></i> <?= gettext('Clear Filter') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-home"></i> <?= gettext('Families') ?></h3>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table" id="families">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Address') ?></th>
                    <th><?= gettext('Address Status') ?></th>
                    <th><?= gettext('Home Phone') ?></th>
                    <th><?= gettext('Email') ?></th>
                    <th><?= gettext('Created') ?></th>
                    <th><?= gettext('Edited') ?></th>
                    <th class="text-end no-export w-1"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($families as $family) {
                    /* @var Family $family */
                    $familyMembers = $family->getPeople();
                    $memberCount = count($familyMembers);
                    
                    // Check if all family members are in cart
                    $isInCart = false;
                    if (isset($_SESSION['aPeopleCart']) && $memberCount > 0) {
                        $allInCart = true;
                        foreach ($familyMembers as $member) {
                            if (!in_array($member->getId(), $_SESSION['aPeopleCart'], false)) {
                                $allInCart = false;
                                break;
                            }
                        }
                        $isInCart = $allInCart;
                    }
                ?>
                <tr>
                    
                    <td>
                        <?= $family->getLinkHtml(true, true) ?>
                        <?php if (!$family->isActive()) { ?>
                            <span class="badge bg-light text-dark ms-2" title="<?= gettext('Inactive') ?>">
                                <i class="fa-solid fa-power-off"></i> <?= gettext('Inactive') ?>
                            </span>
                        <?php } ?>
                    </td>
                    <td>
                        <?= InputUtils::escapeHTML($family->getAddress1()) ?>
                        <?php if ($family->getAddress2()): ?>
                            <br><?= InputUtils::escapeHTML($family->getAddress2()) ?>
                        <?php endif; ?>
                        <br><?= $family->getCityStateShort() ?>
                    </td>
                    <td data-order="<?= $family->hasLatitudeAndLongitude() ? 2 : ($family->hasAddress() ? 1 : 0) ?>">
                        <?php if ($family->hasLatitudeAndLongitude()): ?>
                            <span class="badge bg-green-lt text-green" title="<?= gettext('Geocoded') ?>">
                                <i class="fa-solid fa-check"></i> <?= gettext('Geocoded') ?>
                            </span>
                        <?php elseif ($family->hasAddress()): ?>
                            <span class="badge bg-warning text-dark" title="<?= gettext('Unverified') ?>">
                                <i class="fa-solid fa-triangle-exclamation"></i> <?= gettext('Unverified') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= InputUtils::escapeHTML($family->getHomePhone()) ?></td>
                    <td><?php if ($family->getEmail()): ?><a href="mailto:<?= InputUtils::escapeAttribute($family->getEmail()) ?>"><?= InputUtils::escapeHTML($family->getEmail()) ?></a><?php endif; ?></td>
                    <td><?php if ($family->getDateEntered() !== null) { echo $family->getDateEntered()->format('Y-m-d'); } ?></td>
                    <td><?php if ($family->getDateLastEdited() !== null) { echo $family->getDateLastEdited()->format('Y-m-d'); } ?></td>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $family->getId() ?>">
                                    <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                </a>
                                <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>">
                                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <button type="button"
                                    class="dropdown-item <?= $isInCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                    data-cart-id="<?= $family->getId() ?>"
                                    data-cart-type="family"
                                    data-label-add="<?= gettext('Add to Cart') ?>"
                                    data-label-remove="<?= gettext('Remove from Cart') ?>">
                                    <i class="<?= $isInCart ? 'ti ti-shopping-cart-off' : 'ti ti-shopping-cart-plus' ?> me-2"></i>
                                    <span class="cart-label"><?= $isInCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                </button>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="<?= SystemURLs::getRootPath() ?>/SelectDelete.php?FamilyID=<?= $family->getId() ?>">
                                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    let oTable;
    
    // Initialize DataTable with pagination and search
    if ($.fn.DataTable) {
        let dataTableConfig = {
            // Enable pagination
"paging": true,
"pageLength": 10,
"lengthChange": true,
"searching": true,
            // Column definitions
            // Column order: 0=Name, 1=Address, 2=Address Status, 3=Home Phone, 4=Email, 5=Created, 6=Edited, 7=Actions
"columnDefs": [
                {
"targets": 2, // Address Status column (0-indexed) — uses data-order attribute for sorting
"orderable": true,
"searchable": false
                },
                {
"targets": -1, // Last column (Actions)
"orderable": false,
"searchable": false
                }
            ]
        };
        
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        oTable = $('#families').DataTable(dataTableConfig);
    }
    
    // Auto-submit filters when any field changes
    $('.family-filter-field').on('change', function() {
        $('#family-filters').submit();
    });
    
    // Clear filter button handler
    $('#ClearFamilyFilter').on('click', function(e) {
        e.preventDefault();
        // Reset all filter fields
        $('#filterCity').val('');
        $('#filterState').val('');
        $('#familyActiveStatus').val('all');
        $('#filterGeocoded').val('all');
        // Submit the form to clear filters
        $('#family-filters').submit();
    });
});
</script>

<?php
// Load compiled webpack asset for family list interactions
echo '<script src="' . SystemURLs::getRootPath() . '/skin/v2/people-family-list.min.js"></script>';
?>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
