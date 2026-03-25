<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    RedirectUtils::securityRedirect("Admin");
}

$sPageTitle = gettext('Property Type List');

// Get filter parameter
$filterClass = isset($_GET['class']) ? InputUtils::legacyFilterInput($_GET['class'], 'char', 1) : '';

// Build SQL query with optional class filter
$sSQL = 'SELECT prt_ID, prt_Class, prt_Name, COUNT(pro_ID) AS Properties FROM propertytype_prt LEFT JOIN property_pro ON pro_prt_ID = prt_ID';
if ($filterClass !== '') {
    $sSQL .=" WHERE prt_Class = '" . $filterClass ."'";
}
$sSQL .= ' GROUP BY prt_ID, prt_Class, prt_Name ORDER BY prt_Class, prt_Name';
$rsPropertyTypes = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-1">
                <i class="fa-solid fa-list-check text-primary"></i> <?= gettext('Property Types') ?>
            </h2>
            <p class="text-muted mb-0"><?= gettext('Manage person, family, and group property types') ?></p>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= gettext('Filter by Class') ?>">
                    <a href="PropertyTypeList.php" class="btn btn-outline-secondary <?= $filterClass === '' ? 'active' : '' ?>">
                        <i class="fa-solid fa-list me-1"></i><?= gettext('All') ?>
                    </a>
                    <a href="PropertyTypeList.php?class=p" class="btn btn-outline-secondary <?= $filterClass === 'p' ? 'active' : '' ?>">
                        <i class="fa-solid fa-person-half-dress me-1"></i><?= gettext('Person') ?>
                    </a>
                    <a href="PropertyTypeList.php?class=f" class="btn btn-outline-secondary <?= $filterClass === 'f' ? 'active' : '' ?>">
                        <i class="fa-solid fa-people-roof me-1"></i><?= gettext('Family') ?>
                    </a>
                    <a href="PropertyTypeList.php?class=g" class="btn btn-outline-secondary <?= $filterClass === 'g' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-group me-1"></i><?= gettext('Group') ?>
                    </a>
                </div>
            </div>
            <div class="card-options">
                <div class="dropdown me-2">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-tags me-1"></i><?= gettext('View Properties') ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="PropertyList.php?Type=p">
                            <i class="fa-solid fa-person-half-dress me-2"></i><?= gettext('Person Properties') ?>
                        </a>
                        <a class="dropdown-item" href="PropertyList.php?Type=f">
                            <i class="fa-solid fa-people-roof me-2"></i><?= gettext('Family Properties') ?>
                        </a>
                        <a class="dropdown-item" href="PropertyList.php?Type=g">
                            <i class="fa-solid fa-user-group me-2"></i><?= gettext('Group Properties') ?>
                        </a>
                    </div>
                </div>
                <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
                <a href="PropertyTypeEditor.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <table class="table table-hover table-vcenter card-table">
            <thead>
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('Class') ?></th>
                    <th class="text-center">
                        <?= gettext('In Use') ?>
                        <i class="fa-solid fa-circle-info text-muted ms-1" title="<?= gettext('Number of records using this property type') ?>" data-bs-toggle="tooltip"></i>
                    </th>
                    <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
                    <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $hasRows = false;
            while ($aRow = mysqli_fetch_array($rsPropertyTypes)) {
                $hasRows = true;
                $prt_ID = $aRow['prt_ID'];
                $prt_Name = $aRow['prt_Name'];
                $prt_Class = $aRow['prt_Class'];
                $Properties = $aRow['Properties'];
                ?>
                <tr>
                    <td>
                        <strong><?= InputUtils::escapeHTML($prt_Name) ?></strong>
                    </td>
                    <td>
                        <?php
                        switch ($prt_Class) {
                            case 'p':
                                echo '<span class="badge bg-blue-lt text-blue"><i class="fa-solid fa-person-half-dress me-1"></i>' . gettext('Person') . '</span>';
                                break;
                            case 'f':
                                echo '<span class="badge bg-teal-lt text-teal"><i class="fa-solid fa-people-roof me-1"></i>' . gettext('Family') . '</span>';
                                break;
                            case 'g':
                                echo '<span class="badge bg-purple-lt text-purple"><i class="fa-solid fa-user-group me-1"></i>' . gettext('Group') . '</span>';
                                break;
                        }
                        ?>
                    </td>
                    <td class="text-center">
                        <?php if ($Properties > 0): ?>
                        <span class="badge bg-green-lt text-green" title="<?= $Properties . ' ' . gettext('records') ?>">
                            <i class="fa-solid fa-check me-1"></i><?= $Properties ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-light text-dark">0</span>
                        <?php endif; ?>
                    </td>
                    <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="PropertyTypeEditor.php?PropertyTypeID=<?= $prt_ID ?>">
                                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="PropertyTypeDelete.php?PropertyTypeID=<?= $prt_ID ?><?= $Properties > 0 ? '&Warn' : '' ?>"
                                   title="<?= $Properties > 0 ? gettext('Delete (will delete all associated records)') : gettext('Delete') ?>">
                                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                </a>
                            </div>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php
            }
            if (!$hasRows): ?>
                <tr>
                    <td colspan="<?= AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled() ? '4' : '3' ?>" class="text-center text-muted py-4">
                        <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                        <?= gettext('No property types found') ?>
                        <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
                        <br>
                        <a href="PropertyTypeEditor.php" class="btn btn-primary btn-sm mt-2">
                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Your First Property Type') ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>

<?php
require_once __DIR__ . '/Include/Footer.php';
