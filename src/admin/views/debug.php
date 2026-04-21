<?php

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\VersionUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$integrityStatus = AppIntegrityService::getIntegrityCheckStatus();
$integrityPassed = $integrityStatus === gettext('Passed');
$failing = AppIntegrityService::getFilesFailingIntegrityCheck();
$failingCount = count($failing);

$orphanedFiles = AppIntegrityService::getOrphanedFiles();
$orphanedCount = count($orphanedFiles);

$localeInfo = AppIntegrityService::getLocaleSetupInfo();
$localeDetected = $localeInfo['systemLocaleDetected'];

$serverTimezone = date_default_timezone_get();
$configuredTimezone = SystemConfig::getValue('sTimeZone');
$currentServerTime = new DateTime('now', new DateTimeZone($serverTimezone));
$serverConfigMismatch = !empty($configuredTimezone) && $configuredTimezone !== $serverTimezone;

// Count prerequisite failures so we can surface the state in the card header
// and in the summary banner without the user having to expand the card.
$appPrereqs = AppIntegrityService::getApplicationPrerequisites();
$fsPrereqs = AppIntegrityService::getFilesystemPrerequisites();
$prereqFailingCount = 0;
foreach (array_merge($appPrereqs, $fsPrereqs) as $p) {
    if ($p->getStatusText() !== gettext('Passed')) {
        $prereqFailingCount++;
    }
}

// Build the summary banner. Each chip: label, color, count-or-null, anchor to
// scroll to the matching card. Orphaned files are only included when present.
$statusChips = [
    [
        'label' => gettext('Integrity'),
        'ok' => $integrityPassed,
        'count' => $integrityPassed ? null : $failingCount,
        'target' => null, // Integrity card is always visible, no collapse
    ],
    [
        'label' => gettext('Prerequisites'),
        'ok' => $prereqFailingCount === 0,
        'count' => $prereqFailingCount === 0 ? null : $prereqFailingCount,
        'target' => '#collapsePrerequisites',
    ],
    [
        'label' => gettext('Locale'),
        'ok' => $localeDetected,
        'count' => null,
        'target' => '#collapseLocaleSupport',
    ],
    [
        'label' => gettext('Timezone'),
        'ok' => !$serverConfigMismatch,
        'count' => null,
        'target' => '#collapseTimezone',
    ],
];
if ($orphanedCount > 0) {
    $statusChips[] = [
        'label' => gettext('Orphaned Files'),
        'ok' => false,
        'count' => $orphanedCount,
        'target' => null, // Orphaned card is always visible when present
    ];
}
$hasAnyIssue = $integrityPassed === false
    || $prereqFailingCount > 0
    || $localeDetected === false
    || $serverConfigMismatch
    || $orphanedCount > 0;
?>

<!-- Status banner: quick-scan summary of every diagnostic on this page. -->
<div id="debug-status-banner" class="card mb-3 <?= $hasAnyIssue ? 'border-warning' : 'border-success' ?>">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <strong id="debug-status-banner-headline" class="me-2">
                <?php if ($hasAnyIssue): ?>
                    <i class="fa fa-triangle-exclamation text-warning me-1"></i><?= gettext('Issues detected') ?>
                <?php else: ?>
                    <i class="fa fa-circle-check text-success me-1"></i><?= gettext('All checks passing') ?>
                <?php endif; ?>
            </strong>
            <?php foreach ($statusChips as $chip):
                $chipClass = $chip['ok'] ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning';
                $chipIcon = $chip['ok'] ? 'fa-check' : 'fa-triangle-exclamation';
                $chipInner = '<i class="fa ' . $chipIcon . ' me-1"></i>' . InputUtils::escapeHTML($chip['label']);
                if ($chip['count'] !== null) {
                    $chipInner .= ' <span class="ms-1">' . (int) $chip['count'] . '</span>';
                }
                if (!empty($chip['target'])) {
                    ?>
                    <a href="<?= $chip['target'] ?>" class="badge <?= $chipClass ?> text-decoration-none debug-status-chip"><?= $chipInner ?></a>
                <?php } else { ?>
                    <span class="badge <?= $chipClass ?>"><?= $chipInner ?></span>
                <?php }
            endforeach; ?>
        </div>
    </div>
