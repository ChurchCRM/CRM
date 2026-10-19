<?php

/**
 * Signup Sheets — create / edit a sheet.
 *
 * @var array<string, mixed>|null $sheet
 * @var iterable                  $events
 * @var bool                      $allowPublic
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$isNew = $sheet === null;
$sheetId = $isNew ? null : (int) $sheet['shs_ID'];

/** Format a stored DATETIME for a datetime-local input. */
$asLocalInput = static function (?string $value): string {
    if (empty($value)) {
        return '';
    }
    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
};
?>

<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><i class="fa-solid fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets"><?= gettext('Signup Sheets') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= $isNew ? gettext('New Sheet') : InputUtils::escapeHTML($sheet['shs_title']) ?>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div id="sheet-editor-alert" class="alert alert-danger d-none" role="alert"></div>

<form id="sheet-editor-form" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Sheet Details') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="sheet-title"><?= gettext('Title') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sheet-title" name="title" maxlength="255" required
                               value="<?= $isNew ? '' : InputUtils::escapeAttribute($sheet['shs_title']) ?>"
                               placeholder="<?= InputUtils::escapeAttribute(gettext('Easter Potluck Breakfast')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sheet-description"><?= gettext('Description') ?></label>
                        <textarea class="form-control" id="sheet-description" name="description" rows="3"
                                  placeholder="<?= InputUtils::escapeAttribute(gettext('What volunteers should know before signing up.')) ?>"><?= $isNew ? '' : InputUtils::escapeHTML($sheet['shs_description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="sheet-event"><?= gettext('Attach to a church event') ?></label>
                            <select class="form-select" id="sheet-event" name="eventId">
                                <option value=""><?= gettext('Not attached to an event') ?></option>
                                <?php foreach ($events as $event) : ?>
                                    <option value="<?= (int) $event->getId() ?>"
                                        <?= (!$isNew && (int) ($sheet['shs_event_id'] ?? 0) === (int) $event->getId()) ? 'selected' : '' ?>>
                                        <?= InputUtils::escapeHTML($event->getTitle()) ?>
                                        (<?= $event->getStart() ? $event->getStart()->format('M j, Y') : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="sheet-location"><?= gettext('Location') ?></label>
                            <input type="text" class="form-control" id="sheet-location" name="location" maxlength="255"
                                   value="<?= $isNew ? '' : InputUtils::escapeAttribute($sheet['shs_location'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="sheet-starts"><?= gettext('Starts') ?></label>
                            <input type="datetime-local" class="form-control" id="sheet-starts" name="starts"
                                   value="<?= $isNew ? '' : InputUtils::escapeAttribute($asLocalInput($sheet['shs_starts'] ?? null)) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="sheet-ends"><?= gettext('Ends') ?></label>
                            <input type="datetime-local" class="form-control" id="sheet-ends" name="ends"
                                   value="<?= $isNew ? '' : InputUtils::escapeAttribute($asLocalInput($sheet['shs_ends'] ?? null)) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Availability') ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="sheet-status"><?= gettext('Status') ?></label>
                        <select class="form-select" id="sheet-status" name="status">
                            <?php $currentStatus = $isNew ? 'draft' : (string) $sheet['shs_status']; ?>
                            <option value="draft" <?= $currentStatus === 'draft' ? 'selected' : '' ?>><?= gettext('Draft — not accepting signups') ?></option>
                            <option value="open" <?= $currentStatus === 'open' ? 'selected' : '' ?>><?= gettext('Open — accepting signups') ?></option>
                            <option value="closed" <?= $currentStatus === 'closed' ? 'selected' : '' ?>><?= gettext('Closed — signups finished') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sheet-close-at"><?= gettext('Stop accepting signups at') ?></label>
                        <input type="datetime-local" class="form-control" id="sheet-close-at" name="closeAt"
                               value="<?= $isNew ? '' : InputUtils::escapeAttribute($asLocalInput($sheet['shs_close_at'] ?? null)) ?>">
                        <div class="form-text"><?= gettext('Leave blank to keep the sheet open until you close it.') ?></div>
                    </div>

                    <?php if ($allowPublic) : ?>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="sheet-is-public" name="isPublic"
                                <?= (!$isNew && !empty($sheet['shs_is_public'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sheet-is-public"><?= gettext('Publish a public share link') ?></label>
                            <div class="form-text">
                                <?= gettext('Anyone with the link can sign up without a CRM login. The link is unguessable, but treat it as shareable rather than secret.') ?>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-light border small mb-3">
                            <?= gettext('Public share links are turned off in this plugin\'s settings.') ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="sheet-require-email" name="requireEmail"
                            <?= ($isNew || !empty($sheet['shs_require_email'])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sheet-require-email"><?= gettext('Require an email address') ?></label>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="sheet-allow-comments" name="allowComments"
                            <?= ($isNew || !empty($sheet['shs_allow_comments'])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sheet-allow-comments"><?= gettext('Let people add a note') ?></label>
                        <div class="form-text"><?= gettext('For example, which dish they are bringing.') ?></div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" id="sheet-save">
                    <i class="fa-solid fa-floppy-disk fa-fw"></i>
                    <?= $isNew ? gettext('Create Sheet') : gettext('Save Changes') ?>
                </button>
                <a class="btn btn-outline-secondary"
                   href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets<?= $isNew ? '' : '/' . $sheetId ?>">
                    <?= gettext('Cancel') ?>
                </a>
            </div>
        </div>
    </div>
</form>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    const rootPath = <?= json_encode(SystemURLs::getRootPath()) ?>;
    const sheetId = <?= json_encode($sheetId) ?>;
    const form = document.getElementById('sheet-editor-form');
    const alertBox = document.getElementById('sheet-editor-alert');
    const saveButton = document.getElementById('sheet-save');

    function showError(message) {
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        alertBox.classList.add('d-none');
        saveButton.disabled = true;

        const payload = {
            title: document.getElementById('sheet-title').value,
            description: document.getElementById('sheet-description').value,
            eventId: document.getElementById('sheet-event').value,
            location: document.getElementById('sheet-location').value,
            starts: document.getElementById('sheet-starts').value,
            ends: document.getElementById('sheet-ends').value,
            status: document.getElementById('sheet-status').value,
            closeAt: document.getElementById('sheet-close-at').value,
            isPublic: document.getElementById('sheet-is-public')?.checked ? 'true' : 'false',
            requireEmail: document.getElementById('sheet-require-email').checked ? 'true' : 'false',
            allowComments: document.getElementById('sheet-allow-comments').checked ? 'true' : 'false'
        };

        const url = sheetId === null
            ? rootPath + '/plugins/signup-sheets/api/sheets'
            : rootPath + '/plugins/signup-sheets/api/sheets/' + sheetId;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (!response.ok || data.success === false) {
                showError(data.message || <?= json_encode(gettext('The sheet could not be saved.')) ?>);
                saveButton.disabled = false;
                return;
            }

            window.location.href = rootPath + '/plugins/signup-sheets/' + (sheetId ?? data.sheetId);
        } catch (error) {
            showError(<?= json_encode(gettext('The sheet could not be saved.')) ?>);
            saveButton.disabled = false;
        }
    });
})();
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
