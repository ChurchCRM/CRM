<?php

use ChurchCRM\dto\SystemURLs;

$isForced = $isForced ?? false;
$sPageTitle = gettext("Change Password") . ": " . $user->getFullName();
if ($isForced) {
    require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
} else {
    require SystemURLs::getDocumentRoot() . '/Include/Header.php';
}
?>

<?php if ($isForced): ?>
<div class="login-box">
    <div class="card card-outline card-success">
        <div class="card-header text-center">
            <a href="<?= SystemURLs::getRootPath() ?>" class="h1">
                <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" style="max-width:280px; height:auto;" />
            </a>
        </div>
        <div class="card-body text-center">
            <p class="login-box-msg">
                <i class="fa fa-check-circle text-success" style="font-size:2rem;"></i><br>
                <strong><?= gettext('Password Changed') ?></strong><br>
                <small class="text-muted"><?= gettext('Your password has been updated successfully.') ?></small>
            </p>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="btn btn-success btn-block">
                <?= gettext('Continue to Dashboard') ?>
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row">
    <div class="col-md-6">
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Password Change Successful') ?></h3>
            </div>
            <div class="card-body">
                <p><?= sprintf(gettext('The password for %s has been updated.'), $user->getFullName()) ?></p>
                <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="btn btn-success"><?= gettext('Go to Dashboard') ?></a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
if ($isForced) {
    require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
} else {
    require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
}
