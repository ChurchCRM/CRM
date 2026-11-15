<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\InputUtils;

$viewedUserId = (int) $user->getId();
$viewedPerson = $user->getPerson();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card mb-3" data-cy="user-identity">
    <div class="card-body">
        <h2 class="card-title">
            <?php if ($viewedPerson !== null): ?>
                <a href="<?= Person::getViewURIForId($viewedUserId) ?>"><?= InputUtils::escapeHTML($user->getFullName()) ?></a>
            <?php else: ?>
                <?= InputUtils::escapeHTML($user->getFullName()) ?>
            <?php endif; ?>
        </h2>
        <dl class="row mb-0">
            <dt class="col-sm-3"><?= gettext('Username') ?></dt>
            <dd class="col-sm-9 text-break" data-cy="user-username"><?= InputUtils::escapeHTML($user->getUserName()) ?></dd>
            <dt class="col-sm-3"><?= gettext('Email') ?></dt>
            <dd class="col-sm-9 text-break"><?= InputUtils::escapeHTML($user->getEmail() ?: gettext('Not set')) ?></dd>
            <?php if ($viewedPerson !== null && (int) $viewedPerson->getFamId() > 0): ?>
                <dt class="col-sm-3"><?= gettext('Family') ?></dt>
                <dd class="col-sm-9 text-break">
                    <a href="<?= Family::getFamilyViewURIForId((int) $viewedPerson->getFamId()) ?>"><?= InputUtils::escapeHTML($viewedPerson->getFamily()->getName()) ?></a>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php $signInBlockedLabel = $user->getSignInBlockedLabel(); ?>
<?php if ($signInBlockedLabel !== null): ?>
<div class="alert alert-secondary d-flex align-items-center mb-3" role="alert" data-cy="sign-in-blocked-alert">
    <i class="fa-solid fa-user-slash me-2 flex-shrink-0 fs-3"></i>
    <div>
        <strong><?= InputUtils::escapeHTML($signInBlockedLabel) ?></strong>
        <div class="small"><?= gettext('Sign-in, sessions and the API key are blocked until this is resolved on the linked person\'s record.') ?></div>
    </div>
</div>
<?php endif; ?>
<?php if ($user->isLocked()): ?>
<div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
    <i class="fa-solid fa-lock me-2 flex-shrink-0 fs-3"></i>
    <div>
        <strong><?= gettext('Account locked') ?></strong>
        <div class="small"><?= gettext('This account is locked because of too many failed login attempts.') ?></div>
        <div class="small" data-cy="lockout-minutes-left"><?= sprintf(gettext('Minutes until it unlocks by itself: %d'), \ChurchCRM\Authentication\SignInThrottle::minutesUntilUnlock($user)) ?></div>
    </div>
</div>
<?php endif; ?>
<?php if ($user->getNeedPasswordChange()): ?>
<div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
    <i class="fa-solid fa-key me-2 flex-shrink-0 fs-3"></i>
    <div>
        <strong><?= gettext('Password change required') ?></strong>
        <div class="small"><?= gettext('This user must set a new password the next time they sign in.') ?></div>
    </div>
</div>
<?php endif; ?>

<?php if ($canAdminister): ?>
<div class="d-flex flex-wrap gap-2 mb-3" data-cy="user-admin-actions">
    <a class="btn btn-primary" href="<?= SystemURLs::getRootPath() ?>/admin/system/users/<?= $viewedUserId ?>/edit">
        <i class="fa-solid fa-pencil me-2" aria-hidden="true"></i><?= gettext('Edit User') ?>
    </a>
    <a class="btn btn-outline-primary" href="<?= SystemURLs::getRootPath() ?>/admin/system/user/<?= $viewedUserId ?>/changePassword">
        <i class="fa-solid fa-key me-2" aria-hidden="true"></i><?= gettext('Change Password') ?>
    </a>
    <?php if ($canResetPassword): ?>
        <button type="button" class="btn btn-outline-secondary js-reset-user-password" data-user_id="<?= $viewedUserId ?>" data-user_name="<?= InputUtils::escapeAttribute($user->getFullName()) ?>">
            <?= gettext('Reset Password via Email') ?>
        </button>
    <?php endif; ?>
    <?php if ($user->getFailedLogins() > 0): ?>
        <button type="button" class="btn btn-outline-secondary js-reset-login-count" data-user_id="<?= $viewedUserId ?>" data-user_name="<?= InputUtils::escapeAttribute($user->getFullName()) ?>">
            <?= gettext('Reset Failed Logins') ?>
        </button>
    <?php endif; ?>
    <?php if ($user->is2FactorAuthEnabled()): ?>
        <button type="button" class="btn btn-outline-warning js-disable-2fa" data-user_id="<?= $viewedUserId ?>" data-user_name="<?= InputUtils::escapeAttribute($user->getFullName()) ?>">
            <?= gettext('Disable 2FA') ?>
        </button>
    <?php endif; ?>
    <?php if ($canDelete): ?>
        <button type="button" class="btn btn-outline-danger js-delete-user" data-user_id="<?= $viewedUserId ?>" data-user_name="<?= InputUtils::escapeAttribute($user->getFullName()) ?>">
            <i class="fa-solid fa-trash me-2" aria-hidden="true"></i><?= gettext('Delete User') ?>
        </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-4">
        <div class="card" data-cy="user-status">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Account status') ?></h3>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <?php foreach ($statusRows as $statusRow): ?>
                        <dt><?= InputUtils::escapeHTML($statusRow['label']) ?></dt>
                        <dd class="text-break"><?= InputUtils::escapeHTML($statusRow['value']) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card" data-cy="user-permissions">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Roles & permissions') ?></h3>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4"><?= gettext('Access level') ?></dt>
                    <dd class="col-sm-8" data-cy="user-access-level"><?= $user->isAdmin() ? gettext('Administrator') : gettext('Custom') ?></dd>
                </dl>
                <?php foreach ($permissionGroups as $permissionGroup): ?>
                    <div class="border rounded mb-3">
                        <div class="px-3 py-2 border-bottom bg-surface-secondary">
                            <h4 class="mb-0"><?= InputUtils::escapeHTML($permissionGroup['group']) ?></h4>
                        </div>
                        <?php foreach ($permissionGroup['rows'] as $rowIndex => $permission): ?>
                            <div class="row g-0 align-items-center px-3 py-2<?= $rowIndex > 0 ? ' border-top' : '' ?>" data-cy="permission-row" data-granted="<?= $permission['granted'] ? 'true' : 'false' ?>">
                                <div class="col-sm-6" data-cy="permission-label"><?= InputUtils::escapeHTML($permission['label']) ?></div>
                                <div class="col-sm-6">
                                    <?php if ($permission['granted']): ?>
                                        <span class="badge bg-success-lt text-success"><?= gettext('Yes') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-lt text-secondary"><?= gettext('No') ?></span>
                                        <span class="small text-body-secondary ms-1" data-cy="permission-reason"><?= InputUtils::escapeHTML($permission['reason']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($loginAsPersonHistory !== null && $canAdminister): ?>
    <?php include SystemURLs::getDocumentRoot() . '/people/views/partials/login-as-person-history.php'; ?>
<?php endif; ?>

<script src="<?= SystemURLs::assetVersioned('/skin/js/users.js') ?>"></script>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
