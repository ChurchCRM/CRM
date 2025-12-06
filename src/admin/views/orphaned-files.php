<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Variables passed from route: $orphanedFiles, $orphanedCount
$hasOrphanedFiles = $orphanedCount > 0;
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h3 class="card-title mb-0">
                    <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext('Orphaned Files Management') ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle mr-2"></i><?= gettext('What are Orphaned Files?') ?></h5>
                    <p class="mb-0"><?= gettext('Orphaned files are PHP or JavaScript files that exist on your server but are not part of the official ChurchCRM release. These files may be leftover from previous versions and could pose security risks if they contain outdated code with vulnerabilities.') ?></p>
                </div>

                <?php if ($hasOrphanedFiles): ?>
                    <div class="alert alert-danger">
                        <strong><i class="fa fa-exclamation-triangle mr-2"></i><?= sprintf(gettext('%d Orphaned Files Detected'), $orphanedCount) ?></strong>
                        <p class="mb-0 mt-2"><?= gettext('Review the files below and delete them to improve security.') ?></p>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-danger btn-lg" id="deleteAllOrphanedFiles">
                            <i class="fa fa-trash mr-2"></i><?= gettext('Delete All Orphaned Files') ?>
                        </button>
                        <button type="button" class="btn btn-secondary ml-2" id="refreshOrphanedFiles">
                            <i class="fa fa-sync mr-2"></i><?= gettext('Refresh List') ?>
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover" id="orphanedFilesTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th><?= gettext('File Path') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanedFiles as $index => $filename): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <code class="text-monospace" style="word-break: break-all; font-size: 0.85rem;">
                                                <?= InputUtils::escapeHTML($filename) ?>
                                            </code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <strong><i class="fa fa-shield-alt mr-2"></i><?= gettext('Security Recommendation') ?></strong>
                        <p class="mb-0"><?= gettext('These files were likely part of an older ChurchCRM version and were not cleaned up during a previous upgrade. Deleting them will improve your system security.') ?></p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle mr-2"></i>
                        <strong><?= gettext('No Orphaned Files Found') ?></strong>
                        <p class="mb-0 mt-2"><?= gettext('Your ChurchCRM installation is clean. All files on the server match the official release.') ?></p>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug" class="btn btn-outline-secondary">
                        <i class="fa fa-arrow-left mr-2"></i><?= gettext('Back to Debug Info') ?>
                    </a>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/" class="btn btn-outline-primary ml-2">
                        <i class="fa fa-tools mr-2"></i><?= gettext('Admin Dashboard') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext('Confirm Deletion') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= gettext('Are you sure you want to delete all orphaned files?') ?></p>
                <p class="text-danger"><strong><?= gettext('This action cannot be undone.') ?></strong></p>
                <p class="text-muted"><?= sprintf(gettext('%d files will be permanently deleted.'), $orphanedCount) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmDeleteAll">
                    <i class="fa fa-trash mr-2"></i><?= gettext('Delete All Files') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Results Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1" role="dialog" aria-labelledby="resultsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" id="resultsModalHeader">
                <h5 class="modal-title" id="resultsModalLabel"><?= gettext('Deletion Results') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="resultsModalBody">
                <!-- Results will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="resultsModalClose"><?= gettext('Close & Refresh') ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function() {
    'use strict';

    function initOrphanedFilesPage() {
        // Initialize DataTable
        if ($.fn.DataTable && $('#orphanedFilesTable tbody tr').length > 0) {
            $('#orphanedFilesTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[1, 'asc']],
                language: {
                    search: i18next.t('Search') + ':',
                    lengthMenu: i18next.t('Show _MENU_ entries'),
                    info: i18next.t('Showing _START_ to _END_ of _TOTAL_ entries'),
                    paginate: {
                        first: i18next.t('First'),
                        last: i18next.t('Last'),
                        next: i18next.t('Next'),
                        previous: i18next.t('Previous')
                    }
                }
            });
        }

        // Delete All button click
        $('#deleteAllOrphanedFiles').on('click', function() {
            $('#deleteConfirmModal').modal('show');
        });

        // Confirm Delete button click
        $('#confirmDeleteAll').on('click', function() {
            $('#deleteConfirmModal').modal('hide');
            deleteOrphanedFiles();
        });

        // Refresh button click
        $('#refreshOrphanedFiles').on('click', function() {
            window.location.reload();
        });

        // Results modal close button
        $('#resultsModalClose').on('click', function() {
            $('#resultsModal').modal('hide');
            window.location.reload();
        });
    }

    function deleteOrphanedFiles() {
        // Show loading state
        $('#deleteAllOrphanedFiles').prop('disabled', true).html(
            '<i class="fa fa-spinner fa-spin mr-2"></i>' + i18next.t('Deleting...')
        );

        window.CRM.AdminAPIRequest({
            path: 'orphaned-files/delete-all',
            method: 'POST'
        })
        .done(function(response) {
            showResults(response);
        })
        .fail(function(xhr) {
            var errorMsg = i18next.t('An error occurred while deleting files.');
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            showResults({
                deleted: [],
                failed: [],
                errors: [errorMsg]
            });
        })
        .always(function() {
            $('#deleteAllOrphanedFiles').prop('disabled', false).html(
                '<i class="fa fa-trash mr-2"></i>' + i18next.t('Delete All Orphaned Files')
            );
        });
    }

    function showResults(result) {
        var deletedCount = result.deleted ? result.deleted.length : 0;
        var failedCount = result.failed ? result.failed.length : 0;
        var hasErrors = failedCount > 0 || (result.errors && result.errors.length > 0);

        // Set modal header color based on results
        var headerClass = hasErrors ? 'bg-warning' : 'bg-success text-white';
        $('#resultsModalHeader').removeClass('bg-success bg-warning bg-danger text-white').addClass(headerClass);

        var html = '';

        // Summary
        if (deletedCount > 0) {
            html += '<div class="alert alert-success">';
            html += '<i class="fa fa-check-circle mr-2"></i>';
            html += '<strong>' + i18next.t('Successfully deleted') + ': ' + deletedCount + ' ' + i18next.t('files') + '</strong>';
            html += '</div>';
        }

        if (failedCount > 0) {
            html += '<div class="alert alert-danger">';
            html += '<i class="fa fa-times-circle mr-2"></i>';
            html += '<strong>' + i18next.t('Failed to delete') + ': ' + failedCount + ' ' + i18next.t('files') + '</strong>';
            html += '</div>';
        }

        // Deleted files list
        if (deletedCount > 0 && deletedCount <= 20) {
            html += '<h6>' + i18next.t('Deleted Files') + ':</h6>';
            html += '<ul class="list-group mb-3">';
            result.deleted.forEach(function(file) {
                html += '<li class="list-group-item list-group-item-success py-1"><code>' + escapeHtml(file) + '</code></li>';
            });
            html += '</ul>';
        } else if (deletedCount > 20) {
            html += '<p class="text-muted">' + i18next.t('Deleted') + ' ' + deletedCount + ' ' + i18next.t('files') + '</p>';
        }

        // Failed files list
        if (failedCount > 0) {
            html += '<h6 class="text-danger">' + i18next.t('Failed Files') + ':</h6>';
            html += '<ul class="list-group mb-3">';
            result.failed.forEach(function(file) {
                html += '<li class="list-group-item list-group-item-danger py-1"><code>' + escapeHtml(file) + '</code></li>';
            });
            html += '</ul>';
        }

        // Errors
        if (result.errors && result.errors.length > 0) {
            html += '<h6 class="text-danger">' + i18next.t('Errors') + ':</h6>';
            html += '<ul class="list-group">';
            result.errors.forEach(function(error) {
                html += '<li class="list-group-item list-group-item-warning py-1">' + escapeHtml(error) + '</li>';
            });
            html += '</ul>';
        }

        if (deletedCount === 0 && failedCount === 0) {
            html += '<div class="alert alert-info">';
            html += '<i class="fa fa-info-circle mr-2"></i>';
            html += i18next.t('No files were deleted.');
            html += '</div>';
        }

        $('#resultsModalBody').html(html);
        $('#resultsModal').modal('show');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when CRM locales are ready
    if (window.CRM && typeof window.CRM.onLocalesReady === 'function') {
        window.CRM.onLocalesReady(initOrphanedFilesPage);
    } else {
        $(document).ready(initOrphanedFilesPage);
    }
})();
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
