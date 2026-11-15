<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

/**
 * Envelope Manager view.
 *
 * Variables injected by the envelopes.php route:
 *
 * @var string $sRootPath
 * @var array<int, string> $classifications option name keyed by classification id
 * @var int    $classificationId 0 for every family
 * @var string $sortBy 'name' or 'envelope'
 * @var string $assignStartNumber
 * @var array  $rows familyId, label, envelope, invalid, duplicate — in display order
 * @var string $errorMessage
 * @var bool   $isUnsaved the envelope fields hold numbers that are not stored yet
 */

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= $sRootPath ?>/finance/envelopes" id="envelope-filter" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="classification"><?= gettext('Families with at least one member in') ?></label>
                <select class="form-select" id="classification" name="classification">
                    <option value="0"><?= gettext('All') ?></option>
                    <?php foreach ($classifications as $optionId => $optionName): ?>
                    <option value="<?= $optionId ?>" <?= $classificationId === $optionId ? 'selected' : '' ?>><?= InputUtils::escapeHTML($optionName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <div class="form-label"><?= gettext('Sort by') ?></div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="sortBy" value="name" id="sortByName" <?= $sortBy === 'name' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sortByName"><?= gettext('Last Name') ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="sortBy" value="envelope" id="sortByEnvelope" <?= $sortBy === 'envelope' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sortByEnvelope"><?= gettext('Envelope Number') ?></label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="fa-solid fa-filter me-1"></i><?= gettext('Apply') ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($errorMessage !== ''): ?>
<div class="alert alert-danger" role="alert" id="envelope-error">
    <i class="fa-solid fa-triangle-exclamation me-1"></i><?= InputUtils::escapeHTML($errorMessage) ?>
</div>
<?php elseif ($isUnsaved): ?>
<div class="alert alert-info" role="status" id="envelope-unsaved">
    <i class="fa-solid fa-circle-info me-1"></i><?= gettext('These envelope numbers are not saved until you select Update Family Records.') ?>
</div>
<?php endif; ?>

<form method="post" action="<?= $sRootPath ?>/finance/envelopes" id="envelope-form">
    <?= CSRFUtils::getTokenInputField('envelope_manager') ?>
    <input type="hidden" name="classification" value="<?= $classificationId ?>">
    <input type="hidden" name="sortBy" value="<?= InputUtils::escapeAttribute($sortBy) ?>">
    <?php // Pressing Enter in a field must neither save nor renumber; a disabled first submit button blocks implicit submission. ?>
    <button type="submit" class="d-none" disabled aria-hidden="true" tabindex="-1"></button>

    <div class="card">
        <div class="card-header flex-wrap gap-2">
            <h3 class="card-title">
                <i class="fa-solid fa-envelope me-2"></i><?= gettext('Families') ?>
                <span class="badge bg-secondary-lt text-secondary ms-1"><?= count($rows) ?></span>
            </h3>
            <div class="card-actions btn-list">
                <button type="submit" class="btn btn-outline-secondary" name="action" value="zero" id="envelope-zero"><?= gettext('Zero') ?></button>
                <div class="input-group w-auto">
                    <button type="submit" class="btn btn-outline-secondary" name="action" value="assign" id="envelope-assign"><?= gettext('Assign starting at #') ?></button>
                    <input type="text" class="form-control" name="assignStartNumber" id="assignStartNumber" value="<?= InputUtils::escapeAttribute($assignStartNumber) ?>"
                           inputmode="numeric" maxlength="7" size="7" aria-label="<?= InputUtils::escapeAttribute(gettext('Starting envelope number')) ?>">
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateEnvelopesModal" <?= $rows === [] ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Update Family Records') ?>
                </button>
            </div>
        </div>
        <?php if ($rows === []): ?>
        <div class="card-body">
            <div class="text-secondary"><?= gettext('No families found.') ?></div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="envelope-table">
                <thead>
                    <tr>
                        <th><?= gettext('Family') ?></th>
                        <th class="w-25"><?= gettext('Envelope') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= InputUtils::escapeHTML($row['label']) ?></td>
                        <td class="<?= $row['duplicate'] ? 'bg-danger-lt' : '' ?>" <?= $row['duplicate'] ? 'title="' . InputUtils::escapeAttribute(gettext('Duplicate envelope number')) . '"' : '' ?>>
                            <input type="text"
                                   class="form-control form-control-sm <?= $row['invalid'] ? 'is-invalid' : '' ?>"
                                   name="envelopes[<?= (int) $row['familyId'] ?>]"
                                   value="<?= InputUtils::escapeAttribute($row['envelope']) ?>"
                                   inputmode="numeric"
                                   maxlength="7"
                                   aria-label="<?= InputUtils::escapeAttribute($row['label']) ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="updateEnvelopesModal" tabindex="-1" aria-labelledby="updateEnvelopesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateEnvelopesModalLabel"><?= gettext('Update Envelopes') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <span class="text-danger"><?= gettext('This will overwrite the family envelope numbers in the database with those selected on this page.  Continue?') ?></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                    <button type="submit" class="btn btn-primary" name="action" value="save" id="envelope-save"><?= gettext('Confirm') ?></button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
