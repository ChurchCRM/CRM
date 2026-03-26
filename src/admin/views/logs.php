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
        <div class="col-sm-6 col-lg-3">
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
                            <div class="text-muted"><?= gettext('Log Level') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($logFiles)): ?>
        <div class="col-sm-6 col-lg-3">
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
                            <div class="text-muted"><?= gettext('Log Files') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
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
                            <div class="text-muted"><?= gettext('Total Size') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-danger text-white avatar rounded-circle">
                                <i class="fa-solid fa-trash icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <button class="btn btn-sm btn-danger" id="deleteAllLogs" style="width: auto; padding: 0.25rem 0.75rem;">
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
                                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item view-log" href="#" data-log-name="<?= InputUtils::escapeHTML($logFile['name']) ?>">
                                                    <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                                </a>
                                                <a class="dropdown-item download-log" href="#" data-log-name="<?= InputUtils::escapeHTML($logFile['name']) ?>">
                                                    <i class="ti ti-download me-2"></i><?= gettext('Download') ?>
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger delete-log" href="#" data-log-name="<?= InputUtils::escapeHTML($logFile['name']) ?>">
                                                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
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
                    <label><?= gettext('Filter by log level:') ?></label>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary log-filter active" data-level="all"><?= gettext('All') ?></button>
                        <button type="button" class="btn btn-outline-danger log-filter" data-level="ERROR"><?= gettext('Error') ?></button>
                        <button type="button" class="btn btn-outline-warning log-filter" data-level="WARNING"><?= gettext('Warning') ?></button>
                        <button type="button" class="btn btn-outline-info log-filter" data-level="INFO"><?= gettext('Info') ?></button>
                        <button type="button" class="btn btn-outline-secondary log-filter" data-level="DEBUG"><?= gettext('Debug') ?></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label><?= gettext('Number of lines to display:') ?></label>
                    <select class="form-select form-select-sm" id="logLinesLimit" style="width: auto; display: inline-block;">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                        <option value="all"><?= gettext('All') ?></option>
                    </select>
                </div>
                <pre id="logContent" style="max-height: 500px; overflow-y: auto; background-color: #f4f4f4; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; color: #212529; font-family: Menlo, Monaco, Consolas, 'Liberation Mono', monospace;"><code style="color: inherit; white-space: pre-wrap;"></code></pre>
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    var currentLogFile = '';
    var currentLogContent = '';
    var currentFilter = 'all';

    $(document).ready(function() {
        window.CRM.onLocalesReady(function() {
            var dataTableConfig = {
                order: [[2, 'desc']]
            };
            $.extend(dataTableConfig, window.CRM.plugin.dataTable);
            // Override global defaults for this specific table (no search, no paging needed)
            dataTableConfig.paging = false;
            dataTableConfig.info = false;
            dataTableConfig.searching = false;
            dataTableConfig.layout = { topEnd: 'buttons' };
            dataTableConfig.columnDefs = [
                { orderable: false, targets: 3 }  // Actions column
            ];
            var table = $('#logFilesTable').DataTable(dataTableConfig);

            $(document).on('click', '.view-log', function() {
                var fileName = $(this).data('log-name');
                currentLogFile = fileName;
                loadLogFile(fileName);
            });

            $(document).on('click', '.download-log', function(e) {
                e.preventDefault();
                var fileName = $(this).data('log-name');

                // Build admin API URL robustly - always include /admin/ prefix for admin routes
                var rootPath = (window.CRM && window.CRM.root) ? window.CRM.root : '/';
                var url = rootPath + (rootPath.endsWith('/') ? '' : '/') + 'admin/api/system/logs/' + encodeURIComponent(fileName) + '/download';

                // Use fetch with same-origin credentials to call the download API.
                // This avoids a top-level navigation and lets us validate headers
                // (Content-Disposition) and present a proper download to the user.
                try {
                    fetch(url, { credentials: 'same-origin' })
                        .then(function(resp) {
                            if (!resp.ok) {
                                window.CRM.notify(i18next.t('Error downloading log file.'), { type: 'error' });
                                return null;
                            }

                            var cd = resp.headers.get('Content-Disposition') || resp.headers.get('content-disposition');
                            if (!cd) {
                                // If header missing, still attempt download but warn
                                window.CRM.notify(i18next.t('Download returned without attachment headers.'), { type: 'warning' });
                            }

                            return resp.blob();
                        })
                        .then(function(blob) {
                            if (!blob) { return; }
                            var tmpUrl = URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = tmpUrl;
                            a.download = fileName;
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            setTimeout(function() { URL.revokeObjectURL(tmpUrl); }, 1000);
                        })
                        .catch(function() {
                            window.CRM.notify(i18next.t('Error downloading log file.'), { type: 'error' });
                        });
                } catch (e) {
                    window.CRM.notify(i18next.t('Error initiating download.'), { type: 'error' });
                }
            });

            $(document).on('click', '.delete-log', function(e) {
                var fileName = $(this).data('log-name');
                deleteLogFile(fileName);
            });

            $('#deleteAllLogs').on('click', function() {
                deleteAllLogs();
            });

            $('.log-filter').on('click', function() {
                $('.log-filter').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('level');
                applyFilter();
            });

            $('#logLinesLimit').on('change', function() {
                applyFilter();
            });
        });
    });

    function loadLogFile(fileName) {
        $('#logViewerModalLabel').text(i18next.t('Log File Viewer') + ' - ' + fileName);
        $('#logContent').hide();
        $('#logLoading').show();
        $('#logViewerModal').modal('show');

        window.CRM.AdminAPIRequest({
            path: 'system/logs/' + encodeURIComponent(fileName),
            method: 'GET'
        })
        .done(function(data) {
            // API now returns JSON object with {success, lines: [], count}
            // Handle both old format (raw string) and new format (JSON object)
            if (typeof data === 'object' && data.success && Array.isArray(data.lines)) {
                currentLogContent = data.lines;
            } else if (typeof data === 'string') {
                // Fallback for old format (raw text)
                currentLogContent = data.split('\n');
            } else {
                // Handle error
                currentLogContent = [];
            }
            applyFilter();
            $('#logLoading').hide();
            $('#logContent').show();
        })
        .fail(function() {
            $('#logContent code').text(i18next.t('Error loading log file.'));
            $('#logLoading').hide();
            $('#logContent').show();
        });
    }

    function applyFilter() {
        // currentLogContent is now an array of strings (parsed from API)
        var lines = Array.isArray(currentLogContent) ? currentLogContent : currentLogContent.split('\n');
        var limit = $('#logLinesLimit').val();
        var filteredLines = lines;

        if (currentFilter !== 'all') {
            filteredLines = lines.filter(function(line) {
                return line.includes('.' + currentFilter);
            });
        }

        if (limit !== 'all') {
            filteredLines = filteredLines.slice(-parseInt(limit));
        }

        $('#logContent code').text(filteredLines.join('\n'));
    }

    function deleteLogFile(fileName) {
        if (confirm(i18next.t('Are you sure you want to delete') + ' ' + fileName + '?')) {
            window.CRM.AdminAPIRequest({
                path: 'system/logs/' + encodeURIComponent(fileName),
                method: 'DELETE'
            })
            .done(function() {
                location.reload();
            })
            .fail(function() {
                window.CRM.notify(i18next.t('Error deleting log file.'), { type: 'error' });
            });
        }
    }

    function deleteAllLogs() {
        if (confirm(i18next.t('Are you sure you want to delete ALL log files? This action cannot be undone.'))) {
            window.CRM.AdminAPIRequest({
                path: 'system/logs',
                method: 'DELETE'
            })
            .done(function() {
                location.reload();
            })
            .fail(function() {
                window.CRM.notify(i18next.t('Error deleting log files.'), { type: 'error' });
            });
        }
    }



</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>

<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
var logLevelMap = {
    '100': 'DEBUG',
    '200': 'INFO',
    '250': 'NOTICE',
    '300': 'WARNING',
    '400': 'ERROR',
    '500': 'CRITICAL',
    '550': 'ALERT',
    '600': 'EMERGENCY',
};

$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#logSettings',
        settings: [ 'sLogLevel' ],
        onSave: function() {
            window.CRM.notify(i18next.t('Log settings saved'), { type: 'success' });
            // Update the current log level display
            $.ajax({
                url: window.CRM.path + 'api/system/config/sLogLevel',
                type: 'GET',
                success: function(data) {
                    var levelValue = data.value || '200';
                    var levelLabel = logLevelMap[levelValue] || 'INFO';
                    $('#currentLogLevelDisplay').text(levelLabel);
                }
            });
        }
    });
});
</script>
