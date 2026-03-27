<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;

// Security: user must have MenuOptions permission to use this page
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

// Get the type to display
$sType = InputUtils::legacyFilterInput($_GET['Type'], 'char', 1);

// Based on the type, set the TypeName and icon
switch ($sType) {
    case 'p':
        $sTypeName = gettext('Person');
        $sTypeIcon = 'fa-person-half-dress';
        break;

    case 'f':
        $sTypeName = gettext('Family');
        $sTypeIcon = 'fa-people-roof';
        break;

    case 'g':
        $sTypeName = gettext('Group');
        $sTypeIcon = 'fa-user-group';
        break;

    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

$sPageTitle = $sTypeName . ' ' . gettext('Property List');

// Get the properties, grouped by type
$sSQL = "SELECT pro_ID, pro_Name, pro_Description, pro_Prompt, prt_ID, prt_Name
         FROM property_pro
         JOIN propertytype_prt ON prt_ID = pro_prt_ID
         WHERE pro_Class = '" . $sType . "'
         ORDER BY prt_Name, pro_Name";
$rsProperties = RunQuery($sSQL);

// Pre-process into groups
$groups = [];
while ($aRow = mysqli_fetch_assoc($rsProperties)) {
    $prtId = (int) $aRow['prt_ID'];
    if (!isset($groups[$prtId])) {
        $groups[$prtId] = [
            'name'       => $aRow['prt_Name'],
            'properties' => [],
        ];
    }
    $groups[$prtId]['properties'][] = $aRow;
}

$canManage = AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled();
$colCount = $canManage ? 4 : 3;

$sPageSubtitle = gettext('Define custom properties that can be assigned to') . ' ' . strtolower(InputUtils::escapeHTML($sTypeName)) . ' ' . gettext('records');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [$sTypeName . ' ' . gettext('Properties')],
]);
require_once __DIR__ . '/Include/Header.php';
?>

<div class="container-fluid">

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <!-- Type switcher tabs -->
            <div class="card-title">
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= gettext('Filter by Type') ?>">
                    <a href="PropertyList.php?Type=p" class="btn btn-outline-secondary <?= $sType === 'p' ? 'active' : '' ?>">
                        <i class="fa-solid fa-person-half-dress me-1"></i><?= gettext('Person') ?>
                    </a>
                    <a href="PropertyList.php?Type=f" class="btn btn-outline-secondary <?= $sType === 'f' ? 'active' : '' ?>">
                        <i class="fa-solid fa-people-roof me-1"></i><?= gettext('Family') ?>
                    </a>
                    <a href="PropertyList.php?Type=g" class="btn btn-outline-secondary <?= $sType === 'g' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-group me-1"></i><?= gettext('Group') ?>
                    </a>
                </div>
            </div>

            <!-- Actions -->
            <div class="card-options">
                <a href="PropertyTypeList.php?class=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fa-solid fa-tags me-1"></i><?= gettext('Manage') ?> <?= InputUtils::escapeHTML($sTypeName) ?> <?= gettext('Property Types') ?>
                </a>
                <?php if ($canManage): ?>
                <a href="PropertyEditor.php?Type=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <table class="table table-hover table-vcenter card-table">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Description') ?></th>
                    <th><?= gettext('Prompt') ?></th>
                    <?php if ($canManage): ?>
                    <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                <tr>
                    <td colspan="<?= $colCount ?>" class="text-center text-muted py-5">
                        <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                        <?= gettext('No properties defined yet') ?>
                        <?php if ($canManage): ?>
                        <br>
                        <a href="PropertyEditor.php?Type=<?= InputUtils::escapeAttribute($sType) ?>" class="btn btn-primary btn-sm mt-3">
                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Your First Property') ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                    <!-- Property type group header -->
                    <tr class="table-active">
                        <td colspan="<?= $colCount ?>">
                            <span class="fw-bold text-secondary">
                                <i class="fa-solid fa-folder-open me-1"></i><?= InputUtils::escapeHTML($group['name']) ?>
                            </span>
                            <span class="badge bg-primary text-white ms-2"><?= count($group['properties']) ?></span>
                        </td>
                    </tr>
                    <?php foreach ($group['properties'] as $prop): ?>
                    <tr>
                        <td>
                            <strong><?= InputUtils::escapeHTML($prop['pro_Name']) ?></strong>
                        </td>
                        <td class="text-muted">
                            <?php if ($prop['pro_Description'] !== ''): ?>
                            ...<?= InputUtils::escapeHTML($prop['pro_Description']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?= InputUtils::escapeHTML($prop['pro_Prompt']) ?>
                        </td>
                        <?php if ($canManage): ?>
                        <td class="text-center w-1">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="PropertyEditor.php?PropertyID=<?= InputUtils::escapeAttribute($prop['pro_ID']) ?>&Type=<?= InputUtils::escapeAttribute($sType) ?>">
                                        <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger delete-property-btn"
                                        data-property-id="<?= InputUtils::escapeAttribute($prop['pro_ID']) ?>"
                                        data-property-name="<?= InputUtils::escapeAttribute($prop['pro_Name']) ?>">
                                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    window.CRM.onLocalesReady(function () {
        $(document).on('click', '.delete-property-btn', function () {
            var btn = $(this);
            var propertyId = btn.data('property-id');
            var propertyName = btn.data('property-name');
            bootbox.confirm({
                title: '<?= gettext("Confirm Property Deletion") ?>',
                message: '<p class="text-warning"><strong><?= gettext("Warning:") ?></strong> ' +
                    '<?= gettext("Deleting this property will also remove all its assignments from any People, Family, or Group records.") ?>' +
                    '</p><p><?= gettext("Delete") ?>: <strong>' + window.CRM.escapeHtml(propertyName) + '</strong></p>',
                buttons: {
                    confirm: { label: '<?= gettext("Yes, delete") ?>', className: 'btn-danger' },
                    cancel:  { label: '<?= gettext("Cancel") ?>',     className: 'btn-secondary' }
                },
                callback: function (result) {
                    if (result) {
                        window.CRM.APIRequest({
                            method: 'DELETE',
                            path: 'people/properties/definition/' + propertyId,
                        }).done(function () {
                            btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                        });
                    }
                }
            });
        });
    });
});
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';
