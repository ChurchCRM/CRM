<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Available properties for the "Assign" dropdown (only admin)
$availableProperties = [];
if ($bCanManageGroups) {
    foreach ($allGroupPropertyDefs as $propDefObj) {
        if (!in_array($propDefObj->getProId(), $rsAssignedPropertyIds, true)) {
            $availableProperties[] = [
                'pro_ID'          => $propDefObj->getProId(),
                'pro_Name'        => $propDefObj->getProName(),
                'pro_Prompt'      => (string) $propDefObj->getProPrompt(),
                'pro_Description' => (string) $propDefObj->getProDescription(),
            ];
        }
    }
}
?>

<!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-layer-group icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= InputUtils::escapeHTML($sGroupType) ?></div>
                        <div class="text-muted"><?= gettext('Group Type') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-tag icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $defaultRole !== null ? InputUtils::escapeHTML($defaultRole->getOptionName()) : gettext('None') ?></div>
                        <div class="text-muted"><?= gettext('Default Role') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><span id="iTotalMembers">0</span></div>
                        <div class="text-muted"><?= gettext('Members') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <?php if ($thisGroup->isActive()): ?>
                        <span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-circle-check icon"></i></span>
                        <?php else: ?>
                        <span class="bg-danger text-white avatar rounded-circle"><i class="fa-solid fa-circle-xmark icon"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <div class="fw-medium">
                            <?php if ($thisGroup->isActive()): ?>
                            <span class="badge bg-success-lt text-success"><?= gettext('Active') ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger-lt text-danger"><?= gettext('Inactive') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted"><?= gettext('Status') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- LEFT COLUMN: Actions, Members -->
    <div class="col-lg-8">
        <!-- Action Toolbar (ghost buttons, family-view pattern) -->
        <div class="d-flex align-items-center mb-3 gap-2 flex-wrap d-print-none">
            <?php if ($bCanManageGroups): ?>
            <a class="btn btn-ghost-primary" href="<?= $sRootPath ?>/GroupEditor.php?GroupID=<?= $iGroupID ?>">
                <i class="fa-solid fa-pen me-1"></i><?= gettext('Edit') ?>
            </a>
            <?php endif; ?>
            <button class="btn btn-ghost-secondary" id="printGroup" title="<?= gettext('Print') ?>">
                <i class="fa-solid fa-print me-1"></i><?= gettext('Print') ?>
            </button>
            <div class="dropdown">
                <button class="btn btn-ghost-success dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                    <i class="fa-solid fa-cart-plus me-1"></i><?= gettext('Cart') ?>
                </button>
                <div class="dropdown-menu" id="addToCartMenu">
                    <a class="dropdown-item" id="addAllToCart" href="#">
                        <i class="fa-solid fa-users me-2"></i><?= gettext('All Members') ?>
                    </a>
                    <div class="dropdown-divider" id="cartRoleDivider"></div>
                    <!-- Role-specific cart items injected by JS -->
                </div>
            </div>
            <a class="btn btn-ghost-info" href="<?= $sRootPath ?>/v2/map?groupId=<?= $iGroupID ?>">
                <i class="fa-solid fa-map-location-dot me-1"></i><?= gettext('Map') ?>
            </a>
            <?php if ($thisGroup->isSundaySchool()): ?>
            <a class="btn btn-ghost-warning" href="<?= $sRootPath ?>/groups/sundayschool/class/<?= $iGroupID ?>">
                <i class="fa-solid fa-chalkboard-user me-1"></i><?= gettext('Sunday School') ?>
            </a>
            <?php endif; ?>
            <?php if (!empty($sEmailLink) && $bEmailEnabled): ?>
            <div class="dropdown">
                <button class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                    <i class="fa-solid fa-paper-plane me-1"></i><?= gettext('Email') ?>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="mailto:<?= InputUtils::escapeAttribute($sEmailLink) ?>"><i class="fa-solid fa-users me-2"></i><?= gettext('All Members') ?></a>
                    <?php foreach ($roleEmails as $roleName => $encodedEmails): ?>
                    <a class="dropdown-item" href="mailto:<?= InputUtils::escapeAttribute($encodedEmails) ?>"><i class="fa-solid fa-user me-2"></i><?= InputUtils::escapeHTML($roleName) ?></a>
                    <?php endforeach; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="mailto:?bcc=<?= InputUtils::escapeAttribute($sEmailLink) ?>"><i class="fa-solid fa-user-secret me-2"></i><?= gettext('BCC All') ?></a>
                    <?php foreach ($roleEmails as $roleName => $encodedEmails): ?>
                    <a class="dropdown-item" href="mailto:?bcc=<?= InputUtils::escapeAttribute($encodedEmails) ?>"><i class="fa-solid fa-user-secret me-2"></i><?= gettext('BCC') ?>: <?= InputUtils::escapeHTML($roleName) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($sPhoneLink) && $bEmailEnabled): ?>
            <button class="btn btn-ghost-secondary" id="textGroupBtn" data-phones="<?= InputUtils::escapeAttribute($sPhoneLink) ?>">
                <i class="fa-solid fa-mobile-screen me-1"></i><?= gettext('Text') ?>
            </button>
            <?php endif; ?>
            <?php if ($bCanManageGroups): ?>
            <div class="dropdown ms-auto">
                <button class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                    <i class="fa-solid fa-ellipsis-vertical me-1"></i><?= gettext('Actions') ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($thisGroup->getHasSpecialProps()): ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/GroupPropsFormEditor.php?GroupID=<?= $iGroupID ?>">
                        <i class="fa-solid fa-rectangle-list me-2"></i><?= gettext('Edit Member Properties Form') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <h6 class="dropdown-header"><?= gettext('Copy to Group') ?></h6>
                    <a class="dropdown-item copy-role-to-group" data-role-id="" href="#"><i class="fa-solid fa-users me-2"></i><?= gettext('All Members') ?></a>
                    <div id="copyRoleItems"></div>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header"><?= gettext('Move to Group') ?></h6>
                    <a class="dropdown-item move-role-to-group" data-role-id="" href="#"><i class="fa-solid fa-users me-2"></i><?= gettext('All Members') ?></a>
                    <div id="moveRoleItems"></div>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header"><?= gettext('Settings') ?></h6>
                    <a class="dropdown-item" id="toggleGroupActive" href="#">
                        <i class="fa-solid fa-power-off me-2"></i><?= $thisGroup->isActive() ? gettext('Set Inactive') : gettext('Set Active') ?>
                    </a>
                    <a class="dropdown-item" id="toggleGroupEmailExport" href="#">
                        <i class="fa-solid fa-envelope me-2"></i><?= $thisGroup->isIncludeInEmailExport() ? gettext('Exclude from Email Export') : gettext('Include in Email Export') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item text-danger" id="deleteGroupButton">
                        <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete Group') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Group Members Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-users me-1"></i> <?= gettext('Members') ?></h3>
                <span class="badge bg-primary-lt text-primary ms-2" id="memberCountBadge">0</span>
            </div>
            <div class="card-body">
                <!-- Role pill filters (built dynamically by JS) -->
                <ul class="nav nav-pills mb-3" id="role-pills"></ul>

                <!-- Add member -->
                <?php if ($bCanManageGroups): ?>
                <div class="row mb-3">
                    <label for="addGroupMember" class="col-auto col-form-label"><?= gettext('Add Member') ?>:</label>
                    <div class="col-md-5">
                        <select id="addGroupMember" class="form-select personSearch" name="addGroupMember"></select>
                    </div>
                </div>
                <?php endif; ?>

                <!-- DataTable -->
                <div style="overflow: visible;">
                    <table class="table table-hover table-vcenter table-sm" id="membersTable"></table>
                </div>
            </div>
            </div>
    </div>

    <!-- RIGHT COLUMN: Info, Properties -->
    <div class="col-lg-4">

        <!-- Group Info Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-circle-info me-2"></i><?= gettext('About') ?></h3>
            </div>
            <div class="card-body">
                <?php if ($thisGroup->getDescription()): ?>
                <p class="text-muted mb-0"><?= InputUtils::escapeHTML($thisGroup->getDescription()) ?></p>
                <?php else: ?>
                <p class="text-muted mb-0"><em><?= gettext('No description set.') ?></em></p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-3">
                    <div>
                        <i class="fa-solid fa-envelope me-1 text-muted"></i>
                        <span class="text-muted"><?= gettext('Email Export') ?>:</span>
                        <?php if ($thisGroup->isIncludeInEmailExport()): ?>
                        <span class="badge bg-success-lt text-success"><?= gettext('Included') ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary-lt text-secondary"><?= gettext('Excluded') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Properties Card -->
        <div class="card mb-3" id="group-properties-card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-tags me-1"></i> <?= gettext('Properties') ?></h3>
                <span class="badge bg-primary-lt text-primary ms-2"><?= count($rsAssignedRows) ?></span>
            </div>
            <?php if (empty($rsAssignedRows)): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                <?= gettext('No properties assigned.') ?>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($rsAssignedRows as $aRow): ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-start">
                        <div class="me-auto">
                            <div class="fw-bold"><?= InputUtils::escapeHTML($aRow['pro_Name']) ?></div>
                            <span class="badge bg-secondary-lt text-secondary me-1"><?= InputUtils::escapeHTML($aRow['prt_Name']) ?></span>
                            <?php if (!empty($aRow['r2p_Value'])): ?>
                            <span class="text-muted"><?= InputUtils::escapeHTML($aRow['r2p_Value']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($aRow['pro_Prompt'])): ?>
                            <div class="text-muted small mt-1">
                                <i class="fa-solid fa-circle-question me-1"></i><?= InputUtils::escapeHTML($aRow['pro_Prompt']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($bCanManageGroups): ?>
                        <div class="d-flex gap-1">
                            <?php if (strlen($aRow['pro_Prompt']) > 0): ?>
                            <button class="btn btn-sm btn-ghost-secondary edit-group-property-btn"
                                data-pro-id="<?= (int) $aRow['pro_ID'] ?>"
                                data-pro-prompt="<?= InputUtils::escapeAttribute($aRow['pro_Prompt']) ?>"
                                data-pro-value="<?= InputUtils::escapeAttribute($aRow['r2p_Value']) ?>"
                                title="<?= gettext('Edit Value') ?>">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-ghost-danger remove-group-property-btn"
                                data-pro-id="<?= (int) $aRow['pro_ID'] ?>"
                                data-pro-name="<?= InputUtils::escapeAttribute($aRow['pro_Name']) ?>"
                                title="<?= gettext('Remove') ?>">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($bCanManageGroups && !empty($availableProperties)): ?>
            <div class="card-footer">
                <div class="d-flex gap-2 align-items-center">
                    <select id="group-property-select" class="form-select form-select-sm">
                        <?php foreach ($availableProperties as $prop): ?>
                        <option value="<?= (int) $prop['pro_ID'] ?>"
                            data-prompt="<?= InputUtils::escapeAttribute($prop['pro_Prompt'] ?? '') ?>"
                            data-description="<?= InputUtils::escapeAttribute($prop['pro_Description'] ?? '') ?>">
                            <?= InputUtils::escapeHTML($prop['pro_Name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button id="assign-group-property-btn" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-plus me-1"></i><?= gettext('Assign') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Group-Specific Properties Card (groupprop_master) -->
        <?php if ($thisGroup->getHasSpecialProps()): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-sliders me-1"></i> <?= gettext('Member Properties') ?></h3>
                <span class="badge bg-info-lt text-info ms-2"><?= $groupSpecificProps->count() ?></span>
                <?php if ($bCanManageGroups): ?>
                <a href="<?= $sRootPath ?>/GroupPropsFormEditor.php?GroupID=<?= $iGroupID ?>" class="btn btn-sm btn-ghost-secondary ms-auto" title="<?= gettext('Edit Form') ?>">
                    <i class="fa-solid fa-pen"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php if ($groupSpecificProps->count() === 0): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                <?= gettext('No member properties defined.') ?>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($groupSpecificProps as $prop): ?>
                <div class="list-group-item">
                    <div class="fw-bold"><?= InputUtils::escapeHTML($prop->getName()) ?></div>
                    <span class="badge bg-secondary-lt text-secondary me-1"><?= InputUtils::escapeHTML($aPropTypes[$prop->getTypeId()] ?? '') ?></span>
                    <?php if ($prop->getDescription()): ?>
                    <span class="text-muted small"><?= InputUtils::escapeHTML($prop->getDescription()) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentGroup      = <?= (int) $iGroupID ?>;
    window.CRM.currentGroupName  = <?= json_encode($thisGroup->getName(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.CRM.groupIsActive     = <?= $thisGroup->isActive() ? 'true' : 'false' ?>;
    window.CRM.groupEmailExport  = <?= $thisGroup->isIncludeInEmailExport() ? 'true' : 'false' ?>;
    window.CRM.groupPhoneNumbers = <?= json_encode($sPhoneLink, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $sRootPath ?>/skin/js/GroupView.js?v=<?= filemtime(SystemURLs::getDocumentRoot() . '/skin/js/GroupView.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
