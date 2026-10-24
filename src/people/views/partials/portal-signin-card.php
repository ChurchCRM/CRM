<?php

use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

/*
 * The person page's "Portal sign-in" card (member sign-in design §11.3).
 * Staff with Edit Records see the state and may confirm an email moved from
 * the family email; administrators may confirm any unconfirmed email (§9),
 * turn Disable sign-in on or off and sign the person out everywhere.
 *
 * Variables: $portalSignIn (PersonSignInService::statusFor()), $portalSignInCanManage,
 * $portalSignInIsSelf, $portalSignInCsrfToken, $iPersonID, and for administrators
 * $portalAccess and $portalGrantChoices (partials/portal-signin-access.php).
 * Behaviour: webpack/people/person-signin-card.ts.
 */
?>
<div class="card mb-3" id="portal-signin-card"
     data-person-id="<?= (int) $iPersonID ?>"
     data-csrf-token="<?= InputUtils::escapeAttribute($portalSignInCsrfToken) ?>">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title m-0"><i class="fa-solid fa-right-to-bracket me-1"></i> <?= gettext('Portal sign-in') ?></h3>
    </div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            <li class="mb-2" id="portal-signin-status" data-reason="<?= InputUtils::escapeAttribute((string) $portalSignIn['reason']) ?>">
                <?php if ($portalSignIn['maySignIn']) : ?>
                    <span class="badge bg-green-lt text-green"><i class="fa-solid fa-circle-check me-1"></i><?= gettext('May sign in') ?></span>
                <?php else : ?>
                    <span class="badge bg-secondary-lt text-secondary"><i class="fa-solid fa-ban me-1"></i><?= gettext('Cannot sign in') ?></span>
                    <div class="small text-secondary mt-1"><?= InputUtils::escapeHTML((string) $portalSignIn['reasonLabel']) ?></div>
                <?php endif; ?>
                <?php if (!$portalSignIn['memberSignInEnabled']) : ?>
                    <div class="small text-secondary mt-1"><?= gettext('Member sign-in is off for the church.') ?></div>
                <?php endif; ?>
            </li>
            <?php if ($portalSignIn['signInDisabled']) : ?>
                <li class="mb-2" id="portal-signin-disabled">
                    <i class="fa-solid fa-lock me-2 text-danger"></i><?= gettext('Sign-in is disabled') ?>
                    <?php if ($portalSignIn['disabledAt'] !== null) : ?>
                        <div class="small text-secondary">
                            <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($portalSignIn['disabledAt'], true)) ?>
                            <?php if ($portalSignIn['disabledBy'] !== null) : ?>
                                &middot; <?= InputUtils::escapeHTML($portalSignIn['disabledBy']['name']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($portalSignIn['disabledReason'] !== null) : ?>
                        <div class="small" id="portal-signin-disabled-reason"><?= InputUtils::escapeHTML($portalSignIn['disabledReason']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
            <?php if ($portalSignIn['unconfirmedEmail'] !== null) : ?>
                <li class="mb-2" id="portal-signin-unconfirmed">
                    <i class="fa-solid fa-envelope me-2 text-warning"></i><?= gettext('Unconfirmed email') ?>:
                    <span class="text-break"><?= InputUtils::escapeHTML($portalSignIn['unconfirmedEmail']) ?></span>
                    <div class="small text-secondary">
                        <?= InputUtils::escapeHTML((string) $portalSignIn['unconfirmedSourceLabel']) ?>
                        <?php if ($portalSignIn['unconfirmedAt'] !== null) : ?>
                            &middot; <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($portalSignIn['unconfirmedAt'], true)) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($portalSignIn['canConfirmEmail']) : ?>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="portal-signin-confirm-email"
                                data-email="<?= InputUtils::escapeAttribute($portalSignIn['unconfirmedEmail']) ?>">
                            <i class="fa-solid fa-check me-1"></i><?= gettext('Confirm Email Address') ?>
                        </button>
                    <?php else : ?>
                        <div class="small text-secondary mt-1" id="portal-signin-confirm-refusal"><?= InputUtils::escapeHTML((string) $portalSignIn['confirmEmailRefusal']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
            <li class="mb-1"><i class="fa-solid fa-clock-rotate-left me-2 text-body-secondary"></i><?= gettext('Last sign-in') ?>:
                <?= $portalSignIn['lastSignIn'] !== null ? InputUtils::escapeHTML(DateTimeUtils::formatDate($portalSignIn['lastSignIn'], true)) : gettext('Never') ?>
            </li>
            <li class="mb-1" id="portal-signin-devices"><i class="fa-solid fa-laptop me-2 text-body-secondary"></i><?= sprintf(gettext('Remembered devices: %d'), (int) $portalSignIn['devices']) ?></li>
            <li class="mb-1" id="portal-signin-passkeys"><i class="fa-solid fa-key me-2 text-body-secondary"></i><?= sprintf(gettext('Passkeys: %d'), (int) $portalSignIn['passkeys']) ?></li>
        </ul>
        <?php if ($portalAccess !== null) : ?>
            <?php include __DIR__ . '/portal-signin-access.php'; ?>
        <?php endif; ?>
    </div>
    <?php if ($portalSignInCanManage) : ?>
        <div class="card-footer d-flex flex-wrap gap-2">
            <?php if ($portalSignIn['signInDisabled']) : ?>
                <button type="button" class="btn btn-sm btn-outline-success" id="portal-signin-enable">
                    <i class="fa-solid fa-lock-open me-1"></i><?= gettext('Enable sign-in') ?>
                </button>
            <?php else : ?>
                <button type="button" class="btn btn-sm btn-outline-danger" id="portal-signin-disable"
                        data-bs-toggle="modal" data-bs-target="#portal-signin-disable-modal"
                        <?= $portalSignInIsSelf ? 'disabled title="' . InputUtils::escapeAttribute(gettext("You can't disable your own sign-in.")) . '"' : '' ?>>
                    <i class="fa-solid fa-lock me-1"></i><?= gettext('Disable sign-in') ?>
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="portal-signin-sign-out">
                <i class="fa-solid fa-right-from-bracket me-1"></i><?= gettext('Sign out everywhere') ?>
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if ($portalSignInCanManage) : ?>
    <div class="modal fade" id="portal-signin-disable-modal" tabindex="-1" aria-labelledby="portal-signin-disable-title" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="portal-signin-disable-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="portal-signin-disable-title"><?= gettext('Disable sign-in') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <p><?= gettext('This person can no longer sign in anywhere, to the Member Portal or as staff. Every session ends, and their remembered devices and passkeys are removed.') ?></p>
                    <label class="form-label required" for="portal-signin-disable-reason"><?= gettext('Reason') ?></label>
                    <textarea class="form-control" id="portal-signin-disable-reason" name="reason" rows="3" maxlength="255" required></textarea>
                    <div class="form-text"><?= gettext('Only administrators see the reason.') ?></div>
                    <div class="invalid-feedback d-block" id="portal-signin-disable-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                    <button type="submit" class="btn btn-danger" id="portal-signin-disable-confirm">
                        <i class="fa-solid fa-lock me-1"></i><?= gettext('Disable sign-in') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
