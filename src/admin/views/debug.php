<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\VersionUtils;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<?php
$integrityStatus = AppIntegrityService::getIntegrityCheckStatus();
?>
<div class="row">
    <!-- Installation Configuration - First Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" id="headingInstallation">
                <h4 data-toggle="collapse" data-target="#collapseInstallation" aria-expanded="false" aria-controls="collapseInstallation" style="cursor: pointer;">
                    <i class="fa fa-cubes mr-2"></i><?= gettext('ChurchCRM Installation') ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapseInstallation" class="collapse" aria-labelledby="headingInstallation">
                <div class="card-body">
                <table class="table table-striped table-sm">
                    <tr>
                        <td><?= gettext('Software Version') ?></td>
                        <td><?= VersionUtils::getInstalledVersion() ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Database Version') ?></td>
                        <td><?= VersionUtils::getDBVersion() ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Root Path') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getRootPath() ?: '(empty - top level)' ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= htmlspecialchars(SystemURLs::getRootPath() ?: '', ENT_QUOTES) ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Document Root') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getDocumentRoot() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= htmlspecialchars(SystemURLs::getDocumentRoot(), ENT_QUOTES) ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Base URL') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getURL() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= htmlspecialchars(SystemURLs::getURL(), ENT_QUOTES) ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Images Root') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getImagesRoot() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= htmlspecialchars(SystemURLs::getImagesRoot(), ENT_QUOTES) ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('DSN') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= Bootstrapper::getDSN() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= htmlspecialchars(Bootstrapper::getDSN(), ENT_QUOTES) ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Application Integrity Check -->
    <?php
    $failing = AppIntegrityService::getFilesFailingIntegrityCheck();
    $failingCount = count($failing);
    $integrityPassed = $integrityStatus === gettext('Passed');
    ?>
    <div class="col-md-4">
        <div class="card <?= $integrityPassed ? '' : 'border-warning' ?>">
            <div class="card-header <?= $integrityPassed ? 'bg-success' : 'bg-warning' ?> text-white">
                <h4 class="mb-0">
                    <i class="fa fa-shield-alt mr-2"></i><?= gettext('Application Integrity') ?>
                    <?php if (!$integrityPassed): ?>
                        <span class="badge badge-light ml-2"><?= $failingCount ?></span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if ($integrityPassed): ?>
                    <p><i class="fa fa-check-circle text-success mr-2"></i><?= gettext('All system files have passed integrity validation.') ?></p>
                    <p class="text-muted small mb-0"><?= gettext('File signatures match the official release.') ?></p>
                <?php else: ?>
                    <p><?= sprintf(gettext('%d files have failed integrity validation.'), $failingCount) ?></p>
                    <p class="text-muted small"><?= gettext('Files may be modified or missing. Consider re-deploying from an official release.') ?></p>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="btn btn-warning btn-block">
                        <i class="fa fa-cloud-upload-alt mr-2"></i><?= gettext('System Upgrade') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    // Check for orphaned files and show a separate card if found
    $orphanedFiles = AppIntegrityService::getOrphanedFiles();
    $orphanedCount = count($orphanedFiles);
    if ($orphanedCount > 0):
    ?>
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">
                    <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext('Orphaned Files') ?>
                    <span class="badge badge-light ml-2"><?= $orphanedCount ?></span>
                </h4>
            </div>
            <div class="card-body">
                <p><?= sprintf(gettext('%d orphaned files were detected on your server.'), $orphanedCount) ?></p>
                <p class="text-muted small"><?= gettext('These files are not part of the official release and may pose security risks.') ?></p>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/orphaned-files" class="btn btn-danger btn-block">
                    <i class="fa fa-trash mr-2"></i><?= gettext('Manage Orphaned Files') ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" id="headingPrerequisites">
                <h4 data-toggle="collapse" data-target="#collapsePrerequisites" aria-expanded="false" aria-controls="collapsePrerequisites" style="cursor: pointer;">
                    <i class="fa fa-check-circle mr-2"></i><?= gettext('Application Prerequisites') ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapsePrerequisites" class="collapse" aria-labelledby="collapsePrerequisites">
                <div class="card-body">
                <h6 class="text-muted"><?= gettext('PHP & Server Requirements') ?></h6>
                <?php $appPrereqs = AppIntegrityService::getApplicationPrerequisites(); ?>
                <table class="table table-striped table-sm">
                    <?php foreach ($appPrereqs as $prerequisite) { 
                        $status = $prerequisite->getStatusText();
                        $isOk = $status === gettext('Passed');
                        $iconClass = $isOk ? 'fa-check text-success' : 'fa-times text-danger';
                    ?>
                        <tr>
                            <td><i class="fa <?= $iconClass ?> mr-2"></i><a href='<?= $prerequisite->getWikiLink() ?>' target="_blank" rel="noopener noreferrer"><?= $prerequisite->getName() ?></a></td>
                        </tr>
                    <?php } ?>
                </table>
                <hr>
                <h6 class="text-muted"><?= gettext('Filesystem Permissions') ?></h6>
                <?php $fsPrereqs = AppIntegrityService::getFilesystemPrerequisites(); ?>
                <table class="table table-striped table-sm">
                    <?php foreach ($fsPrereqs as $prerequisite) { 
                        $status = $prerequisite->getStatusText();
                        $isOk = $status === gettext('Passed');
                        $iconClass = $isOk ? 'fa-check text-success' : 'fa-times text-danger';
                    ?>
                        <tr>
                            <td><i class="fa <?= $iconClass ?> mr-2"></i><?= $prerequisite->getName() ?></td>
                        </tr>
                    <?php } ?>
                </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Configuration -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" id="headingSystemConfig">
                <h4 data-toggle="collapse" data-target="#collapseSystemConfig" aria-expanded="false" aria-controls="collapseSystemConfig" style="cursor: pointer;">
                    <i class="fa fa-cogs mr-2"></i><?= gettext('System & Configuration') ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapseSystemConfig" class="collapse" aria-labelledby="headingSystemConfig">
                <div class="card-body">
                <h6 class="text-muted mb-2"><?= gettext('Server Information') ?></h6>
                <table class="table table-striped table-sm mb-3">
                    <tr>
                        <td><?= gettext('Hostname') ?></td>
                        <td><?= gethostname() ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext('IP Address') ?></td>
                        <td><?= $_SERVER['SERVER_ADDR'] ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Platform') ?></td>
                        <td><?= php_uname() ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Software') ?></td>
                        <td><?= $_SERVER["SERVER_SOFTWARE"] ?></td>
                    </tr>
                </table>
                <hr>
                <h6 class="text-muted mb-2"><?= gettext('Database Server') ?></h6>
                <table class="table table-striped table-sm mb-3">
                    <tr>
                        <td><?= gettext('Version') ?></td>
                        <td><?= SystemService::getDBServerVersion() ?></td>
                    </tr>
                </table>
                <hr>
                <h6 class="text-muted mb-2"><?= gettext('Email Configuration') ?></h6>
                <table class="table table-striped table-sm">
                    <tr>
                        <td><?= gettext('SMTP Host') ?></td>
                        <td><?= SystemConfig::getValue("sSMTPHost") ?: gettext('Not configured') ?></td>
                    </tr>
                    <tr>
                        <td><?= gettext('Valid Settings') ?></td>
                        <td>
                            <?php if (SystemConfig::hasValidMailServerSettings()): ?>
                                <i class="fa fa-check text-success mr-2"></i><span class="text-success"><?= gettext('Yes') ?></span>
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug/email" class="btn btn-sm btn-outline-primary ml-2" title="<?= gettext('Email Debug Info') ?>">
                                    <i class="fa fa-envelope mr-1"></i><?= gettext('Debug') ?>
                                </a>
                            <?php else: ?>
                                <i class="fa fa-times text-danger mr-2"></i><span class="text-danger"><?= gettext('No') ?></span>
                                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug/email" class="btn btn-sm btn-outline-danger ml-2" title="<?= gettext('Email Debug Info') ?>">
                                    <i class="fa fa-envelope mr-1"></i><?= gettext('Debug') ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" id="headingPHP">
                <h4 data-toggle="collapse" data-target="#collapsePHP" aria-expanded="false" aria-controls="collapsePHP" style="cursor: pointer;">
                    <i class="fa fa-code mr-2"></i><?= gettext('PHP Configuration') ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapsePHP" class="collapse" aria-labelledby="headingPHP">
                <div class="card-body">
                <table class="table table-striped table-sm">
                    <tr>
                        <td>PHP Version</td>
                        <td><?= PHP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td>Max file upload size</td>
                        <td><?= ini_get('upload_max_filesize') ?></td>
                    </tr>
                    <tr>
                        <td>Max POST size</td>
                        <td><?= ini_get('post_max_size') ?></td>
                    </tr>
                    <tr>
                        <td>PHP Memory Limit</td>
                        <td><?= ini_get('memory_limit') ?></td>
                    </tr>
                    <tr>
                        <td>PHP Max Execution Time</td>
                        <td><?= ini_get('max_execution_time') ?>s</td>
                    </tr>
                    <tr>
                        <td>SAPI Name</td>
                        <td><?= php_sapi_name()  ?></td>
                    </tr>
                </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" id="headingWebServer">
                <h4 data-toggle="collapse" data-target="#collapseWebServer" aria-expanded="false" aria-controls="collapseWebServer" style="cursor: pointer;">
                    <i class="fa fa-globe mr-2"></i><?= gettext('Web Server') ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapseWebServer" class="collapse" aria-labelledby="headingWebServer">
                <div class="card-body">
                <table class="table table-striped table-sm">
                    <tr>
                        <td colspan="2"><strong><?= $_SERVER["SERVER_SOFTWARE"] ?></strong></td>
                    </tr>
