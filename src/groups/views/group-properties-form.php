<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    var groupId = <?= (int) $iGroupID ?>;

    function reorderFormProp(propId, direction) {
        fetch(window.CRM.root + '/api/groups/' + groupId + '/formprops/' + propId + '/order', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ direction: direction })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { window.location.reload(); }
        });
    }

    $(document).on('click', '.js-reorder-field', function (e) {
        e.preventDefault();
        var btn = $(this);
        reorderFormProp(btn.data('prop-id'), btn.data('direction'));
    });

    function confirmDeleteField(fieldName, propId, fieldId) {
        var msg = <?= json_encode(gettext('Are you sure you want to delete')) ?> + '"' + window.CRM.escapeHtml(fieldName) + '"?';
        msg += '<br><br><strong>' + <?= json_encode(gettext('Warning')) ?> + ':</strong> ';
        msg += <?= json_encode(gettext('By deleting this field, you will irrevocably lose all group member data assigned for this field!')) ?>;
        bootbox.confirm({
            title: <?= json_encode(gettext('Delete Confirmation')) ?>,
            message: msg,
            buttons: {
                cancel:  { label: <?= json_encode(gettext('Cancel')) ?>, className: 'btn-secondary' },
                confirm: { label: <?= json_encode(gettext('Delete')) ?>, className: 'btn-danger' }
            },
            callback: function(result) {
                if (result) {
                    fetch(window.CRM.root + '/api/groups/' + groupId + '/formprops/' + propId, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ field: fieldId })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) { window.location.reload(); }
                    });
                }
            }
        });
        return false;
    }

    $(document).on('click', '.js-delete-field', function () {
        var btn = $(this);
        confirmDeleteField(btn.data('field-name'), btn.data('prop-id'), btn.data('field-id'));
    });

    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>

