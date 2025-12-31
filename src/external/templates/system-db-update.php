<?php
/**
 * Template for System DB Update
 * Expects $this->errorMessage to be available when included via the controller
 */
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\VersionUtils;

$sPageTitle = gettext('Upgrade Required');
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

<div class="container" style="max-width:720px; margin-top:48px;">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="mr-3"><i class="fa-solid fa-triangle-exclamation fa-2x text-warning"></i></div>
                <div>
                    <h3 class="mb-1"><?= gettext('Upgrade Required') ?></h3>
                    <p class="text-muted mb-2"><?= gettext('Your ChurchCRM installation needs a database upgrade to match the installed software version. This operation will apply schema and data migrations. Please ensure you have a recent backup before proceeding.') ?></p>

                    <ul class="list-unstyled small text-muted">
                        <li><strong><?= gettext('Current DB Version') ?>:</strong> <?= VersionUtils::getDBVersion() ?></li>
                        <li><strong><?= gettext('Current Software Version') ?>:</strong> <?= VersionUtils::getInstalledVersion() ?></li>
                    </ul>

                    <?php if (!empty($successMessage)) { ?>
                        <div class="alert alert-success mt-3" role="alert">
                            <i class="fa-solid fa-check-circle fa-fw"></i>
                            <?= InputUtils::escapeHTML($successMessage) ?>
                        </div>
                        <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                            // Redirect to dashboard after short delay
                            setTimeout(function(){ window.location = "<?= SystemURLs::getRootPath() ?>/v2/dashboard"; }, 2500);
                        </script>
                    <?php } else if (empty($errorMessage)) { ?>
                        <form id="dbUpgradeForm" class="mt-3" method="post" action="<?= SystemURLs::getRootPath() ?>/external/system/db-upgrade">
                            <button type="submit" class="btn btn-primary" id="upgradeDatabase"><i class="fa-solid fa-database"></i> <?= gettext('Upgrade database now') ?></button>
                        </form>
                    <?php } else { ?>
                        <div class="alert alert-danger mt-3" role="alert">
                            <i class="fa-solid fa-triangle-exclamation fa-fw"></i>
                            <?= InputUtils::escapeHTML($errorMessage) ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
