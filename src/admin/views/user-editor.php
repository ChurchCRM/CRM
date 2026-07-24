<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Derive the access mode for the UI
$accessMode = $perms['admin'] ? 'admin' : ($perms['editSelf'] ? 'self' : 'custom');

?>

<form method="post" action="<?= InputUtils::escapeAttribute($formAction) ?>">
<?= CSRFUtils::getTokenInputField('user_editor') ?>

<?php if (!empty($sErrorText)): ?>
<div class="alert alert-danger alert-dismissible" role="alert">
    <i class="ti ti-alert-circle me-2"></i><?= InputUtils::escapeHTML($sErrorText) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$bEmailEnabled): ?>
<div class="alert alert-warning d-flex align-items-center" role="alert">
    <i class="fa-solid fa-triangle-exclamation me-2 fs-3"></i>
    <div class="flex-grow-1">
        <strong><?= gettext('Email is disabled') ?></strong>
        <div class="text-secondary"><?= gettext('New users will not receive a welcome email with their credentials. Share the password with them manually, or configure email first.') ?></div>
    </div>
    <a href="<?= SystemURLs::getRootPath() ?>/v2/email/dashboard?settings=open" class="btn btn-warning ms-3">
        <i class="fa-solid fa-envelope me-1"></i><?= gettext('Set up Email') ?>
    </a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Account') ?></h3>
    </div>
    <div class="card-body">
        <?php if ($showPersonSelect): ?>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label"><?= gettext('Person') ?></label>
            <div class="col-sm-9">
                <select name="PersonID" id="personSelect" class="form-select">
                    <option value="" disabled selected><?= gettext('— Select a person —') ?></option>
                    <?php foreach ($people as $p): ?>
                    <option value="<?= $p->getId() ?>"><?= InputUtils::escapeHTML($p->getLastName() . ', ' . $p->getFirstName()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="PersonID" value="<?= (int) $editorPersonId ?>">
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label"><?= gettext('User') ?></label>
            <div class="col-sm-9">
                <div class="form-control-plaintext"><?= InputUtils::escapeHTML($sUser) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mb-3">
            <label class="col-sm-3 col-form-label" for="UserName"><?= gettext('Login Name') ?></label>
            <div class="col-sm-9">
                <input type="text" name="UserName" id="UserName" value="<?= InputUtils::escapeAttribute($sUserName) ?>" class="form-control">
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Permissions') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="ti ti-info-circle me-2"></i><?= gettext('Changes will not take effect until next logon.') ?>
        </div>

        <?php
        $accessModes = [
            ['value' => 'admin', 'icon' => 'ti-shield-check', 'label' => gettext('Administrator'), 'desc' => gettext('Full access — grants all privileges.')],
            ['value' => 'self',  'icon' => 'ti-user-check',   'label' => gettext('Self-service only'), 'desc' => gettext('Can only review and verify their own family. No other access.')],
            ['value' => 'custom','icon' => 'ti-adjustments',  'label' => gettext('Custom'), 'desc' => gettext('Choose specific permissions below.')],
        ];
        ?>
        <div class="mb-3">
            <label class="form-label"><?= gettext('Access level') ?></label>
            <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column flex-md-row gap-2" id="accessModeGroup">
                <?php foreach ($accessModes as $mode): ?>
                <label class="form-selectgroup-item flex-fill">
                    <input type="radio" name="accessMode" value="<?= $mode['value'] ?>" class="form-selectgroup-input"<?= $accessMode === $mode['value'] ? ' checked' : '' ?>>
                    <span class="form-selectgroup-label d-block text-start p-3">
                        <span class="d-flex align-items-center mb-1">
                            <i class="ti <?= $mode['icon'] ?> me-2 text-primary fs-3"></i>
                            <span class="fw-bold"><?= $mode['label'] ?></span>
                        </span>
                        <span class="d-block text-body-secondary small"><?= $mode['desc'] ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Hidden Admin/EditSelf flags driven by the access-level radio selector -->
        <input type="checkbox" class="d-none" name="Admin" id="Admin" value="1"<?= $perms['admin'] ? ' checked' : '' ?>>
        <input type="checkbox" class="d-none" name="EditSelf" id="EditSelf" value="1"<?= $perms['editSelf'] ? ' checked' : '' ?>>

        <!-- People & Families panel: shown only in Custom mode -->
        <div id="pfPanel" class="border rounded mb-3"<?= $accessMode === 'custom' ? '' : ' style="display:none;"' ?>>
            <div class="px-3 py-2 border-bottom bg-light">
                <strong><i class="ti ti-users me-2"></i><?= gettext('People &amp; Families') ?></strong>
                <p class="text-body-secondary small mb-0 mt-1"><?= gettext('All users can view congregation members. This permission cannot be removed.') ?></p>
            </div>
            <div class="row align-items-center px-3 py-2">
                <label class="col-sm-5 col-form-label text-body-secondary"><?= gettext('View') ?></label>
                <div class="col-sm-7 d-flex align-items-center gap-2">
                    <span class="badge bg-success-lt text-success"><i class="ti ti-eye me-1"></i><?= gettext('View') ?></span>
                    <span class="badge bg-secondary-lt text-secondary"><i class="ti ti-lock me-1"></i><?= gettext('Always granted') ?></span>
                </div>
            </div>
            <div class="row align-items-center border-top px-3 py-2 permission-row">
                <label class="col-sm-5 col-form-label" for="AddRecords"><?= gettext('Add') ?></label>
                <div class="col-sm-7">
                    <label class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="AddRecords" id="AddRecords" value="1"<?= $perms['addRecords'] ? ' checked' : '' ?>>
                    </label>
                </div>
            </div>
            <div class="row align-items-center border-top px-3 py-2 permission-row">
                <label class="col-sm-5 col-form-label" for="EditRecords"><?= gettext('Edit') ?></label>
                <div class="col-sm-7">
                    <label class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="EditRecords" id="EditRecords" value="1"<?= $perms['editRecords'] ? ' checked' : '' ?>>
                    </label>
                </div>
            </div>
            <div class="row align-items-center border-top px-3 py-2 permission-row">
                <label class="col-sm-5 col-form-label" for="DeleteRecords"><?= gettext('Delete') ?></label>
                <div class="col-sm-7">
                    <label class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="DeleteRecords" id="DeleteRecords" value="1"<?= $perms['deleteRecords'] ? ' checked' : '' ?>>
                    </label>
                </div>
            </div>
            <div class="row align-items-center border-top px-3 py-2 permission-row">
                <label class="col-sm-5 col-form-label" for="Notes"><?= gettext('Notes') ?></label>
                <div class="col-sm-7">
                    <label class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="Notes" id="Notes" value="1"<?= $perms['notes'] ? ' checked' : '' ?>>
                    </label>
                </div>
            </div>
        </div>

        <div id="customPermissions"<?= $accessMode === 'custom' ? '' : ' style="display:none;"' ?>>
            <hr>
            <p class="text-body-secondary small mb-3"><?= gettext('Grant individual permissions') ?>:</p>
            <?php
            $permissions = [
                ['name' => 'MenuOptions',  'label' => gettext('Manage Properties and Classifications'), 'checked' => $perms['menuOptions']],
                ['name' => 'ManageGroups', 'label' => gettext('Manage Groups and Roles'),               'checked' => $perms['manageGroups']],
                ['name' => 'Finance',            'label' => gettext('Manage Donations and Finance'),  'checked' => $perms['finance']],
                ['name' => 'ManageFundraisers', 'label' => gettext('Manage Fundraisers'),              'checked' => $perms['manageFundraisers']],
            ];
            foreach ($permissions as $perm):
            ?>
            <div class="row mb-2 permission-row">
                <label class="col-sm-5 col-form-label" for="<?= $perm['name'] ?>"><?= $perm['label'] ?></label>
                <div class="col-sm-7">
                    <label class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="<?= $perm['name'] ?>" id="<?= $perm['name'] ?>" value="1"<?= $perm['checked'] ? ' checked' : '' ?>>
                    </label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="btn btn-secondary me-2"><?= gettext('Cancel') ?></a>
        <button type="submit" class="btn btn-primary" id="SaveButton" name="save"><?= gettext('Save') ?></button>
    </div>
</div>

<?php if (!$isNew && !empty($configRows)): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('User Config') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <i class="ti ti-info-circle me-2"></i><?= gettext('Set Permission to True to allow this user to change the setting themselves.') ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th><?= gettext('Permission') ?></th>
                    <th><?= gettext('Variable name') ?></th>
                    <th><?= gettext('Current Value') ?></th>
                    <th><?= gettext('Notes') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($configRows as $row): ?>
                <tr>
                    <td>
                        <select class="form-select form-select-sm" name="new_permission[<?= (int) $row['id'] ?>]">
                            <option value="FALSE"<?= $row['permission'] !== 'TRUE' ? ' selected' : '' ?>><?= gettext('False') ?></option>
                            <option value="TRUE"<?= $row['permission'] === 'TRUE' ? ' selected' : '' ?>><?= gettext('True') ?></option>
                        </select>
                    </td>
                    <td><?= InputUtils::escapeHTML($row['name']) ?></td>
                    <td>
                        <?php if ($row['type'] === 'text'): ?>
                        <input type="text" class="form-control form-control-sm" maxlength="255" name="new_value[<?= (int) $row['id'] ?>]" value="<?= InputUtils::escapeAttribute($row['value']) ?>">
                        <?php elseif ($row['type'] === 'textarea'): ?>
                        <textarea class="form-control form-control-sm" rows="3" name="new_value[<?= (int) $row['id'] ?>]"><?= InputUtils::escapeHTML($row['value']) ?></textarea>
                        <?php elseif ($row['type'] === 'number' || $row['type'] === 'date'): ?>
                        <input type="text" class="form-control form-control-sm" maxlength="15" name="new_value[<?= (int) $row['id'] ?>]" value="<?= InputUtils::escapeAttribute($row['value']) ?>">
                        <?php elseif ($row['type'] === 'boolean'): ?>
                        <select class="form-select form-select-sm" name="new_value[<?= (int) $row['id'] ?>]">
                            <option value=""<?= !$row['value'] ? ' selected' : '' ?>><?= gettext('False') ?></option>
                            <option value="1"<?= $row['value'] ? ' selected' : '' ?>><?= gettext('True') ?></option>
                        </select>
                        <?php endif; ?>
                        <input type="hidden" name="type[<?= (int) $row['id'] ?>]" value="<?= InputUtils::escapeAttribute($row['type']) ?>">
                    </td>
                    <td class="text-body-secondary"><?= InputUtils::escapeHTML(gettext($row['tooltip'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="btn btn-secondary me-2"><?= gettext('Cancel') ?></a>
        <button type="submit" class="btn btn-primary" name="save"><?= gettext('Save') ?></button>
    </div>
</div>
<?php endif; ?>

</form>

<script src="<?= SystemURLs::assetVersioned('/skin/js/user-editor.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
