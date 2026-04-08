<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Danger banner -->
<div class="alert alert-danger" role="alert">
    <div class="d-flex">
        <div>
            <i class="ti ti-alert-triangle me-2 fs-2"></i>
        </div>
        <div>
            <h4 class="alert-title"><?= gettext('Destructive Operation') ?></h4>
            <div class="text-secondary">
                <?= gettext('This page allows you to permanently erase all data in the ChurchCRM database. This action cannot be undone.') ?>
            </div>
        </div>
    </div>
</div>

<div class="row row-deck row-cards">

    <!-- Step 1: Create a backup -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-stamp">
                <div class="card-stamp-icon bg-info">
                    <i class="ti ti-database-export"></i>
                </div>
            </div>
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-download me-2"></i><?= gettext('Step 1: Create a Backup') ?></h3>
                <span class="badge bg-info-lt"><?= gettext('Recommended') ?></span>
            </div>
            <div class="card-body">
                <p class="text-secondary">
                    <?= gettext('Before resetting, we strongly recommend downloading a backup of your current data. This is your only chance to preserve it.') ?>
                </p>

                <!-- Backup type selector -->
                <div class="mb-3">
                    <label class="form-label"><?= gettext('Backup Type') ?></label>
                    <div class="form-selectgroup form-selectgroup-boxes d-flex flex-column gap-2">
                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="archiveType" value="0" class="form-selectgroup-input" checked>
                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                <div class="me-3">
                                    <span class="form-selectgroup-check"></span>
                                </div>
                                <div>
                                    <strong><?= gettext('SQL Only') ?></strong>
                                    <div class="text-secondary"><?= gettext('Database dump only (smaller, faster)') ?></div>
                                </div>
                            </div>
                        </label>
                        <label class="form-selectgroup-item flex-fill">
                            <input type="radio" name="archiveType" value="3" class="form-selectgroup-input">
                            <div class="form-selectgroup-label d-flex align-items-center p-3">
                                <div class="me-3">
                                    <span class="form-selectgroup-check"></span>
                                </div>
                                <div>
                                    <strong><?= gettext('Full Backup') ?></strong>
                                    <div class="text-secondary"><?= gettext('Database + uploaded photos (larger)') ?></div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="button" class="btn btn-info w-100" id="doBackup">
                    <i class="ti ti-download me-2"></i><?= gettext('Generate & Download Backup') ?>
                </button>

                <!-- Backup status area -->
                <div id="backupStatus" class="mt-3 d-none">
                    <div id="backupRunning" class="d-none">
                        <div class="progress progress-sm mb-2">
                            <div class="progress-bar progress-bar-indeterminate bg-info"></div>
                        </div>
                        <p class="text-secondary text-center small mb-0"><?= gettext('Creating backup...') ?></p>
                    </div>
                    <div id="backupComplete" class="d-none">
                        <div class="alert alert-success mb-0">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-check me-2"></i>
                                <span id="backupCompleteMessage"><?= gettext('Backup ready!') ?></span>
                            </div>
                            <button class="btn btn-success btn-sm mt-2 w-100 d-none" id="downloadBackup">
                                <i class="ti ti-download me-2"></i><span id="downloadFilename"></span>
                            </button>
                        </div>
                    </div>
                    <div id="backupError" class="d-none">
                        <div class="alert alert-danger mb-0">
                            <i class="ti ti-alert-circle me-2"></i><?= gettext('Backup failed. You can still proceed with the reset, but your data will be lost.') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Reset Database -->
    <div class="col-lg-6">
        <div class="card border-danger">
            <div class="card-stamp">
                <div class="card-stamp-icon bg-danger">
                    <i class="ti ti-trash"></i>
                </div>
            </div>
            <div class="card-header">
                <h3 class="card-title text-danger"><i class="ti ti-alert-triangle me-2"></i><?= gettext('Step 2: Reset Database') ?></h3>
            </div>
            <div class="card-body">
                <p class="text-secondary"><?= gettext('Resetting will permanently delete:') ?></p>
                <ul class="list-unstyled space-y-1">
                    <li><i class="ti ti-x text-danger me-1"></i><?= gettext('All people and family records') ?></li>
                    <li><i class="ti ti-x text-danger me-1"></i><?= gettext('All groups, roles, and memberships') ?></li>
                    <li><i class="ti ti-x text-danger me-1"></i><?= gettext('All financial data (pledges, payments, deposits)') ?></li>
                    <li><i class="ti ti-x text-danger me-1"></i><?= gettext('All events and attendance records') ?></li>
                    <li><i class="ti ti-x text-danger me-1"></i><?= gettext('All uploaded photos and documents') ?></li>
                    <li><i class="ti ti-x text-danger me-1"></i><?= gettext('All system settings and custom fields') ?></li>
                </ul>

                <div class="hr-text"><?= gettext('Confirm') ?></div>

                <div class="mb-3">
                    <label class="form-label" for="confirmInput">
                        <?= gettext('Type') ?> <strong>RESET</strong> <?= gettext('to enable the reset button') ?>
                    </label>
                    <input type="text" class="form-control" id="confirmInput"
                           placeholder="<?= gettext('Type RESET to confirm') ?>"
                           autocomplete="off" spellcheck="false">
                </div>

                <button type="button" class="btn btn-danger w-100" id="resetBtn" disabled>
                    <i class="ti ti-trash me-2"></i><?= gettext('Reset Database') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    window.CRM.onLocalesReady(function () {

        // ── Backup Flow ──────────────────────────────────────────
        $("#doBackup").on("click", function () {
            var type = $("input[name=archiveType]:checked").val();

            $("#backupStatus").removeClass("d-none");
            $("#backupRunning").removeClass("d-none");
            $("#backupComplete, #backupError").addClass("d-none");
            $("#doBackup").prop("disabled", true);

            window.CRM.APIRequest({
                method: "POST",
                path: "database/backup",
                data: JSON.stringify({ BackupType: type })
            })
            .done(function (data) {
                $("#backupRunning").addClass("d-none");
                $("#backupComplete").removeClass("d-none");

                if (data && data.BackupDownloadFileName) {
                    $("#downloadFilename").text(data.BackupDownloadFileName);
                    $("#downloadBackup").removeClass("d-none").data("filename", data.BackupDownloadFileName);
                }
                $("#doBackup").prop("disabled", false);
            })
            .fail(function () {
                $("#backupRunning").addClass("d-none");
                $("#backupError").removeClass("d-none");
                $("#doBackup").prop("disabled", false);
            });
        });

        $("#downloadBackup").on("click", function () {
            var filename = $(this).data("filename");
            window.location = window.CRM.root + "/api/database/download/" + filename;
            $(this).prop("disabled", true).removeClass("btn-success").addClass("btn-secondary");
            $("#backupCompleteMessage").text(i18next.t("Backup downloaded."));
        });

        // ── Confirm input ────────────────────────────────────────
        $("#confirmInput").on("input", function () {
            var match = $(this).val().trim() === "RESET";
            $("#resetBtn").prop("disabled", !match);
        });

        // ── Reset Flow ───────────────────────────────────────────
        $("#resetBtn").on("click", function () {
            bootbox.confirm({
                title: '<i class="ti ti-alert-triangle text-danger me-2"></i>' + i18next.t("Final Confirmation"),
                message: '<p>' + i18next.t("Are you absolutely sure? This will erase all data and restore factory defaults.") + '</p>' +
                         '<p class="text-danger fw-bold mb-0">' + i18next.t("This action cannot be undone.") + '</p>',
                buttons: {
                    confirm: {
                        label: '<i class="ti ti-trash me-1"></i>' + i18next.t('Reset Database'),
                        className: 'btn-danger'
                    },
                    cancel: {
                        label: i18next.t('Cancel'),
                        className: 'btn-secondary'
                    }
                },
                callback: function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    $("#resetBtn").prop("disabled", true).html(
                        '<span class="spinner-border spinner-border-sm me-2"></span>' + i18next.t("Resetting...")
                    );

                    window.CRM.AdminAPIRequest({
                        path: 'database/reset',
                        method: 'DELETE'
                    })
                    .done(function (data) {
                        var username = (data && data.defaultUsername) ? data.defaultUsername : 'admin';
                        var password = (data && data.defaultPassword) ? data.defaultPassword : 'changeme';

                        bootbox.alert({
                            title: '<i class="ti ti-check text-success me-2"></i>' + i18next.t('Reset Complete'),
                            message: '<p>' + i18next.t('The database has been cleared. The system is ready for a fresh start.') + '</p>' +
                                     '<div class="alert alert-info mb-0">' +
                                     '<strong>' + i18next.t('Default admin credentials') + ':</strong><br>' +
                                     '<code>' + username + '</code> / <code>' + password + '</code>' +
                                     '</div>',
                            callback: function () {
                                window.location.href = window.CRM.root + "/";
                            }
                        });
                    })
                    .fail(function (xhr) {
                        var msg = i18next.t('Database reset failed.');
                        if (xhr.responseJSON && xhr.responseJSON.msg) {
                            msg = xhr.responseJSON.msg;
                        }
                        bootbox.alert({
                            title: '<i class="ti ti-alert-circle text-danger me-2"></i>' + i18next.t('Error'),
                            message: msg
                        });
                        $("#resetBtn").prop("disabled", false).html(
                            '<i class="ti ti-trash me-2"></i>' + i18next.t('Reset Database')
                        );
                        $("#confirmInput").val("");
                    });
                }
            });
        });
    });
});
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
