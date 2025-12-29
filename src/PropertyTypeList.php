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
    $sSQL .= " WHERE prt_Class = '" . $filterClass . "'";
}
$sSQL .= ' GROUP BY prt_ID, prt_Class, prt_Name ORDER BY prt_Class, prt_Name';
$rsPropertyTypes = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fa-solid fa-list-check"></i>
                <?= gettext('Property Types') ?>
            </h5>
            <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
            <a href="PropertyTypeEditor.php" class="btn btn-light btn-sm">
                <i class="fa-solid fa-plus"></i>
                <?= gettext('Add New') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Buttons and Property List Links -->
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= gettext('Filter by Class') ?>">
                <a href="PropertyTypeList.php" class="btn btn-outline-secondary <?= $filterClass === '' ? 'active' : '' ?>">
                    <i class="fa-solid fa-list"></i>
                    <?= gettext('All') ?>
                </a>
                <a href="PropertyTypeList.php?class=p" class="btn btn-outline-info <?= $filterClass === 'p' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user"></i>
                    <?= gettext('Person') ?>
                </a>
                <a href="PropertyTypeList.php?class=f" class="btn btn-outline-success <?= $filterClass === 'f' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i>
                    <?= gettext('Family') ?>
                </a>
                <a href="PropertyTypeList.php?class=g" class="btn btn-outline-primary <?= $filterClass === 'g' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-group"></i>
                    <?= gettext('Group') ?>
                </a>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= gettext('View Properties') ?>">
                <a href="PropertyList.php?Type=p" class="btn btn-outline-info">
                    <i class="fa-solid fa-user"></i>
                    <?= gettext('Person Properties') ?>
                </a>
                <a href="PropertyList.php?Type=f" class="btn btn-outline-success">
                    <i class="fa-solid fa-users"></i>
                    <?= gettext('Family Properties') ?>
                </a>
                <a href="PropertyList.php?Type=g" class="btn btn-outline-primary">
                    <i class="fa-solid fa-user-group"></i>
                    <?= gettext('Group Properties') ?>
                </a>
            </div>
        </div>

        <!-- Property Types Table -->
        <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th><?= gettext('Name') ?></th>
                            <th><?= gettext('Class') ?></th>
                            <th class="text-center">
                                <?= gettext('In Use') ?>
                                <i class="fa-solid fa-circle-info text-muted ml-1" title="<?= gettext('Number of records using this property type') ?>" data-toggle="tooltip"></i>
                            </th>
                            <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
                            <th class="text-right"><?= gettext('Actions') ?></th>
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
                                        echo '<span class="badge badge-info"><i class="fa-solid fa-user"></i> ' . gettext('Person') . '</span>';
                                        break;
                                    case 'f':
                                        echo '<span class="badge badge-success"><i class="fa-solid fa-users"></i> ' . gettext('Family') . '</span>';
                                        break;
                                    case 'g':
                                        echo '<span class="badge badge-primary"><i class="fa-solid fa-user-group"></i> ' . gettext('Group') . '</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php if ($Properties > 0): ?>
                                <span class="badge badge-success" title="<?= $Properties . ' ' . gettext('records') ?>">
                                    <i class="fa-solid fa-check"></i> <?= $Properties ?>
                                </span>
                                <?php else: ?>
                                <span class="badge badge-secondary">
                                    <i class="fa-solid fa-minus"></i> 0
                                </span>
                                <?php endif; ?>
                            </td>
                            <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()): ?>
                            <td class="text-right">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="PropertyTypeEditor.php?PropertyTypeID=<?= $prt_ID ?>" class="btn btn-info" title="<?= gettext('Edit') ?>">
                                        <i class="fa-solid fa-edit"></i>
                                        <?= gettext('Edit') ?>
                                    </a>
                                    <a href="PropertyTypeDelete.php?PropertyTypeID=<?= $prt_ID ?><?= $Properties > 0 ? '&Warn' : '' ?>" 
                                       class="btn btn-danger" 
                                       title="<?= $Properties > 0 ? gettext('Delete (will delete all associated records)') : gettext('Delete') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                        <?= gettext('Delete') ?>
                                    </a>
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
                                    <i class="fa-solid fa-plus"></i>
                                    <?= gettext('Add Your First Property Type') ?>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<?php
require_once __DIR__ . '/Include/Footer.php';
