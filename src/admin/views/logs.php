<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= gettext('Log Settings') ?></h4>
            </div>
            <div class="card-body">
                <form class="form-inline">
                    <div class="form-group mr-3">
                        <label for="logLevel" class="mr-2"><?= gettext('Log Level:') ?></label>
                        <select class="form-control" id="logLevel">
                            <option value="100">DEBUG (100)</option>
                            <option value="200" selected>INFO (200)</option>
                            <option value="250">NOTICE (250)</option>
                            <option value="300">WARNING (300)</option>
                            <option value="400">ERROR (400)</option>
                            <option value="500">CRITICAL (500)</option>
                            <option value="550">ALERT (550)</option>
                            <option value="600">EMERGENCY (600)</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" id="saveLogLevel">
                        <i class="fa-solid fa-save"></i> <?= gettext('Save Log Level') ?>
                    </button>
                    <span id="logLevelStatus" class="ml-3"></span>
                </form>
                <p class="text-muted mt-2 mb-0">
                    <small><i class="fa-solid fa-info-circle"></i> <?= gettext('Lower numbers log more details. Higher numbers log only severe issues. Changes apply to new log entries immediately.') ?></small>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= gettext('System Logs') ?></h4>
                <p class="text-muted"><?= gettext('View application logs. Click on a log file to view its contents.') ?></p>
                <?php if (!empty($logFiles)): ?>
                <button class="btn btn-danger float-right" id="deleteAllLogs">
                    <i class="fa-solid fa-trash"></i> <?= gettext('Delete All Logs') ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($logFiles)): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle"></i> <?= gettext('No log files found.') ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="logFilesTable">
                            <thead>
                                <tr>
                                    <th style="width: 10%;"><?= gettext('Actions') ?></th>
                                    <th style="width: 50%;"><?= gettext('Log File') ?></th>
                                    <th style="width: 15%;"><?= gettext('Size') ?></th>
                                    <th style="width: 25%;"><?= gettext('Last Modified') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logFiles as $logFile): ?>
                                    <tr>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-log" 
                                                    data-file="<?= InputUtils::escapeHTML($logFile['name']) ?>"
                                                    title="<?= gettext('View') ?>">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-log" 
                                                    data-file="<?= InputUtils::escapeHTML($logFile['name']) ?>"
                                                    title="<?= gettext('Delete') ?>">\n
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <i class="fa-solid fa-file-alt"></i> 
                                            <strong><?= htmlspecialchars($logFile['name']) ?></strong>
                                        </td>
                                        <td><?= number_format($logFile['size'] / 1024, 2) ?> KB</td>
                                        <td><?= date('Y-m-d H:i:s', $logFile['modified']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Log Viewer Modal -->
<div class="modal fade" id="logViewerModal" tabindex="-1" role="dialog" aria-labelledby="logViewerModalLabel">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="logViewerModalLabel"><?= gettext('Log File Viewer') ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?= gettext('Filter by log level:') ?></label>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary log-filter active" data-level="all"><?= gettext('All') ?></button>
                        <button type="button" class="btn btn-outline-danger log-filter" data-level="ERROR"><?= gettext('Error') ?></button>
                        <button type="button" class="btn btn-outline-warning log-filter" data-level="WARNING"><?= gettext('Warning') ?></button>
                        <button type="button" class="btn btn-outline-info log-filter" data-level="INFO"><?= gettext('Info') ?></button>
                        <button type="button" class="btn btn-outline-secondary log-filter" data-level="DEBUG"><?= gettext('Debug') ?></button>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= gettext('Number of lines to display:') ?></label>
                    <select class="form-control form-control-sm" id="logLinesLimit" style="width: auto; display: inline-block;">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                        <option value="all"><?= gettext('All') ?></option>
                    </select>
                </div>
                <pre id="logContent" style="max-height: 500px; overflow-y: auto; background-color: #f4f4f4; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"><code></code></pre>
                <div id="logLoading" class="d-none text-center p-3">
                    <i class="fa-solid fa-spinner fa-spin fa-3x"></i>
                    <p><?= gettext('Loading log file...') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= gettext('Close') ?></button>
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
            loadLogLevel();

            var table = $('#logFilesTable').DataTable({
                responsive: true,
                order: [[3, 'desc']],
                paging: false,
                info: false,
                searching: false,
                columnDefs: [
                    { orderable: false, targets: 0 }
                ]
            });

            $(document).on('click', '.view-log', function() {
                var fileName = $(this).data('file');
                currentLogFile = fileName;
                loadLogFile(fileName);
            });

            $(document).on('click', '.delete-log', function(e) {
                var fileName = $(this).data('file');
                deleteLogFile(fileName);
            });

            $('#deleteAllLogs').on('click', function() {
                deleteAllLogs();
            });

            $('#saveLogLevel').on('click', function() {
                saveLogLevel();
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

        $.ajax({
            url: '<?= SystemURLs::getRootPath() ?>/api/system/logs/' + encodeURIComponent(fileName),
            method: 'GET',
            success: function(data) {
                currentLogContent = data;
                applyFilter();
                $('#logLoading').hide();
                $('#logContent').show();
            },
            error: function() {
                $('#logContent code').text(i18next.t('Error loading log file.'));
                $('#logLoading').hide();
                $('#logContent').show();
            }
        });
    }

    function applyFilter() {
        var lines = currentLogContent.split('\n');
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
            $.ajax({
                url: '<?= SystemURLs::getRootPath() ?>/api/system/logs/' + encodeURIComponent(fileName),
                method: 'DELETE',
                success: function() {
                    location.reload();
                },
                error: function() {
                    alert(i18next.t('Error deleting log file.'));
                }
            });
        }
    }

    function deleteAllLogs() {
        if (confirm(i18next.t('Are you sure you want to delete ALL log files? This action cannot be undone.'))) {
            $.ajax({
                url: '<?= SystemURLs::getRootPath() ?>/api/system/logs',
                method: 'DELETE',
                success: function() {
                    location.reload();
                },
                error: function() {
                    alert(i18next.t('Error deleting log files.'));
                }
            });
        }
    }

    function loadLogLevel() {
        var logLevelMap = {
            'DEBUG': '100',
            'INFO': '200',
            'NOTICE': '250',
            'WARNING': '300',
            'ERROR': '400',
            'CRITICAL': '500',
            'ALERT': '550',
            'EMERGENCY': '600'
        };
        
        $.ajax({
            url: '<?= SystemURLs::getRootPath() ?>/api/system/config/sLogLevel',
            method: 'GET',
            cache: false,
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            success: function(data) {
                if (data.value) {
                    var logLevelValue = data.value;
                    
                    if (logLevelMap[logLevelValue]) {
                        logLevelValue = logLevelMap[logLevelValue];
                    } else {
                        logLevelValue = String(logLevelValue);
                    }
                    
                    $('#logLevel').val(logLevelValue);
                } else {
                    $('#logLevel').val('200');
                }
            },
            error: function(xhr, status, error) {
                console.error(i18next.t('Error loading log level.'), error);
                $('#logLevel').val('200');
            }
        });
    }

    function saveLogLevel() {
        var logLevel = $('#logLevel').val();
        $.ajax({
            url: '<?= SystemURLs::getRootPath() ?>/api/system/logs/loglevel',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ value: logLevel }),
            success: function(data) {
                $('#logLevelStatus').html('<span class="text-success"><i class="fa-solid fa-check"></i> ' + i18next.t('Saved - Log level updated immediately') + '</span>');
                setTimeout(function() {
                    $('#logLevelStatus').html('');
                }, 3000);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#logLevelStatus').html('<span class="text-danger"><i class="fa-solid fa-times"></i> ' + i18next.t('Error') + '</span>');
            }
        });
    }
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
