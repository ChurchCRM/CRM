<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\VersionUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
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
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= SystemURLs::getRootPath() ?: '' ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Document Root') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getDocumentRoot() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= SystemURLs::getDocumentRoot() ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Base URL') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getURL() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= SystemURLs::getURL() ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Images Root') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= SystemURLs::getImagesRoot() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= SystemURLs::getImagesRoot() ?>"><?= gettext('Copy') ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('DSN') ?></td>
                        <td>
                            <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= Bootstrapper::getDSN() ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 copy-btn" data-copy="<?= Bootstrapper::getDSN() ?>"><?= gettext('Copy') ?></button>
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
    <!-- Locale Support -->
    <?php 
    $localeInfo = AppIntegrityService::getLocaleSetupInfo();
    $localeDetected = $localeInfo['systemLocaleDetected'];
    ?>
    <div class="col-md-4">
        <div class="card <?= $localeDetected ? '' : 'border-warning' ?>">
            <div class="card-header <?= $localeDetected ? 'bg-success' : 'bg-warning' ?> text-white" id="headingLocaleSupport">
                <h4 data-toggle="collapse" data-target="#collapseLocaleSupport" aria-expanded="false" aria-controls="collapseLocaleSupport" style="cursor: pointer;" class="mb-0">
                    <i class="fa fa-globe mr-2"></i><?= gettext('Locale Support') ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapseLocaleSupport" class="collapse" aria-labelledby="headingLocaleSupport">
            <div class="card-body">
                <div class="alert <?= $localeInfo['systemLocaleDetected'] ? 'alert-success' : 'alert-warning' ?> mb-3">
                    <i class="fa <?= $localeInfo['systemLocaleDetected'] ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
                    <strong><?= gettext('System Locale Support') ?></strong><br>
                    <small><?= InputUtils::escapeHTML($localeInfo['systemLocaleSupportSummary']) ?></small>
                </div>
                <h6 class="text-muted mb-3"><i class="fa fa-language mr-2"></i><?= gettext('ChurchCRM Supported Locales') ?></h6>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th><?= gettext('Language') ?></th>
                            <th style="text-align: center; width: 100px;"><?= gettext('System') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($localeInfo['supportedLocales']) > 0): ?>
                            <?php foreach ($localeInfo['supportedLocales'] as $locale): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500; font-size: 0.95em;"><?= InputUtils::escapeHTML($locale['name']) ?></div>
                                        <small class="text-muted" style="font-size: 0.85em;"><?= InputUtils::escapeHTML($locale['locale']) ?></small>
                                    </td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <?php if ($locale['systemAvailable']): ?>
                                            <span class="badge badge-success"><i class="fa fa-check mr-1"></i><?= gettext('Yes') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><i class="fa fa-times mr-1"></i><?= gettext('No') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="text-muted text-center"><?= gettext('No locales configured') ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
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
                        <td>
                            <?php
                            $smtpHost = SystemConfig::getBooleanValue('plugin.smtp.enabled')
                                ? SystemConfig::getValue('plugin.smtp.host')
                                : SystemConfig::getValue('sSMTPHost');
                            echo htmlspecialchars($smtpHost ?: gettext('Not configured'));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?= gettext('Valid Settings') ?></td>
                        <td>
                            <?php if (SystemConfig::hasValidMailServerSettings()): ?>
                                <i class="fa fa-check text-success mr-2"></i><span class="text-success"><?= gettext('Yes') ?></span>
                                <a href="<?= SystemURLs::getRootPath() ?>/plugins/management#plugin-smtp" class="btn btn-sm btn-outline-primary ml-2" title="<?= gettext('SMTP Plugin Settings') ?>">
                                    <i class="fa fa-envelope mr-1"></i><?= gettext('SMTP Settings') ?>
                                </a>
                            <?php else: ?>
                                <i class="fa fa-times text-danger mr-2"></i><span class="text-danger"><?= gettext('No') ?></span>
                                <a href="<?= SystemURLs::getRootPath() ?>/plugins/management#plugin-smtp" class="btn btn-sm btn-outline-danger ml-2" title="<?= gettext('SMTP Plugin Settings') ?>">
                                    <i class="fa fa-envelope mr-1"></i><?= gettext('SMTP Settings') ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Timezone Information -->
    <?php
    $serverTimezone = date_default_timezone_get();
    $configuredTimezone = SystemConfig::getValue('sTimeZone');
    $currentServerTime = new DateTime('now', new DateTimeZone($serverTimezone));
    $serverConfigMismatch = !empty($configuredTimezone) && $configuredTimezone !== $serverTimezone;
    ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" id="headingTimezone">
                <h4 data-toggle="collapse" data-target="#collapseTimezone" aria-expanded="false" aria-controls="collapseTimezone" style="cursor: pointer;">
                    <i class="fa fa-clock mr-2"></i><?= gettext('Timezone Information') ?>
                    <?php if ($serverConfigMismatch): ?>
                        <i class="fa fa-exclamation-triangle text-warning ml-2" id="tz-header-alert" title="<?= gettext('Timezone mismatch detected') ?>"></i>
                    <?php else: ?>
                        <i class="fa fa-exclamation-triangle text-warning ml-2 d-none" id="tz-header-alert" title="<?= gettext('Timezone mismatch detected') ?>"></i>
                    <?php endif; ?>
                    <i class="fa fa-chevron-down float-right"></i>
                </h4>
            </div>
            <div id="collapseTimezone" class="collapse" aria-labelledby="headingTimezone">
                <div class="card-body p-0">
                    <!-- System Config (Baseline) -->
                    <div class="p-3 bg-light border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block"><?= gettext('System Config') ?> (sTimeZone)</small>
                                <strong class="h6 mb-0"><?= InputUtils::escapeHTML($configuredTimezone ?: gettext('Not set')) ?></strong>
                            </div>
                            <span class="badge badge-primary"><?= gettext('Baseline') ?></span>
                        </div>
                    </div>
                    
                    <!-- PHP Active -->
                    <div class="p-3 border-bottom <?= $serverConfigMismatch ? 'bg-warning-light' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block"><?= gettext('PHP Active') ?></small>
                                <strong class="mb-0"><?= InputUtils::escapeHTML($serverTimezone) ?></strong>
                                <small class="text-muted d-block"><?= InputUtils::escapeHTML($currentServerTime->format('Y-m-d H:i:s T')) ?></small>
                            </div>
                            <?php if ($serverConfigMismatch): ?>
                                <span class="badge badge-warning" title="<?= gettext('Does not match system config') ?>">
                                    <i class="fa fa-exclamation-triangle"></i> <?= gettext('Mismatch') ?>
                                </span>
                            <?php elseif (!empty($configuredTimezone)): ?>
                                <span class="badge badge-success"><i class="fa fa-check"></i></span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?= gettext('Default') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Browser -->
                    <div class="p-3 border-bottom" id="browser-tz-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block"><?= gettext('Browser') ?></small>
                                <strong class="mb-0" id="browser-timezone"><?= gettext('Loading...') ?></strong>
                                <small class="text-muted d-block" id="browser-time"><?= gettext('Loading...') ?></small>
                            </div>
                            <span class="badge" id="browser-tz-badge"><?= gettext('Loading...') ?></span>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="p-3" id="timezone-summary">
                        <small class="text-muted"><i class="fa fa-spinner fa-spin mr-1"></i><?= gettext('Comparing timezones...') ?></small>
                    </div>
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

