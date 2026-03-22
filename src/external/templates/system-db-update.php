<?php
/**
 * Template for System DB Version Mismatch
 *
 * Shown when:
 *  1. The DB version is newer than the installed software (downgrade detected)
 *  2. An automatic database upgrade failed (error from Bootstrapper)
 */
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Version Mismatch');
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>

<div class="container" style="max-width:720px; margin-top:48px;">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="me-3"><i class="fa-solid fa-triangle-exclamation fa-2x text-danger"></i></div>
                <div>
                    <?php if (!empty($errorMessage)) { ?>
                        <h3 class="mb-1"><?= gettext('Database Upgrade Failed') ?></h3>
                        <p class="text-muted mb-2"><?= gettext('An automatic database upgrade was attempted but failed. Please review the error below and contact your system administrator.') ?></p>

                        <ul class="list-unstyled small text-muted">
                            <li><strong><?= gettext('Current DB Version') ?>:</strong> <?= InputUtils::escapeHTML($dbVersion) ?></li>
                            <li><strong><?= gettext('Current Software Version') ?>:</strong> <?= InputUtils::escapeHTML($softwareVersion) ?></li>
                        </ul>

                        <div class="alert alert-danger mt-3" role="alert">
                            <i class="fa-solid fa-triangle-exclamation fa-fw"></i>
                            <?= InputUtils::escapeHTML($errorMessage) ?>
                        </div>
                    <?php } else { ?>
                        <h3 class="mb-1"><?= gettext('Software Update Required') ?></h3>
                        <p class="text-muted mb-2"><?= gettext('Your database version is newer than the installed software. This usually means the software was rolled back to an older version. Please upgrade ChurchCRM to match your database.') ?></p>

                        <ul class="list-unstyled small text-muted">
                            <li><strong><?= gettext('Current DB Version') ?>:</strong> <?= InputUtils::escapeHTML($dbVersion) ?></li>
                            <li><strong><?= gettext('Installed Software Version') ?>:</strong> <?= InputUtils::escapeHTML($softwareVersion) ?></li>
                        </ul>

                        <div class="alert alert-warning mt-3" role="alert">
                            <i class="fa-solid fa-info-circle fa-fw"></i>
                            <?= gettext('Please upgrade ChurchCRM to version') ?> <strong><?= InputUtils::escapeHTML($dbVersion) ?></strong> <?= gettext('or later to continue.') ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
