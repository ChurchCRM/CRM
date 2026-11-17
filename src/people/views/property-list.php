<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

$breadcrumbParent = match ($sType) {
    'g' => [gettext('Groups'), '/groups/dashboard'],
    default => [gettext('People'), '/people/dashboard'],
};
$aBreadcrumbs = PageHeader::breadcrumbs(array_filter([
    $breadcrumbParent,
    [$sTypeName . ' ' . gettext('Properties')],
]));
require_once __DIR__ . '/../../Include/Header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="btn-group btn-group-sm" role="group">
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
                    <th class="w-1"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($groups)): ?>
                <tr><td colspan="4" class="text-center py-5 text-body-secondary"><?= gettext('No properties defined yet') ?></td></tr>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <tr class="table-active"><td colspan="4"><strong><?= InputUtils::escapeHTML($group['name']) ?></strong></td></tr>
                    <?php foreach ($group['properties'] as $prop): ?>
                    <tr>
                        <td><?= InputUtils::escapeHTML($prop['pro_Name']) ?></td>
                        <td><?= InputUtils::escapeHTML($prop['pro_Description']) ?></td>
                        <td><?= InputUtils::escapeHTML($prop['pro_Prompt']) ?></td>
                        <td>
                            <a href="<?= $sRootPath ?>/admin/properties/<?= $sRecordType ?>/<?= (int) $prop['pro_ID'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../Include/Footer.php'; ?>