<style nonce="<?= SystemURLs::getCSPNonce() ?>">
.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1) !important;
}
</style>

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
        // Populate browser timezone information with guard for older browsers
        var browserTimezone;
        try {
            browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '<?= gettext('Unknown') ?>';
        } catch (e) {
            browserTimezone = '<?= gettext('Unknown') ?>';
        }
        var now = new Date();
        var browserOffset = -now.getTimezoneOffset();
        var offsetHours = Math.floor(Math.abs(browserOffset) / 60);
        var offsetMinutes = Math.abs(browserOffset) % 60;
        var offsetSign = browserOffset >= 0 ? '+' : '-';
        var offsetString = 'UTC' + offsetSign + String(offsetHours).padStart(2, '0') + ':' + String(offsetMinutes).padStart(2, '0');
        
        var browserTimeString = now.getFullYear() + '-' + 
            String(now.getMonth() + 1).padStart(2, '0') + '-' + 
            String(now.getDate()).padStart(2, '0') + ' ' +
            String(now.getHours()).padStart(2, '0') + ':' + 
            String(now.getMinutes()).padStart(2, '0') + ':' + 
            String(now.getSeconds()).padStart(2, '0');
        
        // Update browser timezone display - .text() handles escaping
        $('#browser-timezone').text(browserTimezone);
        $('#browser-time').text(browserTimeString + ' (' + offsetString + ')');
        
        // Compare against baseline (configured timezone, or server if not configured)
        var serverTimezone = <?= json_encode($serverTimezone) ?>;
        var configuredTimezone = <?= json_encode($configuredTimezone) ?>;
        var baselineTimezone = configuredTimezone || serverTimezone;
        
        var browserMatchesBaseline = (browserTimezone === baselineTimezone);
        var $badge = $('#browser-tz-badge');
        var $row = $('#browser-tz-row');
        var $headerAlert = $('#tz-header-alert');
        
        if (browserMatchesBaseline) {
            $badge.removeClass('badge-warning badge-secondary').addClass('badge-success')
                  .html('<i class="fa fa-check"></i>');
            $row.removeClass('bg-warning-light');
        } else {
            $badge.removeClass('badge-success badge-secondary').addClass('badge-warning')
                  .html('<i class="fa fa-exclamation-triangle"></i> <?= gettext('Mismatch') ?>');
            $row.addClass('bg-warning-light');
            // Show alert icon in card header
            $headerAlert.removeClass('d-none');
        }
        
        // Update summary
        var serverConfigMismatch = configuredTimezone && (configuredTimezone !== serverTimezone);
        var issueCount = 0;
        if (serverConfigMismatch) issueCount++;
        if (!browserMatchesBaseline) issueCount++;
        
        // Update header alert visibility based on any mismatch
        if (issueCount > 0) {
            $headerAlert.removeClass('d-none');
        }
        
        var summaryHtml = '';
        if (issueCount === 0) {
            summaryHtml = '<span class="text-success"><i class="fa fa-check-circle mr-1"></i><?= gettext('All timezones match') ?></span>';
        } else {
            summaryHtml = '<span class="text-warning"><i class="fa fa-exclamation-triangle mr-1"></i>' + 
                          issueCount + ' ' + (issueCount === 1 ? '<?= gettext('mismatch detected') ?>' : '<?= gettext('mismatches detected') ?>') + '</span>';
            if (!browserMatchesBaseline) {
                summaryHtml += '<br><small class="text-muted"><?= gettext('Browser differs from system config - dates may display incorrectly for this user.') ?></small>';
            }
        }
        $('#timezone-summary').html(summaryHtml);
        
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

    // Initialize page once locales (i18next) are ready, with DOM-ready fallback
    if (window.CRM && typeof window.CRM.onLocalesReady === 'function') {
        window.CRM.onLocalesReady(function() {
            initializeDebugPage();
        });
    } else {
        $(document).ready(function() {
            initializeDebugPage();
        });
    }
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
