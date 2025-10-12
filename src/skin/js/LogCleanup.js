$(document).ready(function() {
    loadLogFiles();
});

function loadLogFiles() {
    window.CRM.APIRequest({
        method: 'GET',
        path: 'system/logs'
    }).done(function(data) {
        displayLogFiles(data.files);
    }).fail(function() {
        $('#logFilesList').html('<div class="alert alert-danger">Failed to load log files.</div>');
    });
}

function displayLogFiles(files) {
    if (files.length === 0) {
        $('#logFilesList').html('<div class="alert alert-info">No log files found.</div>');
        return;
    }

    var html = '<table class="table table-striped table-bordered">';
    html += '<thead><tr>';
    html += '<th><input type="checkbox" id="selectAll"></th>';
    html += '<th>File Name</th>';
    html += '<th>Size</th>';
    html += '<th>Last Modified</th>';
    html += '<th>Age (Days)</th>';
    html += '</tr></thead><tbody>';

    var now = Math.floor(Date.now() / 1000);
    
    files.forEach(function(file) {
        var ageInDays = Math.floor((now - file.modified) / 86400);
        var sizeInKB = (file.size / 1024).toFixed(2);
        var modifiedDate = new Date(file.modified * 1000).toLocaleString();
        
        html += '<tr>';
        html += '<td><input type="checkbox" class="log-file-checkbox" value="' + file.name + '"></td>';
        html += '<td>' + file.name + '</td>';
        html += '<td>' + sizeInKB + ' KB</td>';
        html += '<td>' + modifiedDate + '</td>';
        html += '<td>' + ageInDays + '</td>';
        html += '</tr>';
    });

    html += '</tbody></table>';
    $('#logFilesList').html(html);
    
    $('#deleteSelectedBtn').show();
    $('#deleteOldBtn').show();

    // Select all checkbox handler
    $('#selectAll').click(function() {
        $('.log-file-checkbox').prop('checked', this.checked);
    });
}

function deleteSelectedFiles() {
    var selectedFiles = [];
    $('.log-file-checkbox:checked').each(function() {
        selectedFiles.push($(this).val());
    });

    if (selectedFiles.length === 0) {
        alert('Please select at least one file to delete.');
        return;
    }

    if (!confirm('Are you sure you want to delete ' + selectedFiles.length + ' file(s)?')) {
        return;
    }

    window.CRM.APIRequest({
        method: 'POST',
        path: 'system/logs/delete',
        data: JSON.stringify({ files: selectedFiles })
    }).done(function(data) {
        if (data.success) {
            alert('Successfully deleted ' + data.deletedCount + ' file(s).');
            loadLogFiles();
        } else {
            alert('Deleted ' + data.deletedCount + ' file(s). Errors: ' + data.errors.join(', '));
            loadLogFiles();
        }
    }).fail(function() {
        alert('Failed to delete files.');
    });
}

function deleteOldFiles() {
    if (!confirm('Are you sure you want to delete all log files older than 30 days?')) {
        return;
    }

    var now = Math.floor(Date.now() / 1000);
    var thirtyDaysAgo = now - (30 * 86400);
    var oldFiles = [];

    // Temporarily load all files to find old ones
    window.CRM.APIRequest({
        method: 'GET',
        path: 'system/logs'
    }).done(function(data) {
        data.files.forEach(function(file) {
            if (file.modified < thirtyDaysAgo) {
                oldFiles.push(file.name);
            }
        });

        if (oldFiles.length === 0) {
            alert('No log files older than 30 days found.');
            return;
        }

        window.CRM.APIRequest({
            method: 'POST',
            path: 'system/logs/delete',
            data: JSON.stringify({ files: oldFiles })
        }).done(function(data) {
            if (data.success) {
                alert('Successfully deleted ' + data.deletedCount + ' old file(s).');
                loadLogFiles();
            } else {
                alert('Deleted ' + data.deletedCount + ' file(s). Errors: ' + data.errors.join(', '));
                loadLogFiles();
            }
        }).fail(function() {
            alert('Failed to delete files.');
        });
    });
}

$('#deleteSelectedBtn').click(function(event) {
    event.preventDefault();
    deleteSelectedFiles();
});

$('#deleteOldBtn').click(function(event) {
    event.preventDefault();
    deleteOldFiles();
});
