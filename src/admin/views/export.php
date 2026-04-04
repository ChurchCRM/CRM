<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row row-cards">

    <!-- CSV Export -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-file-csv me-2 text-primary"></i><?= gettext('CSV Export') ?>
                </h3>
            </div>
            <div class="card-body">
                <p class="text-secondary"><?= gettext('Export congregation data to CSV format. Choose which fields to include and filter by classification, group, gender, and more.') ?></p>
                <ul class="list-unstyled text-secondary small">
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('Flexible field selection') ?></li>
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('Advanced filtering by group, classification, and role') ?></li>
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('Family or individual record layout') ?></li>
                </ul>
            </div>
            <div class="card-footer">
                <a href="<?= SystemURLs::getRootPath() ?>/CSVExport.php" class="btn btn-primary w-100">
                    <i class="fa-solid fa-file-export me-2"></i><?= gettext('Open CSV Export') ?>
                </a>
            </div>
        </div>
    </div>

    <!-- ChMeetings Export -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-people-group me-2 text-success"></i><?= gettext('ChMeetings Export') ?>
                </h3>
            </div>
            <div class="card-body">
                <p class="text-secondary"><?= gettext('Export all people in the format expected by ChMeetings, making it easy to sync your congregation data with the ChMeetings platform.') ?></p>
                <ul class="list-unstyled text-secondary small">
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('All congregation members included') ?></li>
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('ChMeetings-compatible column format') ?></li>
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('Contact info, family roles, and key dates') ?></li>
                </ul>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-success w-100" id="exportChMeetingsBtn">
                    <i class="fa-solid fa-download me-2"></i><?= gettext('Export to ChMeetings CSV') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Database Backup -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-database me-2 text-warning"></i><?= gettext('Database Backup') ?>
                </h3>
            </div>
            <div class="card-body">
                <p class="text-secondary"><?= gettext('Create a full backup of your church database. Download a database-only SQL file or a complete archive including uploaded photos.') ?></p>
                <ul class="list-unstyled text-secondary small">
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('Database-only (.sql) or full archive (.tar.gz)') ?></li>
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('Restore from the System Backup page') ?></li>
                    <li><i class="fa-solid fa-check text-success me-1"></i><?= gettext('External (WebDAV) backup via plugin') ?></li>
                </ul>
            </div>
            <div class="card-footer">
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/backup" class="btn btn-warning w-100">
                    <i class="fa-solid fa-hard-drive me-2"></i><?= gettext('Go to Database Backup') ?>
                </a>
            </div>
        </div>
    </div>

</div>

<script>
document.getElementById('exportChMeetingsBtn').addEventListener('click', function () {
    var btn = this;
    var originalHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i><?= gettext('Exporting...') ?>';

    fetch(window.CRM.root + '/admin/api/database/people/export/chmeetings')
        .then(function (res) {
            if (!res.ok) {
                throw new Error(res.statusText);
            }
            return res.blob();
        })
        .then(function (blob) {
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'ChMeetings-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            btn.disabled = false;
            btn.innerHTML = originalHtml;

            window.CRM.notify(i18next.t('ChMeetings export completed successfully'), { type: 'success', delay: 3000 });
        })
        .catch(function (err) {
            console.error('ChMeetings export failed:', err);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            window.CRM.notify(i18next.t('Failed to export ChMeetings CSV'), { type: 'error', delay: 3000 });
        });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
