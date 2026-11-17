<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

$csrfToken = CSRFUtils::generateToken();
$breadcrumbParent = match ($sType) {
    'g' => [gettext('Groups'), '/groups/dashboard'],
    default => [gettext('People'), '/people/dashboard'],
};
$aBreadcrumbs = PageHeader::breadcrumbs(array_filter([
    $breadcrumbParent,
    [$sTypeName . ' ' . gettext('Properties')],
]));
if ($sType === 'p' || $sType === 'f') {
    $sPageHeaderButtons = PageHeader::peopleSettingsButton();
}
require_once __DIR__ . '/../../Include/Header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= gettext('Filter by Type') ?>">
                    <a href="<?= $sRootPath ?>/people/properties/p" class="btn btn-outline-secondary <?= $sType === 'p' ? 'active' : '' ?>">
                        <i class="fa-solid fa-person-half-dress me-1"></i><?= gettext('Person') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/people/properties/f" class="btn btn-outline-secondary <?= $sType === 'f' ? 'active' : '' ?>">
                        <i class="fa-solid fa-people-roof me-1"></i><?= gettext('Family') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/people/properties/g" class="btn btn-outline-secondary <?= $sType === 'g' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-group me-1"></i><?= gettext('Group') ?>
                    </a>
                </div>
            </div>
            <div class="card-options">
                <a href="<?= $sRootPath ?>/people/properties/types/<?= $sType ?>" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fa-solid fa-tags me-1"></i><?= gettext('Manage') ?> <?= InputUtils::escapeHTML($sTypeName) ?> <?= gettext('Property Types') ?>
                </a>
                <a href="<?= $sRootPath ?>/admin/properties/<?= $sRecordType ?>/new" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New') ?>
                </a>
            </div>
        </div>
        <table class="table table-hover table-vcenter card-table">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Description') ?></th>
                    <th><?= gettext('Prompt') ?></th>
                    <th class="text-center w-1"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($groups)): ?>
                <tr>
                    <td colspan="4" class="text-center text-body-secondary py-5">
                        <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                        <?= gettext('No properties defined yet') ?>
                        <br>
                        <a href="<?= $sRootPath ?>/admin/properties/<?= $sRecordType ?>/new" class="btn btn-primary btn-sm mt-3">
                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Your First Property') ?>
                        </a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <tr class="table-active">
                        <td colspan="4">
                            <span class="fw-bold text-secondary">
                                <i class="fa-solid fa-folder-open me-1"></i><?= InputUtils::escapeHTML($group['name']) ?>
                            </span>
                            <span class="badge bg-primary text-white ms-2"><?= count($group['properties']) ?></span>
                        </td>
                    </tr>
                    <?php foreach ($group['properties'] as $prop): ?>
                    <tr>
                        <td><strong><?= InputUtils::escapeHTML($prop['pro_Name']) ?></strong></td>
                        <td class="text-body-secondary"><?= InputUtils::escapeHTML($prop['pro_Description']) ?></td>
                        <td class="text-body-secondary"><?= InputUtils::escapeHTML($prop['pro_Prompt']) ?></td>
                        <td class="text-center w-1">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="<?= $sRootPath ?>/admin/properties/<?= (int) $prop['pro_ID'] ?>/edit">
                                        <i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger delete-property-btn"
                                        data-property-id="<?= (int) $prop['pro_ID'] ?>"
                                        data-property-name="<?= InputUtils::escapeAttribute($prop['pro_Name']) ?>"
                                        data-csrf-token="<?= InputUtils::escapeAttribute($csrfToken) ?>">
                                        <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-property-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var propertyId = this.dataset.propertyId;
            var propertyName = this.dataset.propertyName;
            var csrf = this.dataset.csrfToken;
            window.CRM.confirmAction(
                '<?= gettext("Confirm Property Deletion") ?>',
                '<p class="text-warning"><strong><?= gettext("Warning") ?>:</strong> <?= gettext("Deleting this property will also remove all its assignments from any People, Family, or Group records.") ?></p><p><?= gettext("Delete") ?>: <strong>' + window.CRM.escapeHtml(propertyName) + '</strong></p>',
                function () {
                    window.CRM.AdminAPIRequest({
                        method: 'DELETE',
                        path: 'properties/definition/' + propertyId,
                        headers: { 'X-CSRF-Token': csrf },
                    }).done(function () {
                        btn.closest('tr').remove();
                        window.CRM.notify('<?= gettext("Property deleted") ?>', 'success');
                    });
                },
                { confirmLabel: '<?= gettext("Yes, delete") ?>', cancelLabel: '<?= gettext("Cancel") ?>' }
            );
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../Include/Footer.php'; ?>
