<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Email Functions') ?></h3>
    </div>
    <div class="card-body">
        <div class="text-center">
            <a href="<?= SystemURLs::getRootPath()?>/email/MemberEmailExport.php" class="btn btn-app bg-info"><i class="fa-solid fa-table fa-3x"></i><br><?= gettext('Email Export') ?></a>
            <a href="<?= SystemURLs::getRootPath()?>/v2/email/duplicate" class="btn btn-app bg-warning"><i class="fa-solid fa-exclamation-triangle fa-3x"></i><br><?= gettext('Find Duplicate Emails') ?></a>
            <a href="<?= SystemURLs::getRootPath()?>/v2/email/missing" class="btn btn-app bg-danger"><i class="fa-solid fa-bell-slash fa-3x"></i><br><?= gettext('Families Without Emails') ?></a>
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
            <a href="<?= SystemURLs::getRootPath()?>/admin/system/debug/email" class="btn btn-app bg-secondary"><i class="fa-solid fa-stethoscope fa-3x"></i><br><?= gettext('Debug') ?></a>
            <?php } ?>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