<form method="post" action="<?= $sRootPath ?>/groups/<?= $iGroupID ?>/properties/form" name="GroupPropFormEditor">
    <div class="card mb-4">
        <div class="card-status-top bg-success"></div>
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fa-solid fa-plus"></i>
                <?= gettext('Add New Field') ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label for="newFieldType" class="form-label"><?= gettext('Type') ?>:</label>
                    <select id="newFieldType" name="newFieldType" class="form-select">
                        <?php
                        for ($iOptionID = 1; $iOptionID <= count($aPropTypes); $iOptionID++) {
                            echo '<option value="' . InputUtils::escapeAttribute($iOptionID) . '">' . InputUtils::escapeHTML($aPropTypes[$iOptionID]) . '</option>';
                        }
                        ?>
                    </select>
                    <small class="form-text text-body-secondary">
                        <a href="<?= SystemURLs::getSupportURL() ?>" target="_blank"><?= gettext('Help on types..') ?></a>
                    </small>
                </div>
                <div class="col-md-4">
                    <label for="newFieldName" class="form-label"><?= gettext('Name') ?>:</label>
                    <input type="text" id="newFieldName" class="form-control" name="newFieldName" maxlength="40">
                    <?php if ($bNewNameError): ?>
                        <small class="text-danger d-block mt-1"><?= gettext('You must enter a name') ?></small>
                    <?php endif; ?>
                    <?php if ($bDuplicateNameError): ?>
                        <small class="text-danger d-block mt-1"><?= gettext('That field name already exists.') ?></small>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label for="newFieldDesc" class="form-label"><?= gettext('Description') ?>:</label>
                    <input type="text" id="newFieldDesc" class="form-control" name="newFieldDesc" maxlength="60">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100" name="AddField">
                        <i class="fa-solid fa-plus"></i>
                        <?= gettext('Add') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($numRows === 0): ?>
        <div class="alert alert-info" role="alert">
            <i class="fa-solid fa-circle-info"></i>
            <?= gettext('No properties have been added yet') ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            <i class="fa-solid fa-circle-info me-1"></i>
            <?= gettext('Name changes require saving. Reorder and delete actions in the action menu take effect immediately.') ?>
        </div>
        <?php if ($bErrorFlag): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <strong><?= gettext('Invalid fields or selections.') ?></strong>
                <?= gettext('Changes not saved! Please correct and try again!') ?>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="mb-0">
                    <i class="fa-solid fa-list me-2"></i>
                    <?= gettext('Existing Group Properties') ?>
                </h5>
                <span class="badge bg-info text-white ms-auto"><?= $numRows ?> <?= gettext('properties') ?></span>
            </div>
            <div class="card-body" style="overflow: visible;">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th><?= gettext('Type') ?></th>
                            <th><?= gettext('Name') ?></th>
                            <th><?= gettext('Description') ?></th>
                            <th><?= gettext('Special option') ?></th>
                            <th class="text-center">
                                <?= gettext('Show in Profile') ?>
                                <i class="fa-solid fa-circle-question text-body-secondary ms-1"
                                   data-bs-toggle="tooltip"
                                   title="<?= htmlspecialchars(gettext('When checked, this property will be displayed alongside the group in the person\'s group list on their profile page.'), ENT_QUOTES, 'UTF-8') ?>"
                                ></i>
                            </th>
                            <th class="no-export"><?= gettext('Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($row = 1; $row <= $numRows; $row++): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark"><?= InputUtils::escapeHTML($aPropTypes[$aTypeFields[$row]]) ?></span>
                            </td>
                            <td>
                                <input type="text" name="<?= $row ?>name" value="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>" class="form-control form-control-sm" maxlength="40">
                                <?php if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]): ?>
                                    <small class="text-danger d-block mt-1"><?= gettext('You must enter a name') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <textarea name="<?= $row ?>desc" class="form-control form-control-sm" rows="1" maxlength="60"><?= InputUtils::escapeHTML($aDescFields[$row]) ?></textarea>
                            </td>
                            <td>
                                <?php if ($aTypeFields[$row] == 9): ?>
                                    <select name="<?= $row ?>special" class="form-select form-select-sm">
                                        <option value="0" selected><?= gettext('Select a group') ?></option>
                                        <?php foreach ($aGroupList as $grp): ?>
                                            <option value="<?= InputUtils::escapeAttribute($grp['grp_ID']) ?>"<?= ($aSpecialFields[$row] == $grp['grp_ID']) ? ' selected' : '' ?>><?= InputUtils::escapeHTML($grp['grp_Name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (array_key_exists($row, $aSpecialErrors) && $aSpecialErrors[$row]): ?>
                                        <small class="text-danger d-block mt-1"><?= gettext('You must select a group.') ?></small>
                                    <?php endif; ?>
                                <?php elseif ($aTypeFields[$row] == 12): ?>
                                    <a href="<?= $sRootPath ?>/admin/system/options?mode=groupcustom&ListID=<?= InputUtils::escapeAttribute($aSpecialFields[$row]) ?>" target="_blank"><?= gettext('Edit List Options') ?></a>
                                <?php else: ?>
                                    &nbsp;
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="<?= $row ?>show" value="1"<?= $aPersonDisplayFields[$row] ? ' checked' : '' ?>>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button type="button" class="dropdown-item text-danger js-delete-field"
                                            data-field-name="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>"
                                            data-prop-id="<?= $row ?>"
                                            data-field-id="<?= InputUtils::escapeAttribute($aFieldFields[$row]) ?>">
                                            <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                        </button>
                                        <?php if ($row != 1): ?>
                                            <a href="#" class="dropdown-item js-reorder-field" data-prop-id="<?= $row ?>" data-direction="up"><i class="ti ti-arrow-up me-2"></i><?= gettext('Move up') ?></a>
                                        <?php endif; ?>
                                        <?php if ($row < $numRows): ?>
                                            <a href="#" class="dropdown-item js-reorder-field" data-prop-id="<?= $row ?>" data-direction="down"><i class="ti ti-arrow-down me-2"></i><?= gettext('Move down') ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-center gap-2 my-3">
        <a href="<?= $sRootPath ?>/groups/view/<?= $iGroupID ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            <?= gettext('Back to Group') ?>
        </a>
        <?php if ($numRows !== 0): ?>
            <button type="submit" class="btn btn-primary" name="SaveChanges">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= gettext('Save Changes') ?>
            </button>
        <?php endif; ?>
    </div>
</form>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