</div>

<!-- Masonry-style layout: cards flow top-to-bottom inside columns so a tall
     expanded card doesn't leave dead space next to collapsed siblings. -->
<div class="debug-grid">
    <!-- Environment card (App / Server / Database / PHP / Web Server tabs).
         This used to be 4 separate cards — consolidated so the reference
         data sits in one place and the Database version isn't duplicated. -->
    <?php
    // Pre-compute paths so the repeated copy-button markup stays readable.
    $envPaths = [
        gettext('Root Path')    => SystemURLs::getRootPath() ?: '(empty - top level)',
        gettext('Document Root') => SystemURLs::getDocumentRoot(),
        gettext('Base URL')     => SystemURLs::getURL(),
        gettext('Images Root')  => SystemURLs::getImagesRoot(),
        gettext('DSN')          => Bootstrapper::getDSN(),
    ];
    $phpIni = [
        gettext('Max file upload size') => ini_get('upload_max_filesize'),
        gettext('Max POST size')        => ini_get('post_max_size'),
        gettext('PHP Memory Limit')     => ini_get('memory_limit'),
        gettext('PHP Max Execution Time') => ini_get('max_execution_time') . 's',
        gettext('SAPI Name')            => php_sapi_name(),
    ];
    ?>
    <div class="card">
        <div class="card-header" id="headingEnvironment">
            <h4 data-bs-toggle="collapse" data-bs-target="#collapseEnvironment" aria-expanded="false" aria-controls="collapseEnvironment" style="cursor: pointer;">
                <i class="fa fa-server me-2"></i><?= gettext('Environment') ?>
                <i class="fa fa-chevron-down float-end"></i>
            </h4>
        </div>
        <div id="collapseEnvironment" class="collapse" aria-labelledby="headingEnvironment">
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation"><a class="nav-link active" id="env-app-tab" data-bs-toggle="tab" href="#env-app" role="tab"><?= gettext('App') ?></a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link" id="env-server-tab" data-bs-toggle="tab" href="#env-server" role="tab"><?= gettext('Server') ?></a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link" id="env-db-tab" data-bs-toggle="tab" href="#env-db" role="tab"><?= gettext('Database') ?></a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link" id="env-php-tab" data-bs-toggle="tab" href="#env-php" role="tab"><?= gettext('PHP') ?></a></li>
                    <li class="nav-item" role="presentation"><a class="nav-link" id="env-web-tab" data-bs-toggle="tab" href="#env-web" role="tab"><?= gettext('Web Server') ?></a></li>
                </ul>
                <div class="tab-content pt-3">
                    <!-- App -->
                    <div class="tab-pane fade show active" id="env-app" role="tabpanel">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><?= gettext('Software Version') ?></td>
                                <td><?= VersionUtils::getInstalledVersion() ?></td>
                            </tr>
                            <?php foreach ($envPaths as $label => $value) { ?>
                                <tr>
                                    <td><?= InputUtils::escapeHTML($label) ?></td>
                                    <td>
                                        <code class="text-monospace" style="word-break:break-all; font-size: 0.85rem;"><?= InputUtils::escapeHTML((string) $value) ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2 copy-btn" data-copy="<?= InputUtils::escapeAttribute((string) $value) ?>"><?= gettext('Copy') ?></button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </table>
                    </div>
                    <!-- Server -->
                    <div class="tab-pane fade" id="env-server" role="tabpanel">
                        <table class="table table-sm mb-0">
                            <tr><td><?= gettext('Hostname') ?></td><td><?= gethostname() ?></td></tr>
                            <tr><td><?= gettext('IP Address') ?></td><td><?= $_SERVER['SERVER_ADDR'] ?? '' ?></td></tr>
                            <tr><td><?= gettext('Platform') ?></td><td><?= php_uname() ?></td></tr>
                            <tr><td><?= gettext('Software') ?></td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? '' ?></td></tr>
                        </table>
                    </div>
                    <!-- Database -->
                    <div class="tab-pane fade" id="env-db" role="tabpanel">
                        <table class="table table-sm mb-0">
                            <tr><td><?= gettext('Schema Version') ?></td><td><?= VersionUtils::getDBVersion() ?></td></tr>
                            <tr><td><?= gettext('Server Version') ?></td><td><?= SystemService::getDBServerVersion() ?></td></tr>
                        </table>
                    </div>
                    <!-- PHP -->
                    <div class="tab-pane fade" id="env-php" role="tabpanel">
                        <table class="table table-sm mb-0">
                            <tr><td><?= gettext('PHP Version') ?></td><td><?= PHP_VERSION ?></td></tr>
                            <?php foreach ($phpIni as $label => $value) { ?>
                                <tr><td><?= InputUtils::escapeHTML($label) ?></td><td><?= InputUtils::escapeHTML((string) $value) ?></td></tr>
                            <?php } ?>
                        </table>
                    </div>
                    <!-- Web Server -->
                    <div class="tab-pane fade" id="env-web" role="tabpanel">
                        <table class="table table-sm mb-0">
                            <tr><td colspan="2"><strong><?= $_SERVER['SERVER_SOFTWARE'] ?? '' ?></strong></td></tr>
                            <?php if (function_exists('apache_get_modules')) {
                                foreach (apache_get_modules() as $module) {
                                    echo '<tr><td>' . InputUtils::escapeHTML($module) . '</td></tr>';
                                }
                            } else { ?>
                                <tr><td class="text-muted"><?= gettext('Unable to list Web Server modules.') ?></td></tr>
                            <?php } ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Application Integrity Check -->
    <div class="card <?= $integrityPassed ? '' : 'border-warning' ?>">
            <div class="card-status-top <?= $integrityPassed ? 'bg-success' : 'bg-warning' ?>"></div>
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fa fa-shield-alt me-2"></i><?= gettext('Application Integrity') ?>
                    <?php if (!$integrityPassed): ?>
                        <span class="badge bg-warning text-dark ms-2"><?= $failingCount ?></span>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if ($integrityPassed): ?>
                    <p><i class="fa fa-circle-check text-success me-2"></i><?= gettext('All system files have passed integrity validation.') ?></p>
                    <p class="text-muted small mb-0"><?= gettext('File signatures match the official release.') ?></p>
                <?php else: ?>
                    <p><?= sprintf(gettext('%d files have failed integrity validation.'), $failingCount) ?></p>
                    <p class="text-muted small"><?= gettext('Files may be modified or missing. Consider re-deploying from an official release.') ?></p>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="btn btn-warning w-100">
                        <i class="fa fa-cloud-upload-alt me-2"></i><?= gettext('System Upgrade') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($orphanedCount > 0): ?>
    <div class="card border-danger">
        <div class="card-status-top bg-danger"></div>
        <div class="card-header">
            <h4 class="mb-0">
                <i class="fa fa-triangle-exclamation me-2"></i><?= gettext('Orphaned Files') ?>
                <span class="badge bg-danger text-white ms-2"><?= $orphanedCount ?></span>
            </h4>
        </div>
        <div class="card-body">
            <p><?= sprintf(gettext('%d orphaned files were detected on your server.'), $orphanedCount) ?></p>
            <p class="text-muted small"><?= gettext('These files are not part of the official release and may pose security risks.') ?></p>
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/orphaned-files" class="btn btn-danger w-100">
                <i class="fa fa-trash me-2"></i><?= gettext('Manage Orphaned Files') ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    <div class="card <?= $prereqFailingCount === 0 ? '' : 'border-warning' ?>">
            <div class="card-status-top <?= $prereqFailingCount === 0 ? 'bg-success' : 'bg-warning' ?>"></div>
            <div class="card-header" id="headingPrerequisites">
                <h4 data-bs-toggle="collapse" data-bs-target="#collapsePrerequisites" aria-expanded="false" aria-controls="collapsePrerequisites" style="cursor: pointer;">
                    <i class="fa fa-circle-check me-2"></i><?= gettext('Application Prerequisites') ?>
                    <?php if ($prereqFailingCount > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?= $prereqFailingCount ?></span>
                    <?php endif; ?>
                    <i class="fa fa-chevron-down float-end"></i>
                </h4>
            </div>
            <div id="collapsePrerequisites" class="collapse" aria-labelledby="collapsePrerequisites">
                <div class="card-body">
                <h6 class="text-muted"><?= gettext('PHP & Server Requirements') ?></h6>
                <table class="table table-sm">
                    <?php foreach ($appPrereqs as $prerequisite) {
                        $status = $prerequisite->getStatusText();
                        $isOk = $status === gettext('Passed');
                        $iconClass = $isOk ? 'fa-check text-success' : 'fa-times text-danger';
                    ?>
                        <tr>
                            <td><i class="fa <?= $iconClass ?> me-2"></i><a href='<?= $prerequisite->getWikiLink() ?>' target="_blank" rel="noopener noreferrer"><?= $prerequisite->getName() ?></a></td>
                        </tr>
                    <?php } ?>
                </table>
                <hr>
                <h6 class="text-muted"><?= gettext('Filesystem Permissions') ?></h6>
                <table class="table table-sm">
                    <?php foreach ($fsPrereqs as $prerequisite) {
                        $status = $prerequisite->getStatusText();
                        $isOk = $status === gettext('Passed');
                        $iconClass = $isOk ? 'fa-check text-success' : 'fa-times text-danger';
                    ?>
                        <tr>
                            <td><i class="fa <?= $iconClass ?> me-2"></i><?= $prerequisite->getName() ?></td>
                        </tr>
                    <?php } ?>
                </table>
                </div>
            </div>
        </div>
    <!-- Locale Support -->
    <div class="card <?= $localeDetected ? '' : 'border-warning' ?>">
            <div class="card-status-top <?= $localeDetected ? 'bg-success' : 'bg-warning' ?>"></div>
            <div class="card-header" id="headingLocaleSupport">
                <h4 data-bs-toggle="collapse" data-bs-target="#collapseLocaleSupport" aria-expanded="false" aria-controls="collapseLocaleSupport" style="cursor: pointer;" class="mb-0">
                    <i class="fa fa-globe me-2"></i><?= gettext('Locale Support') ?>
                    <i class="fa fa-chevron-down float-end"></i>
                </h4>
            </div>
            <div id="collapseLocaleSupport" class="collapse" aria-labelledby="headingLocaleSupport">
            <div class="card-body">
                <div class="alert <?= $localeInfo['systemLocaleDetected'] ? 'alert-success' : 'alert-warning' ?> mb-3">
                    <i class="fa <?= $localeInfo['systemLocaleDetected'] ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i>
                    <strong><?= gettext('System Locale Support') ?></strong><br>
                    <small><?= InputUtils::escapeHTML($localeInfo['systemLocaleSupportSummary']) ?></small>
                </div>
                <h6 class="text-muted mb-3"><i class="fa fa-language me-2"></i><?= gettext('ChurchCRM Supported Locales') ?></h6>
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
                                            <span class="badge bg-green-lt text-green"><i class="fa fa-check me-1"></i><?= gettext('Yes') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark"><i class="fa fa-times me-1"></i><?= gettext('No') ?></span>
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
    <!-- Email — extracted from the old "System & Configuration" card so the
         SMTP status is discoverable at a glance and the /admin/system/debug/email
         page has an inbound entry point. -->
    <?php $mailOk = SystemConfig::hasValidMailServerSettings(); ?>
    <div class="card <?= $mailOk ? '' : 'border-warning' ?>">
        <div class="card-status-top <?= $mailOk ? 'bg-success' : 'bg-warning' ?>"></div>
        <div class="card-header">
            <h4 class="mb-0">
                <i class="fa fa-envelope me-2"></i><?= gettext('Email') ?>
                <?php if (!$mailOk): ?>
                    <span class="badge bg-warning text-dark ms-2"><?= gettext('Misconfigured') ?></span>
                <?php endif; ?>
            </h4>
        </div>
        <div class="card-body">
            <table class="table table-sm mb-3">
                <tr>
                    <td><?= gettext('SMTP Host') ?></td>
                    <td><?= SystemConfig::getValueForHtml('sSMTPHost') ?: gettext('Not configured') ?></td>
                </tr>
                <tr>
                    <td><?= gettext('Valid Settings') ?></td>
                    <td>
                        <?php if ($mailOk): ?>
                            <i class="fa fa-check text-success me-2"></i><span class="text-success"><?= gettext('Yes') ?></span>
                        <?php else: ?>
                            <i class="fa fa-times text-danger me-2"></i><span class="text-danger"><?= gettext('No') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug/email" class="btn <?= $mailOk ? 'btn-outline-primary' : 'btn-warning' ?> w-100">
                <i class="fa fa-envelope me-2"></i><?= gettext('Email Debug') ?>
            </a>
        </div>
    </div>
    <!-- Timezone Information -->
    <div class="card <?= $serverConfigMismatch ? 'border-warning' : '' ?>">
            <div class="card-status-top <?= $serverConfigMismatch ? 'bg-warning' : 'bg-success' ?>"></div>
            <div class="card-header" id="headingTimezone">
                <h4 data-bs-toggle="collapse" data-bs-target="#collapseTimezone" aria-expanded="false" aria-controls="collapseTimezone" style="cursor: pointer;">
                    <i class="fa fa-clock me-2"></i><?= gettext('Timezone Information') ?>
                    <?php if ($serverConfigMismatch): ?>
                        <i class="fa fa-triangle-exclamation text-warning ms-2" id="tz-header-alert" title="<?= gettext('Timezone mismatch detected') ?>"></i>
                    <?php else: ?>
                        <i class="fa fa-triangle-exclamation text-warning ms-2 d-none" id="tz-header-alert" title="<?= gettext('Timezone mismatch detected') ?>"></i>
                    <?php endif; ?>
                    <i class="fa fa-chevron-down float-end"></i>
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
                            <span class="badge bg-primary-lt text-primary"><?= gettext('Baseline') ?></span>
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
                                <span class="badge bg-warning-lt text-warning" title="<?= gettext('Does not match system config') ?>">
                                    <i class="fa fa-triangle-exclamation me-1"></i><?= gettext('Mismatch') ?>
                                </span>
                            <?php elseif (!empty($configuredTimezone)): ?>
                                <span class="badge bg-green-lt text-green"><i class="fa fa-check"></i></span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark"><?= gettext('Default') ?></span>
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
                        <small class="text-muted"><i class="fa fa-spinner fa-spin me-1"></i><?= gettext('Comparing timezones...') ?></small>
                    </div>
                </div>
            </div>
    </div>
    <!-- PHP Configuration and Web Server standalone cards were merged into
         the Environment card's PHP and Web Server tabs above. -->
</div>

<style nonce="<?= SystemURLs::getCSPNonce() ?>">
.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

/* CSS columns (masonry-like) so a tall expanded card doesn't leave dead
   space beside short collapsed cards the way Bootstrap's row-grid does. */
.debug-grid {
    column-gap: 1rem;
}
.debug-grid > .card {
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
    page-break-inside: avoid;
    display: inline-block;
    width: 100%;
    margin-bottom: 1rem;
}
@media (min-width: 768px) {
    .debug-grid { column-count: 2; }
}
@media (min-width: 1200px) {
    .debug-grid { column-count: 3; }
}

/* Status-banner chips — give a little more room than a plain Bootstrap
   badge so the icon + label + count don't look cramped. */
.debug-status-chip,
.card-body .badge.bg-success-lt,
.card-body .badge.bg-warning-lt {
    padding: 0.35em 0.6em;
    font-size: 0.8125rem;
}
</style>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
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
        
        // The top banner's Timezone chip is server-rendered and can't know
        // about the browser mismatch until this runs. Sync it now so the
        // banner state matches the card content.
        var $timezoneChip = $('.debug-status-chip[href="#collapseTimezone"]');
        var $bannerHeadline = $('#debug-status-banner-headline');
        var $banner = $('#debug-status-banner');
        var setChipState = function($chip, ok) {
            if (!$chip.length) return;
            var iconFrom = ok ? 'fa-triangle-exclamation' : 'fa-check';
            var iconTo = ok ? 'fa-check' : 'fa-triangle-exclamation';
            $chip.removeClass('bg-success-lt text-success bg-warning-lt text-warning')
                 .addClass(ok ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning');
            $chip.find('i.fa').removeClass(iconFrom).addClass(iconTo);
        };

        if (browserMatchesBaseline) {
            $badge.removeClass('bg-warning-lt text-warning bg-secondary text-white')
                  .addClass('bg-success-lt text-success')
                  .html('<i class="fa fa-check"></i>');
            $row.removeClass('bg-warning-light');
        } else {
            $badge.removeClass('bg-success-lt text-success bg-secondary text-white')
                  .addClass('bg-warning-lt text-warning')
                  .html('<i class="fa fa-triangle-exclamation me-1"></i><?= gettext('Mismatch') ?>');
            $row.addClass('bg-warning-light');
            // Show alert icon in card header
            $headerAlert.removeClass('d-none');
            // Update banner: flip the Timezone chip and promote headline if
            // this is the first issue detected on the page.
            setChipState($timezoneChip, false);
            $('#collapseTimezone').closest('.card')
                .removeClass('border-success')
                .addClass('border-warning')
                .find('.card-status-top').removeClass('bg-success').addClass('bg-warning');
            if ($bannerHeadline.length) {
                $bannerHeadline.html('<i class="fa fa-triangle-exclamation text-warning me-1"></i><?= gettext('Issues detected') ?>');
                $banner.removeClass('border-success').addClass('border-warning');
            }
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
            summaryHtml = '<span class="text-success"><i class="fa fa-circle-check me-1"></i><?= gettext('All timezones match') ?></span>';
        } else {
            summaryHtml = '<span class="text-warning"><i class="fa fa-triangle-exclamation me-1"></i>' + 
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

        // Keep the trailing chevron in sync with each card's collapse state.
        // Scope the selector to just the chevron element — the old `i.fa`
        // match was rewriting the leading card icon AND the timezone warning
        // triangle, producing stray double chevrons.
        var syncChevron = function(headingEl) {
            var $heading = $(headingEl);
            var $icon = $heading.find('i.fa-chevron-down, i.fa-chevron-up');
            if ($heading.attr('aria-expanded') === 'true') {
                $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            } else {
                $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            }
        };

        // Bootstrap updates aria-expanded on the trigger AFTER the click
        // handler runs, so listen to the collapse events on the pane itself.
        $('.card .collapse').on('shown.bs.collapse hidden.bs.collapse', function() {
            var $trigger = $('[data-bs-target="#' + this.id + '"]');
            $trigger.each(function() { syncChevron(this); });
        });

        // Initial render — catches any card that starts expanded via the hash.
        $('.card-header h4[data-bs-toggle="collapse"]').each(function() {
            syncChevron(this);
        });

        // Deep-link support: /admin/system/debug#collapseTimezone should open
        // the matching section. Bootstrap 5 does not do this out of the box.
        var openFromHash = function() {
            if (!window.location.hash) return;
            var target = document.querySelector(window.location.hash);
            if (!target || !target.classList.contains('collapse')) return;
            if (target.classList.contains('show')) return;
            if (window.bootstrap && bootstrap.Collapse) {
                bootstrap.Collapse.getOrCreateInstance(target).show();
            } else {
                // jQuery fallback
                $(target).collapse('show');
            }
            // Scroll the card into view after expansion animates.
            setTimeout(function() {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 300);
        };
        openFromHash();
        window.addEventListener('hashchange', openFromHash);

        // Auto-expand any collapsible card that's in a warning/danger state so
        // the admin doesn't have to click to find the problem. The chips in
        // the summary banner serve as the quick-scan navigation; expanding
        // just surfaces the details inline.
        var autoOpenProblemCards = function() {
            $('.card .collapse').each(function() {
                if (this.classList.contains('show')) return;
                var $card = $(this).closest('.card');
                if (!$card.hasClass('border-warning') && !$card.hasClass('border-danger')) return;
                if (window.bootstrap && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(this).show();
                } else {
                    $(this).collapse('show');
                }
            });
        };
        autoOpenProblemCards();
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