<?php
if (function_exists('apache_get_modules')) {
    foreach (apache_get_modules() as $module) {
        echo <<<EOD
<tr>
    <td>$module</td>
</tr>
EOD;
    }
} else {
    echo <<<EOD
<tr>
    <td class="text-muted">Unable to list Web Server modules!</td>
</tr>
EOD;
}
?>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    var callback = function() {
        $("#fileIntegrityCheckResultsTable").DataTable({
            responsive: true,
            paging: false,
            searching: false
        });
    };

    if (
        document.readyState === "complete" ||
        (document.readyState !== "loading" && !document.documentElement.doScroll)
    ) {
        callback();
    } else {
        document.addEventListener("DOMContentLoaded", callback);
    }

    var initializeDebugPage = function() {
        $(document).on('click', '.copy-btn', function() {
            var txt = $(this).data('copy');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(function() {
                    window.CRM.notify(i18next.t('Copied to clipboard'), { type: 'success', delay: 2000 });
                }).catch(function() {
                    window.CRM.notify(i18next.t('Copy failed'), { type: 'error', delay: 3000 });
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = txt;
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    window.CRM.notify(i18next.t('Copied to clipboard'), { type: 'success', delay: 2000 });
                } catch (e) {
                    window.CRM.notify(i18next.t('Copy failed'), { type: 'error', delay: 3000 });
                }
                document.body.removeChild(ta);
            }
        });

        // Handle collapse icon toggles
        $('.card-header h4[data-toggle="collapse"]').on('click', function() {
            var icon = $(this).find('i.fa');
            // Small delay to let Bootstrap update aria-expanded
            setTimeout(function() {
                var isExpanded = $(this).attr('aria-expanded') === 'true';
                if (isExpanded) {
                    icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                } else {
                    icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                }
            }.bind(this), 10);
        });

        // Initialize icons for expanded cards
        $('.card-header h4[data-toggle="collapse"]').each(function() {
            var icon = $(this).find('i.fa');
            var isExpanded = $(this).attr('aria-expanded') === 'true';
            if (isExpanded) {
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            } else {
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            }
        });
    };

    if (window.CRM && typeof window.CRM.onLocalesReady === 'function') {
        window.CRM.onLocalesReady(initializeDebugPage);
    }
</script>
<?php
include SystemURLs::getDocumentRoot() . '/Include/Footer.php';
