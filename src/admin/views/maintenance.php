<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= gettext('System Maintenance') ?></h3>
            </div>
            <div class="card-body">
                <p><?= gettext('Manage system-level database operations including backups, restores, resets, and demo data.') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Backup Database -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-primary">
                <h4 class="card-title mb-0 text-white">
                    <i class="fa fa-download mr-2"></i><?= gettext("Backup Database") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Create a backup of your ChurchCRM database and optionally include photos.") ?></p>
                <ul class="mb-3">
                    <li><?= gettext("Make backups regularly (at least weekly)") ?></li>
                    <li><?= gettext("Store copies both on-site (fire-proof safe) and off-site") ?></li>
                    <li><?= gettext("Encrypt backups if storing in accessible locations") ?></li>
                </ul>
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/BackupDatabase.php" class="btn btn-primary btn-block">
                        <i class="fa fa-download mr-2"></i><?= gettext("Backup Database") ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Database -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning">
                <h4 class="card-title mb-0">
                    <i class="fa fa-upload mr-2"></i><?= gettext("Restore Database") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Restore ChurchCRM database from a backup file.") ?></p>
                <ul class="mb-3">
                    <li class="text-danger"><strong><?= gettext("WARNING: This completely erases the existing database") ?></strong></li>
                    <li><?= gettext("Supports ChurchInfo and older ChurchCRM backups") ?></li>
                    <li><?= gettext("Automatic schema upgrade during restore") ?></li>
                </ul>
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/RestoreDatabase.php" class="btn btn-warning btn-block">
                        <i class="fa fa-upload mr-2"></i><?= gettext("Restore Database") ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Import Demo Data -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-success">
                <h4 class="card-title mb-0 text-white">
                    <i class="fa fa-users mr-2"></i><?= gettext("Import Demo Data") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Load sample families, people, groups, and events for testing and demonstration.") ?></p>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="includeDemoFinancial">
                        <label class="form-check-label" for="includeDemoFinancial">
                            <?= gettext("Include financial data (donation funds, pledges)") ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="includeDemoEvents">
                        <label class="form-check-label" for="includeDemoEvents">
                            <?= gettext("Include events and calendars") ?>
                        </label>
                    </div>
                </div>

                <div class="text-center">
                    <button type="button" class="btn btn-success btn-block" id="importDemoData">
                        <i class="fa fa-users mr-2"></i><?= gettext("Import Demo Data") ?>
                    </button>
                </div>

                <div id="demoImportStatus" class="mt-3" style="display: none;">
                    <div class="alert alert-info mb-0">
                        <i class="fa fa-spinner fa-spin mr-2"></i><span id="demoImportMessage"><?= gettext("Importing demo data...") ?></span>
                    </div>
                </div>

                <div id="demoImportResults" class="mt-3" style="display: none;">
                    <h5><?= gettext("Import Results") ?>:</h5>
                    <ul id="demoImportResultsList"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset System -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-danger">
                <h4 class="card-title mb-0 text-white">
                    <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext("Reset System") ?>
                </h4>
            </div>
            <div class="card-body">
                <p><?= gettext("Reset database to factory defaults or clear specific data.") ?></p>
                <ul class="mb-3">
                    <li class="text-danger"><strong><?= gettext("WARNING: These operations are irreversible") ?></strong></li>
                    <li><?= gettext("Reset entire database (new install state)") ?></li>
                    <li><?= gettext("Clear families and people only") ?></li>
                </ul>
                <div class="text-center">
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/admin/database/reset" class="btn btn-danger btn-block">
                        <i class="fa fa-exclamation-triangle mr-2"></i><?= gettext("Reset System") ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    window.CRM.onLocalesReady(function () {
        
        $('#importDemoData').click(function() {
            var includeFinancial = $('#includeDemoFinancial').is(':checked');
            var includeEvents = $('#includeDemoEvents').is(':checked');
            
            bootbox.confirm({
                title: i18next.t("Import Demo Data"),
                message: i18next.t("This will add sample data to your database. Are you sure you want to continue?"),
                buttons: {
                    confirm: {
                        label: i18next.t('Yes'),
                        className: 'btn-success'
                    },
                    cancel: {
                        label: i18next.t('Cancel'),
                        className: 'btn-default'
                    }
                },
                callback: function (result) {
                    if (result) {
                        $('#demoImportStatus').show();
                        $('#demoImportResults').hide();
                        $('#importDemoData').prop('disabled', true);
                        
                        $.ajax({
                            url: window.CRM.root + '/admin/api/demo/load',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                includeFinancial: includeFinancial,
                                includeEvents: includeEvents
                            }),
                            success: function (data) {
                                $('#demoImportStatus').hide();
                                $('#importDemoData').prop('disabled', false);
                                
                                if (data.success) {
                                    $('#demoImportResultsList').empty();
                                    
                                    var imported = data.imported;
                                    for (var key in imported) {
                                        if (imported[key] > 0) {
                                            var label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                            $('#demoImportResultsList').append(
                                                '<li>' + label + ': <strong>' + imported[key] + '</strong></li>'
                                            );
                                        }
                                    }
                                    
                                    if (data.warnings && data.warnings.length > 0) {
                                        $('#demoImportResultsList').append(
                                            '<li class="text-warning">' + i18next.t('Warnings') + ': ' + data.warnings.length + '</li>'
                                        );
                                    }
                                    
                                    $('#demoImportResults').show();
                                    
                                    window.CRM.notify(i18next.t('Demo data imported successfully'), {
                                        type: 'success',
                                        delay: 3000
                                    });
                                } else {
                                    window.CRM.notify(i18next.t('Demo data import failed: ') + (data.error || i18next.t('Unknown error')), {
                                        type: 'error',
                                        delay: 5000
                                    });
                                }
                            },
                            error: function (xhr, status, error) {
                                $('#demoImportStatus').hide();
                                $('#importDemoData').prop('disabled', false);
                                
                                var errorMessage = i18next.t('An error occurred during demo data import');
                                if (xhr.responseJSON && xhr.responseJSON.error) {
                                    errorMessage = xhr.responseJSON.error;
                                }
                                
                                window.CRM.notify(errorMessage, {
                                    type: 'error',
                                    delay: 5000
                                });
                            }
                        });
                    }
                }
            });
        });

    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
