<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<p class="text-muted mb-3"><?= sprintf(gettext('Manage %s options'), InputUtils::escapeHTML($noun)) ?></p>

<!-- Add New Option -->
<div class="card mb-4">
    <div class="card-status-top bg-success"></div>
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fa-solid fa-plus"></i>
            <?= gettext('Add New') . ' ' . InputUtils::escapeHTML($noun) ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label for="newOptionName" class="form-label"><?= gettext('Name') ?>:</label>
                <input class="form-control" type="text" id="newOptionName" maxlength="40">
                <div id="newOptionError" class="text-danger small mt-1 d-none"></div>
            </div>
        </div>
        <div class="text-center mt-3">
            <button type="button" class="btn btn-success" id="addOptionBtn">
                <i class="fa-solid fa-plus"></i>
                <?= gettext('Add New') . ' ' . InputUtils::escapeHTML($noun) ?>
            </button>
        </div>
    </div>
</div>

<!-- Existing Options -->
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">
            <i class="fa-solid fa-list me-2"></i>
            <?= gettext('Existing Options') ?>
        </h5>
        <span class="badge bg-primary text-white ms-auto" id="optionCount"><?= $optionRows->count() ?></span>
    </div>
    <div class="card-body" style="overflow: visible;">
        <table class="table table-hover table-sm mb-0" id="optionsTable">
            <thead>
                <tr>
                    <th style="width: 80px;"><?= gettext('Order') ?></th>
                    <th><?= gettext('Name') ?></th>
                    <?php if ($mode === 'classes'): ?>
                    <th style="width: 100px;"><?= gettext('Inactive') ?></th>
                    <?php endif; ?>
                    <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($optionRows as $idx => $option): ?>
                <tr data-option-id="<?= $option->getOptionId() ?>" data-sequence="<?= $option->getOptionSequence() ?>">
                    <td><span class="badge bg-light text-dark"><?= $option->getOptionSequence() ?></span></td>
                    <td>
                        <input class="form-control form-control-sm option-name-input" type="text"
                               value="<?= InputUtils::escapeAttribute($option->getOptionName()) ?>" maxlength="40"
                               data-original="<?= InputUtils::escapeAttribute($option->getOptionName()) ?>">
                    </td>
                    <?php if ($mode === 'classes'): ?>
                    <td>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input inactive-toggle"
                                   <?= in_array($option->getOptionId(), $inactiveClasses) ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <?php endif; ?>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button type="button" class="dropdown-item move-up-btn"><i class="ti ti-arrow-up me-2"></i><?= gettext('Move up') ?></button>
                                <button type="button" class="dropdown-item move-down-btn"><i class="ti ti-arrow-down me-2"></i><?= gettext('Move down') ?></button>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-danger delete-btn"><i class="ti ti-trash me-2"></i><?= gettext('Delete') ?></button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex mt-3 justify-content-center">
    <button type="button" class="btn btn-primary me-2" id="saveChangesBtn">
        <i class="fa-solid fa-floppy-disk"></i>
        <?= gettext('Save Changes') ?>
    </button>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function() {
    const listId = <?= (int) $listId ?>;
    const mode = <?= json_encode($mode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    // Add new option
    document.getElementById('addOptionBtn').addEventListener('click', function() {
        const nameInput = document.getElementById('newOptionName');
        const errorDiv = document.getElementById('newOptionError');
        const name = nameInput.value.trim();

        if (!name) {
            errorDiv.textContent = i18next.t('You must enter a name');
            errorDiv.classList.remove('d-none');
            return;
        }

        errorDiv.classList.add('d-none');

        window.CRM.AdminAPIRequest({
            method: 'POST',
            path: 'options/' + listId,
            data: JSON.stringify({ name: name })
        }).done(function() {
            window.location.reload();
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || i18next.t('Failed to add option');
            errorDiv.textContent = msg;
            errorDiv.classList.remove('d-none');
        });
    });

    // Save name changes
    document.getElementById('saveChangesBtn').addEventListener('click', function() {
        const rows = document.querySelectorAll('#optionsTable tbody tr');
        const promises = [];

        rows.forEach(function(row) {
            const input = row.querySelector('.option-name-input');
            const optionId = row.dataset.optionId;
            const newName = input.value.trim();
            const originalName = input.dataset.original;

            if (newName !== originalName && newName !== '') {
                promises.push(
                    window.CRM.AdminAPIRequest({
                        method: 'PATCH',
                        path: 'options/' + listId + '/' + optionId,
                        data: JSON.stringify({ name: newName })
                    })
                );
            }
        });

        if (promises.length === 0) {
            window.CRM.notify(i18next.t('No changes to save'), { type: 'info' });
            return;
        }

        $.when.apply($, promises).done(function() {
            window.CRM.notify(i18next.t('Changes saved successfully'), { type: 'success' });
            window.location.reload();
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || i18next.t('Failed to save changes');
            window.CRM.notify(msg, { type: 'error' });
        });
    });

    // Move up/down
    document.querySelectorAll('.move-up-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const optionId = row.dataset.optionId;
            window.CRM.AdminAPIRequest({
                method: 'POST',
                path: 'options/' + listId + '/' + optionId + '/reorder',
                data: JSON.stringify({ direction: 'up' })
            }).done(function() { window.location.reload(); });
        });
    });

    document.querySelectorAll('.move-down-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const optionId = row.dataset.optionId;
            window.CRM.AdminAPIRequest({
                method: 'POST',
                path: 'options/' + listId + '/' + optionId + '/reorder',
                data: JSON.stringify({ direction: 'down' })
            }).done(function() { window.location.reload(); });
        });
    });

    // Delete
    document.querySelectorAll('.delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const optionId = row.dataset.optionId;
            const name = row.querySelector('.option-name-input').value;

            bootbox.confirm({
                title: i18next.t('Delete Confirmation'),
                message: i18next.t('Are you sure you want to delete') + ' "' + window.CRM.escapeHtml(name) + '"?',
                buttons: {
                    cancel: { label: i18next.t('Cancel'), className: 'btn-secondary' },
                    confirm: { label: i18next.t('Delete'), className: 'btn-danger' }
                },
                callback: function(result) {
                    if (result) {
                        window.CRM.AdminAPIRequest({
                            method: 'DELETE',
                            path: 'options/' + listId + '/' + optionId + '?mode=' + encodeURIComponent(mode)
                        }).done(function() {
                            window.CRM.notify(i18next.t('Item deleted successfully'), { type: 'success' });
                            window.location.reload();
                        }).fail(function(xhr) {
                            const msg = xhr.responseJSON?.message || i18next.t('Failed to delete');
                            window.CRM.notify(msg, { type: 'error' });
                        });
                    }
                }
            });
        });
    });

    // Inactive toggle (classes mode only)
    document.querySelectorAll('.inactive-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            const optionId = row.dataset.optionId;
            window.CRM.AdminAPIRequest({
                method: 'POST',
                path: 'options/' + listId + '/' + optionId + '/inactive',
                data: JSON.stringify({})
            });
        });
    });
})();
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
