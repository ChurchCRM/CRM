<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Email Tools Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-envelope me-2"></i><?= gettext('Email Tools') ?></h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap" style="gap:.5rem;">
            <a href="<?= SystemURLs::getRootPath() ?>/v2/people/email-export" class="btn btn-outline-info">
                <i class="fa-solid fa-table me-1"></i><?= gettext('Export') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/email/duplicate" class="btn btn-outline-warning">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><?= gettext('Duplicates') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/email/missing" class="btn btn-outline-danger">
                <i class="fa-solid fa-bell-slash me-1"></i><?= gettext('Missing') ?>
            </a>
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug/email" class="btn btn-outline-secondary">
                <i class="fa-solid fa-stethoscope me-1"></i><?= gettext('Debug') ?>
            </a>
            <?php } ?>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
