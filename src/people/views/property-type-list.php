<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

$csrfToken = CSRFUtils::generateToken();

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('Property Types')],
]);
require_once __DIR__ . '/../../Include/Header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= gettext('Filter by Class') ?>">
                    <a href="<?= $sRootPath ?>/people/properties/types" class="btn btn-outline-secondary <?= $filterClass === '' ? 'active' : '' ?>">
                        <i class="fa-solid fa-list me-1"></i><?= gettext('All') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/people/properties/types/p" class="btn btn-outline-secondary <?= $filterClass === 'p' ? 'active' : '' ?>">
                        <i class="fa-solid fa-person-half-dress me-1"></i><?= gettext('Person') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/people/properties/types/f" class="btn btn-outline-secondary <?= $filterClass === 'f' ? 'active' : '' ?>">
                        <i class="fa-solid fa-people-roof me-1"></i><?= gettext('Family') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/people/properties/types/g" class="btn btn-outline-secondary <?= $filterClass === 'g' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-group me-1"></i><?= gettext('Group') ?>
                    </a>
                </div>
            </div>
            <div class="card-options">
                <a href="<?= $sRootPath ?>/admin/property-types/new" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New') ?>
                </a>
            </div>
        </div>
        <table class="table table-hover table-vcenter card-table">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Class') ?></th>
                    <th class="text-center" title="<?= gettext('Properties / Assignments') ?>"><?= gettext('In Use') ?></th>
                    <th class="text-center w-1"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($types)): ?>
                <tr>
                    <td colspan="4" class="text-center text-body-secondary py-4">
                        <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                        <?= gettext('No property types found') ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($types as $type): ?>
                <tr data-type-id="<?= (int) $type['id'] ?>">
                    <td><strong><?= InputUtils::escapeHTML($type['name']) ?></strong></td>
                    <td>
                        <?php
                        $badgeClass = match ($type['class']) {
                            'p' => 'bg-blue-lt text-blue',
                            'f' => 'bg-teal-lt text-teal',
                            'g' => 'bg-purple-lt text-purple',
                            default => 'bg-secondary',
                        };
                        $icon = match ($type['class']) {
                            'p' => 'fa-person-half-dress',
                            'f' => 'fa-people-roof',
                            'g' => 'fa-user-group',
                            default => 'fa-tag',
                        };
                        $label = match ($type['class']) {
                            'p' => gettext('Person'),
                            'f' => gettext('Family'),
                            'g' => gettext('Group'),
                            default => '',
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><i class="fa-solid <?= $icon ?> me-1"></i><?= $label ?></span>
                    </td>
                    <td class="text-center">
                        <?php
                        $props = (int) $type['properties'];
                        $assigns = (int) ($type['assignments'] ?? 0);
                        $used = $props > 0 || $assigns > 0;
                        $recordLabel = match ($type['class']) {
                            'p' => gettext('people'),
                            'f' => gettext('families'),
                            'g' => gettext('groups'),
                            default => gettext('records'),
                        };
                        ?>
                        <?php if ($used): ?>
                            <span class="badge bg-green-lt text-green" title="<?= $props ?> <?= gettext('properties') ?>, <?= $assigns ?> <?= $recordLabel ?>">
                                <i class="fa-solid fa-check me-1"></i><?= $props ?> / <?= $assigns ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="<?= $sRootPath ?>/admin/property-types/<?= (int) $type['id'] ?>/edit">
                                    <i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger delete-type-btn"
                                        data-id="<?= (int) $type['id'] ?>"
                                        data-name="<?= InputUtils::escapeAttribute($type['name']) ?>"
                                        data-class="<?= $type['class'] ?>"
                                        data-properties="<?= (int) $type['properties'] ?>"
                                        data-assignments="<?= (int) ($type['assignments'] ?? 0) ?>"
                                        data-csrf-token="<?= InputUtils::escapeAttribute($csrfToken) ?>">
                                    <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-type-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const cls = this.dataset.class;
            const props = parseInt(this.dataset.properties || '0', 10);
            const assigns = parseInt(this.dataset.assignments || '0', 10);
            const csrfToken = this.dataset.csrfToken || '';
            const assignsSentence = cls === 'p'
                ? '<?= gettext("%d people currently have properties of this type.") ?>'
                : (cls === 'f'
                    ? '<?= gettext("%d families currently have properties of this type.") ?>'
                    : '<?= gettext("%d groups currently have properties of this type.") ?>');
            let message = '<p><?= gettext("Please confirm deletion of this Property Type") ?>: <strong>' + window.CRM.escapeHtml(name) + '</strong></p>';
            if (props > 0 || assigns > 0) {
                let warn = '<div class="alert alert-warning"><strong><?= gettext("Warning") ?>:</strong> ';
                if (assigns > 0) {
                    warn += assignsSentence.replace('%d', assigns) + ' ';
                }
                if (props > 0) {
                    warn += '<?= gettext("Deleting it will also remove %d properties and all their assignments so nothing is left hanging.") ?>'.replace('%d', props);
                }
                warn += '</div>';
                message = warn + message;
            }
            window.CRM.confirmAction(
                '<?= gettext("Delete Confirmation") ?>',
                message,
                () => {
                    fetch('<?= $sRootPath ?>/people/properties/types/' + id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-Token': csrfToken }
                    }).then(r => {
                        if (r.ok) {
                            this.closest('tr').remove();
                            window.CRM.notify('<?= gettext("Property type deleted") ?>', 'success');
                        } else {
                            window.CRM.notify('<?= gettext("Failed to delete") ?>', 'error');
                        }
                    });
                },
                { confirmLabel: '<?= gettext("Yes, delete") ?>', cancelLabel: '<?= gettext("No, cancel this deletion") ?>' }
            );
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../Include/Footer.php'; ?>
