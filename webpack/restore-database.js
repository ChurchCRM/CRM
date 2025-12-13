/**
 * Restore Database Application Logic
 * Handles the database restoration workflow
 */

$(document).ready(function() {
    // Initialize file input
    initializeFileInput();
    
    // Bind form submission
    $('#restoredatabase').on('submit', function(event) {
        event.preventDefault();
        handleRestoreSubmit($(this));
        return false;
    });
});

/**
 * Initialize the file input with drag-drop support
 */
function initializeFileInput() {
    const $dropzone = $('#dropzone');
    const $fileInput = $('#restoreFile');
    const $fileInfo = $('#fileInfo');
    const $fileName = $('#fileName');
    const $fileSize = $('#fileSize');

    // Click to select file - use native click to avoid event bubbling issues
    $dropzone.on('click', function(e) {
        // Prevent triggering if clicking on the input itself
        if (e.target === $fileInput[0]) {
            return;
        }
        $fileInput[0].click();
    });

    // Handle file selection
    $fileInput.on('change', function() {
        const file = this.files[0];
        if (file) {
            displayFileInfo(file);
        }
    });

    // Prevent click events from file input from bubbling to dropzone
    $fileInput.on('click', function(e) {
        e.stopPropagation();
    });

    // Drag and drop handlers
    $dropzone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    $dropzone.on('dragleave dragend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    $dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $fileInput[0].files = files;
            displayFileInfo(files[0]);
        }
    });

    /**
     * Display selected file information
     * @param {File} file - The selected file
     */
    function displayFileInfo(file) {
        $fileName.text(file.name);
        $fileSize.text(formatFileSize(file.size));
        $fileInfo.removeClass('d-none');
        $dropzone.addClass('has-file');
    }
}

/**
 * Format file size in human-readable format
 * @param {number} bytes - File size in bytes
 * @returns {string} Formatted file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Handle restore form submission
 * @param {jQuery} $form - The form element
 */
function handleRestoreSubmit($form) {
    var formData = new FormData($form[0]);
    var file = document.getElementById('restoreFile').files[0];
    
    // Validate file is selected
    if (!file) {
        window.CRM.notify(i18next.t('Please select a backup file to restore'), {
            type: 'error',
            delay: 3000
        });
        return;
    }
    
    // Validate file size if browser supports FileReader
    if (window.FileReader && file.size > window.CRM.maxUploadSizeBytes) {
        window.CRM.DisplayErrorMessage("/api/database/restore", {
            message: i18next.t('The selected file exceeds this servers maximum upload size of') + ': ' + window.CRM.maxUploadSize
        });
        return;
    }

    // Update UI to show restore is running
    setRestoreStatus('running');

    $.ajax({
        url: window.CRM.root + '/api/database/restore',
        type: 'POST',
        data: formData,
        cache: false,
        contentType: false,
        enctype: 'multipart/form-data',
        processData: false,
        dataType: 'json'
    })
    .done(function(data) {
        // Show any messages from the restore process in the modal
        if (data.Messages && data.Messages.length > 0) {
            var $messages = $('#restoreModalMessages');
            $messages.empty();
            $.each(data.Messages, function(index, value) {
                $('<div>')
                    .addClass('alert alert-warning mt-2')
                    .html('<i class="fa-solid fa-exclamation-triangle mr-2"></i>' + value)
                    .appendTo($messages);
            });
        }
        
        // Hide the running status
        setRestoreStatus('idle');
        
        // Show success modal overlay
        $('#restoreSuccessModal').modal('show');
        
        // Log out the user via API
        $.ajax({
            url: window.CRM.root + '/session/end',
            type: 'GET'
        });
        
        // Start countdown and redirect
        var countdown = 5;
        var countdownInterval = setInterval(function() {
            countdown--;
            $('#redirectCountdown strong').text(countdown);
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = window.CRM.root + '/';
            }
        }, 1000);
    })
    .fail(function(xhr, status, error) {
        let errorMessage = i18next.t('Restore failed. Please check the backup file and try again.');
        
        if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMessage = xhr.responseJSON.message;
        }
        
        setRestoreStatus('error', errorMessage);
    });
}

/**
 * Set the restore status display
 * @param {string} status - One of: 'idle', 'running', 'complete', 'error'
 * @param {string} [errorMessage] - Optional error message for error status
 */
function setRestoreStatus(status, errorMessage) {
    $('#statusIdle, #statusRunning, #statusComplete, #statusError').addClass('d-none');
    $('#statusCard').removeClass('card-primary card-success card-danger card-warning');
    
    switch(status) {
        case 'idle':
            $('#statusIdle').removeClass('d-none');
            break;
        case 'running':
            $('#statusRunning').removeClass('d-none');
            $('#statusCard').addClass('card-warning');
            break;
        case 'complete':
            $('#statusComplete').removeClass('d-none');
            $('#statusCard').addClass('card-success');
            break;
        case 'error':
            if (errorMessage) {
                $('#errorMessage').text(errorMessage);
            }
            $('#statusError').removeClass('d-none');
            $('#statusCard').addClass('card-danger');
            break;
    }
}

// Export functions to global scope
window.RestoreDatabase = {
    setRestoreStatus,
    formatFileSize
};
