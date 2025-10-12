<?php

use ChurchCRM\dto\SystemURLs;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= gettext('System Logs') ?></h4>
                <p class="text-muted"><?= gettext('View application logs. Click on a log file to view its contents.') ?></p>
            </div>
            <div class="card-body">
                <?php if (empty($logFiles)): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <?= gettext('No log files found.') ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="logFilesTable">
                            <thead>
                                <tr>
                                    <th><?= gettext('Log File') ?></th>
                                    <th><?= gettext('Size') ?></th>
                                    <th><?= gettext('Last Modified') ?></th>
                                    <th><?= gettext('Actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logFiles as $logFile): ?>
                                    <tr>
                                        <td>
                                            <i class="fa fa-file-alt"></i> 
                                            <strong><?= htmlspecialchars($logFile['name']) ?></strong>
                                        </td>
                                        <td><?= number_format($logFile['size'] / 1024, 2) ?> KB</td>
                                        <td><?= date('Y-m-d H:i:s', $logFile['modified']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-log" 
                                                    data-file="<?= htmlspecialchars($logFile['name']) ?>">
                                                <i class="fa fa-eye"></i> <?= gettext('View') ?>
                                            </button>
                                        </td>
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
                <div id="logLoading" style="display: none; text-align: center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                    <p><?= gettext('Loading log file...') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    var currentLogFile = '';
    var currentLogContent = '';
    var currentFilter = 'all';

    $(document).ready(function() {
        $('#logFilesTable').DataTable({
            responsive: true,
            order: [[2, 'desc']],
            pageLength: 25
        });

        $('.view-log').on('click', function() {
            var fileName = $(this).data('file');
            currentLogFile = fileName;
            loadLogFile(fileName);
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

    function loadLogFile(fileName) {
        $('#logViewerModalLabel').text('<?= gettext('Log File Viewer') ?> - ' + fileName);
        $('#logContent').hide();
        $('#logLoading').show();
        $('#logViewerModal').modal('show');

        $.ajax({
            url: '<?= SystemURLs::getRootPath() ?>/api/logs/' + encodeURIComponent(fileName),
            method: 'GET',
            success: function(data) {
                currentLogContent = data;
                applyFilter();
                $('#logLoading').hide();
                $('#logContent').show();
            },
            error: function() {
                $('#logContent code').text('<?= gettext('Error loading log file.') ?>');
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
</script>

<?php
include SystemURLs::getDocumentRoot() . '/Include/Footer.php';
