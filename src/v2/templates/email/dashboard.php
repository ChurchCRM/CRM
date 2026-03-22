<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Overview Card -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-envelope"></i> <?= gettext('Overview') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-primary">
                                <div class="stat-icon bg-primary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Email Functions') ?></div>
                            <div class="text-muted small"><?= gettext('Manage email operations') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Functions Card -->
<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= gettext('Email Tools') ?></h3>
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
