<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Family Listing');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="card card-primary mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-filter"></i> <?= gettext('Filters') ?></h3>
    </div>
    <div class="card-body">
        <form id="family-filters" method="get" action="<?= SystemURLs::getRootPath() ?>/v2/family/">
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="filterCity"><?= gettext('City') ?></label>
                        <input type="text" class="form-control family-filter-field" id="filterCity" name="City" value="<?= InputUtils::escapeAttribute($filterCity ?? '') ?>" placeholder="<?= gettext('City') ?>">
                    </div>
                    <div class="form-group">
                        <label for="familyActiveStatus"><?= gettext('Status') ?></label>
                        <select id="familyActiveStatus" name="familyActiveStatus" class="form-control family-filter-field">
                            <option value="all" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'all') ? 'selected' : '' ?>><?= gettext('All') ?></option>
                            <option value="active" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'active') ? 'selected' : '' ?>><?= gettext('Active') ?></option>
                            <option value="inactive" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'inactive') ? 'selected' : '' ?>><?= gettext('Inactive') ?></option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="filterState"><?= gettext('State') ?></label>
                        <input type="text" class="form-control family-filter-field" id="filterState" name="State" value="<?= InputUtils::escapeAttribute($filterState ?? '') ?>" placeholder="<?= gettext('State') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button id="ClearFamilyFilter" type="button" class="btn btn-secondary btn-block">
                        <i class="fa-solid fa-times"></i> <?= gettext('Clear Filter') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-home"></i> <?= gettext('Families') ?></h3>
    </div>
    <div class="card-body p-2">
        <table class="table table-striped table-hover mb-0" id="families">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Address') ?></th>
                    <th><?= gettext('Home Phone') ?></th>
                    <th><?= gettext('Email') ?></th>
                    <th><?= gettext('Created') ?></th>
                    <th><?= gettext('Edited') ?></th>
                    <th class="text-right" width="150"><?= gettext('Actions') ?></th>
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
                            <span class="badge badge-secondary ml-2" title="<?= gettext('Inactive') ?>">
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
                    <td><?= InputUtils::escapeHTML($family->getHomePhone()) ?></td>
                    <td><?php if ($family->getEmail()): ?><a href="mailto:<?= InputUtils::escapeAttribute($family->getEmail()) ?>"><?= InputUtils::escapeHTML($family->getEmail()) ?></a><?php endif; ?></td>
                    <td><?php if ($family->getDateEntered() !== null) { echo $family->getDateEntered()->format('Y-m-d'); } ?></td>
                    <td><?php if ($family->getDateLastEdited() !== null) { echo $family->getDateLastEdited()->format('Y-m-d'); } ?></td>
                    <td class="text-right">
                        <a href='<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>' class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <?php if ($isInCart) { ?>
                            <span class="RemoveFromCart ml-1" data-cart-id="<?= $family->getId() ?>" data-cart-type="family">
                                <button type="button" class="btn btn-sm btn-danger" title="<?= gettext('Remove from Cart') ?>">
                                    <i class="fa-solid fa-shopping-cart fa-sm"></i>
                                </button>
                            </span>
                        <?php } else { ?>
                            <span class="AddToCart ml-1" data-cart-id="<?= $family->getId() ?>" data-cart-type="family">
                                <button type="button" class="btn btn-sm btn-primary" title="<?= gettext('Add to Cart') ?>">
                                    <i class="fa-solid fa-cart-plus fa-sm"></i>
                                </button>
                            </span>
                        <?php } ?>
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
            "columnDefs": [
                {
                    "targets": -1, // Last column (Actions)
                    "orderable": false,
                    "searchable": false
                }
            ],
            "language": {
                "search": "<?= gettext('Search') ?>:",
                "paginate": {
                    "first": "<?= gettext('First') ?>",
                    "last": "<?= gettext('Last') ?>",
                    "next": "<?= gettext('Next') ?>",
                    "previous": "<?= gettext('Previous') ?>"
                },
                "lengthMenu": "<?= gettext('Show') ?> _MENU_ <?= gettext('entries') ?>",
                "info": "<?= gettext('Showing') ?> _START_ <?= gettext('to') ?> _END_ <?= gettext('of') ?> _TOTAL_ <?= gettext('entries') ?>"
            }
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
