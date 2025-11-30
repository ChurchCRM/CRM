/**
 * Backup Database Application Logic
 * Handles the database backup workflow
 */

$(document).ready(function() {
    // Bind backup button handlers
    $('#doBackup').on('click', function(event) {
        event.preventDefault();
        doBackup(false);
    });

    $('#doRemoteBackup').on('click', function(event) {
        event.preventDefault();
        doBackup(true);
    });
});

/**
 * Set the backup status display state
 * @param {string} status - One of: 'idle', 'running', 'complete', 'error'
 */
function setStatus(status) {
    $('#statusIdle, #statusRunning, #statusComplete, #statusError').addClass('d-none');
    $('#statusCard').removeClass('card-primary card-success card-danger');
    $('#statusHeader').removeClass('bg-primary bg-success bg-danger');
    
    switch(status) {
        case 'idle':
            $('#statusIdle').removeClass('d-none');
            break;
        case 'running':
            $('#statusRunning').removeClass('d-none');
            $('#statusCard').addClass('card-primary');
            break;
        case 'complete':
            $('#statusComplete').removeClass('d-none');
            $('#statusCard').addClass('card-success');
            break;
        case 'error':
            $('#statusError').removeClass('d-none');
            $('#statusCard').addClass('card-danger');
            break;
    }
}

/**
 * Execute the backup operation
 * @param {boolean} isRemote - Whether to backup to remote storage
 */
function doBackup(isRemote) {
    var endpointURL = isRemote ? 'database/backupRemote' : 'database/backup';
    
    var formData = {
        BackupType: $('input[name=archiveType]:checked').val()
    };

    setStatus('running');
    $('#resultFiles').empty();

    window.CRM.APIRequest({
        method: 'POST',
        path: endpointURL,
        data: JSON.stringify(formData),
    })
    .done(function(data) {
        setStatus('complete');
        if (isRemote) {
            $('#statusCompleteMessage').text(i18next.t('Backup generated and copied to remote server'));
        } else {
            $('#statusCompleteMessage').text(i18next.t('Backup complete! Click below to download.'));
            var downloadButton = '<button class="btn btn-success btn-lg" id="downloadbutton" onclick="window.BackupDatabase.downloadBackup(\'' + 
                data.BackupDownloadFileName + '\')">' +
                '<i class="fa-solid fa-download mr-2"></i>' + data.BackupDownloadFileName + '</button>';
            $('#resultFiles').html(downloadButton);
        }
    })
    .fail(function() {
        setStatus('error');
    });
}

/**
 * Download the backup file and update UI
 * @param {string} filename - The backup filename to download
 */
function downloadBackup(filename) {
    window.location = window.CRM.root + '/api/database/download/' + filename;
    $('#statusCompleteMessage').text(i18next.t('Backup downloaded. Server copy removed.'));
    $('#downloadbutton').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
}

// Export functions to global scope for onclick handlers
window.BackupDatabase = {
    downloadBackup
};
