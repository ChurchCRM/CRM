<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<?php if (!empty($sPasswordChangeSuccess)) : ?>
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body text-center">
                <i class="fa-solid fa-check-circle fa-3x text-success mb-3"></i>
                <h3><?= gettext('Password Change Successful') ?></h3>
                <p><?= gettext('Password successfully changed for') ?>: <?= InputUtils::escapeHTML($user->getFullName()) ?></p>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/users" class="btn btn-primary mt-2">
                    <?= gettext('Back to Users') ?>
                </a>
            </div>
        </div>
    </div>
</div>
<?php else : ?>
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <?= gettext('Enter a password that meets the configured minimum length and is no more than 72 bytes.') ?>
            </div>
            <form method="post" action="">
                <?= CSRFUtils::getTokenInputField('admin_change_password') ?>
                <div class="card-body">
                    <?php if (!empty($bRequiresReauthentication)) : ?>
                    <div class="mb-3">
                        <label for="CurrentPassword"><?= gettext('Your Current Administrator Password') ?>:</label>
                        <input type="password" name="CurrentPassword" id="CurrentPassword" class="form-control" value="" maxlength="1024" autocomplete="current-password" required>
                        <span id="CurrentPasswordError" class="form-field-error"><?= InputUtils::escapeHTML($sCurrentPasswordError ?? '') ?></span>
                        <div class="form-text"><?= gettext('Confirm your own password before changing another user\'s credentials.') ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="NewPassword1"><?= gettext('New Password') ?>:</label>
                        <input type="password" name="NewPassword1" id="NewPassword1" class="form-control" value="" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="NewPassword2"><?= gettext('Confirm New Password') ?>:</label>
                        <input type="password" name="NewPassword2" id="NewPassword2" class="form-control" value="" autocomplete="new-password" required>
                        <span id="NewPasswordError" class="form-field-error"><?= InputUtils::escapeHTML($sNewPasswordError ?? '') ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Save') ?>">
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= SystemURLs::assetVersioned('/skin/js/PasswordChange.js') ?>"></script>
<?php endif; ?>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
