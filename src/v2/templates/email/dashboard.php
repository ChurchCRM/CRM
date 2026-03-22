<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= gettext('Email Functions') ?></h3>
    </div>
    <div class="card-body">
        <div class="btn-group" role="group">
            <a href="<?= SystemURLs::getRootPath()?>/email/MemberEmailExport.php" class="btn btn-outline-info" title="<?= gettext('Export member emails') ?>"><i class="fa-solid fa-table me-2"></i><?= gettext('Export') ?></a>
            <a href="<?= SystemURLs::getRootPath()?>/v2/email/duplicate" class="btn btn-outline-warning" title="<?= gettext('Find duplicate email addresses') ?>"><i class="fa-solid fa-exclamation-triangle me-2"></i><?= gettext('Duplicates') ?></a>
            <a href="<?= SystemURLs::getRootPath()?>/v2/email/missing" class="btn btn-outline-danger" title="<?= gettext('Find families without email addresses') ?>"><i class="fa-solid fa-bell-slash me-2"></i><?= gettext('Missing') ?></a>
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
            <a href="<?= SystemURLs::getRootPath()?>/admin/system/debug/email" class="btn btn-outline-secondary" title="<?= gettext('Email system diagnostics') ?>"><i class="fa-solid fa-stethoscope me-2"></i><?= gettext('Debug') ?></a>
            <?php } ?>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
