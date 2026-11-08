<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Calculate log statistics
$totalLogFiles = count($logFiles ?? []);
$totalLogSize = 0;
if (!empty($logFiles)) {
    foreach ($logFiles as $file) {
        $totalLogSize += $file['size'];
    }
}
$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();

// Get current log level and mapping
$currentLogLevel = SystemConfig::getValue('sLogLevel') ?? '200';
$logLevelMap = [
    '100' => 'DEBUG',
    '200' => 'INFO',
    '250' => 'NOTICE',
    '300' => 'WARNING',
    '400' => 'ERROR',
    '500' => 'CRITICAL',
    '550' => 'ALERT',
    '600' => 'EMERGENCY',
];
$currentLevelLabel = $logLevelMap[$currentLogLevel] ?? 'INFO';
?>
<div class="container-fluid">
    <!-- Stat Cards Row -->
    <div class="row mb-3">
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-secondary text-white avatar rounded-circle">
                                <i class="fa-solid fa-sliders icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium" id="currentLogLevelDisplay"><?= $currentLevelLabel ?></div>
                            <div class="text-body-secondary"><?= gettext('Log Level') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($logFiles)): ?>
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-info text-white avatar rounded-circle">
                                <i class="fa-solid fa-file-lines icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $totalLogFiles ?></div>
                            <div class="text-body-secondary"><?= gettext('Log Files') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar rounded-circle">
                                <i class="fa-solid fa-database icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= number_format($totalLogSize / 1024 / 1024, 2) ?> MB</div>
                            <div class="text-body-secondary"><?= gettext('Total Size') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-danger text-white avatar rounded-circle">
                                <i class="fa-solid fa-trash icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <button class="btn btn-sm btn-danger admin-delete-logs-btn" id="deleteAllLogs">
                                <?= gettext('Delete All') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <!-- Log Files Table -->
    <div class="row mt-2">
        <div class="col-12">
            <?php if (!empty($logFiles)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Log Files') ?></h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-vcenter table-hover card-table" id="logFilesTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Log File') ?></th>
                                <th><?= gettext('Size') ?></th>
                                <th><?= gettext('Last Modified') ?></th>
                                <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logFiles as $logFile): ?>
                                <tr>
                                    <td>
                                        <i class="fa-solid fa-file-lines me-2"></i>
                                        <strong><?= InputUtils::escapeHTML($logFile['name']) ?></strong>
                                    </td>
                                    <td><?= number_format($logFile['size'] / 1024, 2) ?> KB</td>
                                    <td><?= date('Y-m-d H:i:s', $logFile['modified']) ?></td>
                                    <td class="text-center w-1">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item view-log" href="#" data-log-name="<?= InputUtils::escapeHTML($logFile['name']) ?>">
                                                    <i class="fa-solid fa-eye me-2"></i><?= gettext('View') ?>
                                                </a>
                                                <a class="dropdown-item download-log" href="#" data-log-name="<?= InputUtils::escapeHTML($logFile['name']) ?>">
                                                    <i class="fa-solid fa-download me-2"></i><?= gettext('Download') ?>
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger delete-log" href="#" data-log-name="<?= InputUtils::escapeHTML($logFile['name']) ?>">
                                                    <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info m-0">
                <i class="fa-solid fa-circle-info me-2"></i><?= gettext('No log files found.') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Log Viewer Modal -->
<div class="modal fade" id="logViewerModal" tabindex="-1" role="dialog" aria-labelledby="logViewerModalLabel">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="logViewerModalLabel"><?= gettext('Log File Viewer') ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label><?= gettext('Filter by log level') ?>:</label>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary log-filter active" data-level="all"><?= gettext('All') ?></button>
                        <button type="button" class="btn btn-outline-danger log-filter" data-level="ERROR"><?= gettext('Error') ?></button>
                        <button type="button" class="btn btn-outline-warning log-filter" data-level="WARNING"><?= gettext('Warning') ?></button>
                        <button type="button" class="btn btn-outline-info log-filter" data-level="INFO"><?= gettext('Info') ?></button>
                        <button type="button" class="btn btn-outline-secondary log-filter" data-level="DEBUG"><?= gettext('Debug') ?></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label><?= gettext('Number of lines to display') ?>:</label>
                    <select class="form-select form-select-sm w-auto d-inline-block" id="logLinesLimit">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                        <option value="all"><?= gettext('All') ?></option>
                    </select>
                </div>
                <pre id="logContent" class="bg-body-tertiary text-body border admin-log-content"><code></code></pre>
                <div id="logLoading" class="d-none text-center p-3">
                    <i class="fa-solid fa-spinner fa-spin fa-3x"></i>
                    <p><?= gettext('Loading log file...') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">window.CRM.logSettings = <?= InputUtils::jsonEncodeForScript(['tooltip' => SystemConfig::getTooltip('sLogLevel'), 'choices' => SystemConfig::getChoices('sLogLevel')]) ?>;</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-logs.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
