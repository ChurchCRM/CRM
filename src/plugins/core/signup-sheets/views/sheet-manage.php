<?php

/**
 * Signup Sheets — manage one sheet: its slots, who claimed them, and sharing.
 *
 * @var array<string, mixed>                                   $sheet
 * @var array<string, array<int, array<string, mixed>>>        $slotsByCategory
 * @var array<int, array<int, array<string, mixed>>>           $claimsBySlot
 * @var bool                                                   $isAccepting
 * @var bool                                                   $allowPublic
 * @var string|null                                            $publicUrl
 * @var bool                                                   $canEdit
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$sheetId = (int) $sheet['shs_ID'];
$status = (string) $sheet['shs_status'];
$statusBadges = ['draft' => 'bg-secondary', 'open' => 'bg-success', 'closed' => 'bg-dark'];

$totalCapacity = 0;
$totalClaimed = 0;
foreach ($slotsByCategory as $slots) {
    foreach ($slots as $slot) {
        $totalCapacity += (int) $slot['sls_capacity'];
        $totalClaimed += (int) $slot['claimed'];
    }
}
?>

<div class="row mb-3">
    <div class="col-12 d-flex align-items-center flex-wrap gap-2">
        <nav aria-label="breadcrumb" class="flex-grow-1">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><i class="fa-solid fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets"><?= gettext('Signup Sheets') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= InputUtils::escapeHTML($sheet['shs_title']) ?></li>
            </ol>
        </nav>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary" href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets/<?= $sheetId ?>/export">
                <i class="fa-solid fa-file-csv fa-fw"></i> <?= gettext('Export roster') ?>
            </a>
            <?php if ($canEdit) : ?>
                <a class="btn btn-outline-secondary" href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets/<?= $sheetId ?>/edit">
                    <i class="fa-solid fa-pen fa-fw"></i> <?= gettext('Edit sheet') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="sheet-alert" class="alert alert-danger d-none" role="alert"></div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start gap-3">
            <div class="flex-grow-1">
                <h2 class="mb-1"><?= InputUtils::escapeHTML($sheet['shs_title']) ?></h2>
                <div class="text-body-secondary">
                    <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>"><?= InputUtils::escapeHTML(ucfirst($status)) ?></span>
                    <?php if (!$isAccepting && $status === 'open') : ?>
                        <span class="badge bg-warning text-dark"><?= gettext('Past its closing time') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($sheet['shs_starts'])) : ?>
                        <span class="ms-2"><i class="fa-solid fa-calendar fa-fw"></i><?= date('M j, Y g:i a', strtotime((string) $sheet['shs_starts'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($sheet['shs_location'])) : ?>
                        <span class="ms-2"><i class="fa-solid fa-location-dot fa-fw"></i><?= InputUtils::escapeHTML($sheet['shs_location']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($sheet['event_title'])) : ?>
                        <span class="ms-2"><i class="fa-solid fa-link fa-fw"></i><?= InputUtils::escapeHTML($sheet['event_title']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($sheet['shs_description'])) : ?>
                    <p class="mt-2 mb-0"><?= nl2br(InputUtils::escapeHTML($sheet['shs_description'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="h3 mb-0"><?= $totalClaimed ?><?= $totalCapacity > 0 ? ' / ' . $totalCapacity : '' ?></div>
                <div class="text-body-secondary small"><?= gettext('slots filled') ?></div>
            </div>
        </div>

        <?php if ($allowPublic && $publicUrl !== null) : ?>
            <hr>
            <label class="form-label small text-body-secondary mb-1"><?= gettext('Public share link') ?></label>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="public-share-url" readonly
                       value="<?= InputUtils::escapeAttribute($publicUrl) ?>">
                <button class="btn btn-outline-secondary" type="button" id="copy-share-url">
                    <i class="fa-solid fa-copy fa-fw"></i> <?= gettext('Copy') ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit) : ?>
    <div class="d-flex justify-content-end gap-2 mb-3">
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#add-slot-modal">
            <i class="fa-solid fa-plus fa-fw"></i> <?= gettext('Add slot') ?>
        </button>
    </div>
<?php endif; ?>

<?php if (empty($slotsByCategory)) : ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-list-check fa-3x text-body-secondary mb-3"></i>
            <h3><?= gettext('This sheet has no slots yet') ?></h3>
            <p class="text-body-secondary mb-0">
                <?= gettext('Add a slot for each thing you need — a dish, a role, a shift — and set how many people you need for it.') ?>
            </p>
        </div>
    </div>
<?php else : ?>
    <?php foreach ($slotsByCategory as $category => $slots) : ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $category === '' ? gettext('Slots') : InputUtils::escapeHTML($category) ?>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= gettext('Slot') ?></th>
                            <th class="signup-count-column"><?= gettext('Filled') ?></th>
                            <th><?= gettext('Who signed up') ?></th>
                            <?php if ($canEdit) : ?>
                                <th class="signup-actions-column"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot) : ?>
                            <?php $slotId = (int) $slot['sls_ID']; ?>
                            <tr>
                                <td>
                                    <strong><?= InputUtils::escapeHTML($slot['sls_title']) ?></strong>
                                    <?php if (!empty($slot['sls_description'])) : ?>
                                        <div class="small text-body-secondary"><?= InputUtils::escapeHTML($slot['sls_description']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($slot['sls_starts'])) : ?>
                                        <div class="small text-body-secondary">
                                            <i class="fa-solid fa-clock fa-fw"></i>
                                            <?= date('g:i a', strtotime((string) $slot['sls_starts'])) ?>
                                            <?php if (!empty($slot['sls_ends'])) : ?>
                                                &ndash; <?= date('g:i a', strtotime((string) $slot['sls_ends'])) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) $slot['sls_capacity'] === 0) : ?>
                                        <span class="badge bg-light text-dark"><?= (int) $slot['claimed'] ?> / <?= gettext('any') ?></span>
                                    <?php elseif (!empty($slot['is_full'])) : ?>
                                        <span class="badge bg-success"><?= gettext('Full') ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-warning text-dark">
                                            <?= (int) $slot['claimed'] ?> / <?= (int) $slot['sls_capacity'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $claims = $claimsBySlot[$slotId] ?? []; ?>
                                    <?php if (empty($claims)) : ?>
                                        <span class="text-body-secondary small"><?= gettext('Nobody yet') ?></span>
                                    <?php else : ?>
                                        <ul class="list-unstyled mb-0 small">
                                            <?php foreach ($claims as $claim) : ?>
                                                <li class="d-flex align-items-start gap-2 mb-1">
                                                    <span class="flex-grow-1">
                                                        <?= InputUtils::escapeHTML($claim['sgc_name']) ?>
                                                        <?php if ((int) $claim['sgc_quantity'] > 1) : ?>
                                                            <span class="badge bg-secondary">&times;<?= (int) $claim['sgc_quantity'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($claim['sgc_email'])) : ?>
                                                            <span class="text-body-secondary">&lt;<?= InputUtils::escapeHTML($claim['sgc_email']) ?>&gt;</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($claim['sgc_comment'])) : ?>
                                                            <div class="text-body-secondary fst-italic"><?= InputUtils::escapeHTML($claim['sgc_comment']) ?></div>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if ($canEdit) : ?>
                                                        <button type="button" class="btn btn-link btn-sm p-0 text-danger js-remove-claim"
                                                                data-claim-id="<?= (int) $claim['sgc_ID'] ?>"
                                                                title="<?= InputUtils::escapeAttribute(gettext('Remove this signup')) ?>">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canEdit) : ?>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-primary btn-sm js-add-claim"
                                                data-slot-id="<?= $slotId ?>"
                                                data-slot-title="<?= InputUtils::escapeAttribute($slot['sls_title']) ?>"
                                                data-allow-quantity="<?= !empty($slot['sls_allow_quantity']) ? '1' : '0' ?>"
                                            <?= (!$isAccepting || !empty($slot['is_full'])) ? 'disabled' : '' ?>>
                                            <i class="fa-solid fa-user-plus fa-fw"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm js-delete-slot"
                                                data-slot-id="<?= $slotId ?>"
                                                data-slot-title="<?= InputUtils::escapeAttribute($slot['sls_title']) ?>">
                                            <i class="fa-solid fa-trash fa-fw"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($canEdit) : ?>
<!-- Add slot -->
<div class="modal fade" id="add-slot-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Add a slot') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="slot-title"><?= gettext('What is needed') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slot-title" maxlength="255"
                           placeholder="<?= InputUtils::escapeAttribute(gettext('Bring a dessert')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="slot-category"><?= gettext('Category') ?></label>
                    <input type="text" class="form-control" id="slot-category" maxlength="100"
                           placeholder="<?= InputUtils::escapeAttribute(gettext('Food, Setup, Cleanup…')) ?>">
                    <div class="form-text"><?= gettext('Slots with the same category are grouped together.') ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="slot-description"><?= gettext('Details') ?></label>
                    <textarea class="form-control" id="slot-description" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label" for="slot-capacity"><?= gettext('How many needed') ?></label>
                        <input type="number" class="form-control" id="slot-capacity" min="0" max="9999" value="1">
                        <div class="form-text"><?= gettext('0 means unlimited.') ?></div>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label" for="slot-starts"><?= gettext('Shift starts') ?></label>
                        <input type="datetime-local" class="form-control" id="slot-starts">
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="slot-allow-quantity">
                    <label class="form-check-label" for="slot-allow-quantity"><?= gettext('One person can cover more than one') ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="save-slot"><?= gettext('Add slot') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add signup -->
<div class="modal fade" id="add-claim-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= gettext('Sign someone up') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-body-secondary" id="claim-slot-label"></p>
                <input type="hidden" id="claim-slot-id">
                <div class="mb-3">
                    <label class="form-label" for="claim-name"><?= gettext('Name') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="claim-name" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="claim-email"><?= gettext('Email') ?></label>
                    <input type="email" class="form-control" id="claim-email" maxlength="254">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label" for="claim-phone"><?= gettext('Phone') ?></label>
                        <input type="text" class="form-control" id="claim-phone" maxlength="50">
                    </div>
                    <div class="col-6 mb-3" id="claim-quantity-wrapper">
                        <label class="form-label" for="claim-quantity"><?= gettext('How many') ?></label>
                        <input type="number" class="form-control" id="claim-quantity" min="1" max="99" value="1">
                    </div>
                </div>
                <?php if (!empty($sheet['shs_allow_comments'])) : ?>
                    <div class="mb-0">
                        <label class="form-label" for="claim-comment"><?= gettext('Note') ?></label>
                        <input type="text" class="form-control" id="claim-comment" maxlength="1000"
                               placeholder="<?= InputUtils::escapeAttribute(gettext('Bringing apple pie')) ?>">
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-primary" id="save-claim"><?= gettext('Add signup') ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    const rootPath = <?= json_encode(SystemURLs::getRootPath()) ?>;
    const sheetId = <?= json_encode($sheetId) ?>;
    const apiBase = rootPath + '/plugins/signup-sheets/api';
    const alertBox = document.getElementById('sheet-alert');
    const genericError = <?= json_encode(gettext('That change could not be saved.')) ?>;

    function showError(message) {
        alertBox.textContent = message || genericError;
        alertBox.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function send(url, method, payload) {
        const options = { method: method, headers: { 'Content-Type': 'application/json' } };
        if (payload) {
            options.body = JSON.stringify(payload);
        }
        options.headers = { ...options.headers, 'X-CSRF-Token': <?= \ChurchCRM\Utils\InputUtils::jsonEncodeForScript(\ChurchCRM\Utils\CSRFUtils::generateToken()) ?> };
        const response = await fetch(url, options);
        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }
        if (!response.ok || data.success === false) {
            throw new Error(data.message || genericError);
        }
        return data;
    }

    // --- Copy the public link ---
    const copyButton = document.getElementById('copy-share-url');
    if (copyButton) {
        copyButton.addEventListener('click', function () {
            const field = document.getElementById('public-share-url');
            field.select();
            navigator.clipboard.writeText(field.value).then(function () {
                copyButton.classList.add('btn-success');
                copyButton.classList.remove('btn-outline-secondary');
                window.setTimeout(function () {
                    copyButton.classList.remove('btn-success');
                    copyButton.classList.add('btn-outline-secondary');
                }, 1500);
            });
        });
    }

    // --- Add a slot ---
    const saveSlotButton = document.getElementById('save-slot');
    if (saveSlotButton) {
        saveSlotButton.addEventListener('click', async function () {
            saveSlotButton.disabled = true;
            try {
                await send(apiBase + '/sheets/' + sheetId + '/slots', 'POST', {
                    title: document.getElementById('slot-title').value,
                    category: document.getElementById('slot-category').value,
                    description: document.getElementById('slot-description').value,
                    capacity: document.getElementById('slot-capacity').value,
                    starts: document.getElementById('slot-starts').value,
                    allowQuantity: document.getElementById('slot-allow-quantity').checked ? 'true' : 'false'
                });
                window.location.reload();
            } catch (error) {
                saveSlotButton.disabled = false;
                showError(error.message);
            }
        });
    }

    // --- Delete a slot ---
    document.querySelectorAll('.js-delete-slot').forEach(function (button) {
        button.addEventListener('click', async function () {
            const title = button.getAttribute('data-slot-title');
            const confirmText = <?= json_encode(gettext('Delete this slot and every signup on it?')) ?>;
            if (!window.confirm(confirmText + '\n\n' + title)) {
                return;
            }
            try {
                await send(apiBase + '/slots/' + button.getAttribute('data-slot-id'), 'DELETE');
                window.location.reload();
            } catch (error) {
                showError(error.message);
            }
        });
    });

    // --- Remove a signup ---
    document.querySelectorAll('.js-remove-claim').forEach(function (button) {
        button.addEventListener('click', async function () {
            const confirmText = <?= json_encode(gettext('Remove this signup?')) ?>;
            if (!window.confirm(confirmText)) {
                return;
            }
            try {
                await send(apiBase + '/claims/' + button.getAttribute('data-claim-id'), 'DELETE');
                window.location.reload();
            } catch (error) {
                showError(error.message);
            }
        });
    });

    // --- Add a signup on someone's behalf ---
    const claimModalElement = document.getElementById('add-claim-modal');
    const claimModal = claimModalElement ? new bootstrap.Modal(claimModalElement) : null;

    document.querySelectorAll('.js-add-claim').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('claim-slot-id').value = button.getAttribute('data-slot-id');
            document.getElementById('claim-slot-label').textContent = button.getAttribute('data-slot-title');
            document.getElementById('claim-quantity-wrapper').style.display =
                button.getAttribute('data-allow-quantity') === '1' ? '' : 'none';
            claimModal?.show();
        });
    });

    const saveClaimButton = document.getElementById('save-claim');
    if (saveClaimButton) {
        saveClaimButton.addEventListener('click', async function () {
            saveClaimButton.disabled = true;
            const commentField = document.getElementById('claim-comment');
            try {
                await send(apiBase + '/sheets/' + sheetId + '/claims', 'POST', {
                    slotId: document.getElementById('claim-slot-id').value,
                    name: document.getElementById('claim-name').value,
                    email: document.getElementById('claim-email').value,
                    phone: document.getElementById('claim-phone').value,
                    quantity: document.getElementById('claim-quantity').value,
                    comment: commentField ? commentField.value : ''
                });
                window.location.reload();
            } catch (error) {
                saveClaimButton.disabled = false;
                claimModal?.hide();
                showError(error.message);
            }
        });
    }
})();
</script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
