<?php

/**
 * Signup Sheets — the public page anyone with the share link can use.
 *
 * Shows who has claimed what, by name, so people do not double up. Email
 * addresses and phone numbers are never rendered on this surface.
 *
 * @var array<string, mixed>                            $sheet
 * @var array<string, array<int, array<string, mixed>>> $slotsByCategory
 * @var array<int, array<int, array<string, mixed>>>    $claimsBySlot
 * @var bool                                            $isAccepting
 * @var string                                          $contactEmail
 * @var string                                          $sheetToken
 * @var string|null                                     $errorMessage
 * @var array<string, string>|null                      $confirmation
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

$formAction = SystemURLs::getRootPath() . '/external/signup-sheets/' . rawurlencode($sheetToken) . '/claim';
?>

<div class="container py-4 signup-sheet-public">

    <div class="mb-4">
        <h1 class="mb-1"><?= InputUtils::escapeHTML($sheet['shs_title']) ?></h1>
        <div class="text-body-secondary">
            <?php if (!empty($sheet['shs_starts'])) : ?>
                <span class="me-3"><i class="fa-solid fa-calendar fa-fw"></i>
                    <?= date('l, F j, Y \a\t g:i a', strtotime((string) $sheet['shs_starts'])) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($sheet['shs_location'])) : ?>
                <span><i class="fa-solid fa-location-dot fa-fw"></i><?= InputUtils::escapeHTML($sheet['shs_location']) ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($sheet['shs_description'])) : ?>
            <p class="mt-3 mb-0"><?= nl2br(InputUtils::escapeHTML($sheet['shs_description'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($confirmation !== null) : ?>
        <div class="alert alert-success">
            <h4 class="alert-heading"><i class="fa-solid fa-circle-check fa-fw"></i> <?= gettext('You are signed up. Thank you!') ?></h4>
            <p class="mb-2"><?= gettext('Keep this link if you need to cancel later') . ':' ?></p>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" readonly
                       value="<?= InputUtils::escapeAttribute($confirmation['manageUrl']) ?>">
            </div>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== null) : ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation fa-fw"></i>
            <?= InputUtils::escapeHTML($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!$isAccepting) : ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-lock fa-fw"></i>
            <?= gettext('This sheet is not accepting signups right now.') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($slotsByCategory)) : ?>
        <div class="card">
            <div class="card-body text-center py-5 text-body-secondary">
                <?= gettext('Nothing has been posted on this sheet yet. Please check back soon.') ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($slotsByCategory as $category => $slots) : ?>
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">
                    <?= $category === '' ? gettext('How you can help') : InputUtils::escapeHTML($category) ?>
                </h2>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($slots as $slot) : ?>
                    <?php
                    $slotId = (int) $slot['sls_ID'];
                    $claims = $claimsBySlot[$slotId] ?? [];
                    $isFull = !empty($slot['is_full']);
                    $remaining = $slot['remaining'];
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= InputUtils::escapeHTML($slot['sls_title']) ?></div>
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

                                <?php if (!empty($claims)) : ?>
                                    <div class="small mt-2">
                                        <span class="text-body-secondary"><?= gettext('Signed up') . ':' ?></span>
                                        <?php
                                        $names = [];
                                        foreach ($claims as $claim) {
                                            $display = (string) $claim['sgc_name'];
                                            if ((int) $claim['sgc_quantity'] > 1) {
                                                $display .= ' (×' . (int) $claim['sgc_quantity'] . ')';
                                            }
                                            $names[] = InputUtils::escapeHTML($display);
                                        }
                                        echo implode(', ', $names);
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="text-end signup-button-group">
                                <?php if ($isFull) : ?>
                                    <span class="badge bg-success mb-2"><?= gettext('Filled') ?></span>
                                <?php elseif ($remaining !== null) : ?>
                                    <div class="small text-body-secondary mb-2">
                                        <?= sprintf(gettext('%d still needed'), (int) $remaining) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isAccepting && !$isFull) : ?>
                                    <button class="btn btn-primary btn-sm js-open-signup"
                                            type="button"
                                            data-slot-id="<?= $slotId ?>"
                                            data-slot-title="<?= InputUtils::escapeAttribute($slot['sls_title']) ?>"
                                            data-allow-quantity="<?= !empty($slot['sls_allow_quantity']) ? '1' : '0' ?>"
                                            data-max-quantity="<?= $remaining === null ? 99 : (int) $remaining ?>">
                                        <?= gettext('Sign up') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($contactEmail)) : ?>
        <p class="text-body-secondary small text-center mt-4">
            <?= gettext('Questions?') ?>
            <a href="mailto:<?= InputUtils::escapeAttribute($contactEmail) ?>"><?= InputUtils::escapeHTML($contactEmail) ?></a>
        </p>
    <?php endif; ?>
</div>

<?php if ($isAccepting) : ?>
<!-- Signup form -->
<div class="modal fade" id="public-signup-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= InputUtils::escapeAttribute($formAction) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= gettext('Sign up') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= InputUtils::escapeAttribute(gettext('Close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-body-secondary" id="public-slot-label"></p>
                    <input type="hidden" name="slotId" id="public-slot-id" value="">

                    <!-- Honeypot: hidden from people, tempting to bots. -->
                    <div class="shepherd-honeypot" aria-hidden="true">
                        <label for="website"><?= gettext('Leave this field empty') ?></label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="public-name"><?= gettext('Your name') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="public-name" name="name" maxlength="255" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="public-email">
                            <?= gettext('Email') ?>
                            <?php if (!empty($sheet['shs_require_email'])) : ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <input type="email" class="form-control" id="public-email" name="email" maxlength="254"
                            <?= !empty($sheet['shs_require_email']) ? 'required' : '' ?>>
                        <div class="form-text"><?= gettext('Shared with the organizers so they can reach you about this event. It is not shown on this page.') ?></div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label" for="public-phone"><?= gettext('Phone') ?></label>
                            <input type="text" class="form-control" id="public-phone" name="phone" maxlength="50">
                        </div>
                        <div class="col-6 mb-3" id="public-quantity-wrapper">
                            <label class="form-label" for="public-quantity"><?= gettext('How many') ?></label>
                            <input type="number" class="form-control" id="public-quantity" name="quantity" min="1" max="99" value="1">
                        </div>
                    </div>

                    <?php if (!empty($sheet['shs_allow_comments'])) : ?>
                        <div class="mb-0">
                            <label class="form-label" for="public-comment"><?= gettext('Note') ?></label>
                            <input type="text" class="form-control" id="public-comment" name="comment" maxlength="1000"
                                   placeholder="<?= InputUtils::escapeAttribute(gettext('Bringing apple pie')) ?>">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= gettext('Sign me up') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    const modalElement = document.getElementById('public-signup-modal');
    if (!modalElement) {
        return;
    }
    const modal = new bootstrap.Modal(modalElement);

    document.querySelectorAll('.js-open-signup').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('public-slot-id').value = button.getAttribute('data-slot-id');
            document.getElementById('public-slot-label').textContent = button.getAttribute('data-slot-title');

            const allowQuantity = button.getAttribute('data-allow-quantity') === '1';
            const wrapper = document.getElementById('public-quantity-wrapper');
            const quantity = document.getElementById('public-quantity');
            wrapper.style.display = allowQuantity ? '' : 'none';
            quantity.value = '1';
            quantity.max = allowQuantity ? button.getAttribute('data-max-quantity') : '1';

            modal.show();
        });
    });
})();
</script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
