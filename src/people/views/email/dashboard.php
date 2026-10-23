<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<?php if (!$bEmailEnabled): ?>
<div class="card border-warning">
    <div class="card-header bg-warning-lt">
        <h3 class="card-title text-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= gettext('Email is Disabled') ?></h3>
    </div>
    <div class="card-body">
        <p class="mb-2"><?= gettext('Email functionality is currently disabled. The following features will not work until email is enabled') ?>:</p>
        <ul class="mb-2">
            <li><?= gettext('Password reset emails — users cannot recover their accounts') ?></li>
            <li><?= gettext('New member notification emails') ?></li>
            <li><?= gettext('Email links in group and people views') ?></li>
            <li><?= gettext('Kiosk check-in email notifications') ?></li>
        </ul>
        <?php if ($isAdmin): ?>
        <p class="mb-0"><a href="<?= SystemURLs::getRootPath() ?>/admin/email"><?= gettext('Email Settings') ?></a></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-envelope me-2"></i><?= gettext('Email Tools') ?></h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= SystemURLs::getRootPath() ?>/people/email/duplicate" class="btn btn-outline-warning">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><?= gettext('Duplicates') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/people/email/missing" class="btn btn-outline-danger">
                <i class="fa-solid fa-bell-slash me-1"></i><?= gettext('People Without Emails') ?>
            </a>
        </div>
    </div>
</div>

<?php include SystemURLs::getDocumentRoot() . '/people/views/partials/email-log-table.php'; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
