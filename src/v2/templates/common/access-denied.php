<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Map role codes to human-readable descriptions
$roleDescriptions = [
    'Admin' => gettext('Administrator privileges'),
    'Finance' => gettext('Finance access'),
    'ManageGroups' => gettext('Group management access'),
    'EditRecords' => gettext('Edit records permission'),
    'DeleteRecords' => gettext('Delete records permission'),
    'AddRecords' => gettext('Add records permission'),
    'MenuOptions' => gettext('Menu options access'),
    'Notes' => gettext('Notes access'),
    'CreateDirectory' => gettext('Create directory permission'),
    'AddEvent' => gettext('Add event permission'),
    'CSVExport' => gettext('CSV export permission'),
    'Authentication' => gettext('User authentication'),
];

$roleDescription = isset($roleDescriptions[$missingRole]) 
    ? $roleDescriptions[$missingRole] 
    : gettext('Required permission');
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8 col-sm-10">
        <div class="card card-outline card-danger mt-4">
            <div class="card-header text-center">
                <h3 class="card-title mb-0">
                    <i class="fa-solid fa-lock text-danger"></i>
                    <?= gettext('Permission Required') ?>
                </h3>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fa-solid fa-user-lock text-muted" style="font-size: 4rem;"></i>
                </div>
                
                <h4 class="mb-3"><?= gettext("You don't have access to this page") ?></h4>
                
                <p class="text-muted mb-4">
                    <?= gettext('The page you tried to visit requires special permissions that your account does not currently have.') ?>
                </p>

                <?php if (!empty($missingRole)) : ?>
                <div class="callout callout-warning text-left">
                    <h5><i class="fa-solid fa-key"></i> <?= gettext('Required Permission') ?></h5>
                    <p class="mb-0">
                        <strong><?= InputUtils::escapeHTML($roleDescription) ?></strong>
                    </p>
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <p class="text-muted small mb-3">
                        <i class="fa-solid fa-info-circle"></i>
                        <?= gettext('If you need access to this feature, please contact your church administrator.') ?>
                    </p>
                    
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="btn btn-primary btn-lg btn-block">
                        <i class="fa-solid fa-home"></i> <?= gettext('Go to Dashboard') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
