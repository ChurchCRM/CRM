/**
 * Photo Uploader using Uppy
 * Replaces the legacy jQuery-Photo-Uploader with a modern Uppy implementation
 */

import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import Webcam from '@uppy/webcam';
import XHRUpload from '@uppy/xhr-upload';

// Import Uppy styles
import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';
import '@uppy/webcam/dist/style.min.css';

/**
 * Initialize PhotoUploader with Uppy
 * This function mimics the jQuery PhotoUploader API for backward compatibility
 * 
 * @param {Object} options - Configuration options
 * @param {string} options.url - Upload URL endpoint
 * @param {number} options.maxPhotoSize - Maximum file size in bytes
 * @param {number} options.photoWidth - Target photo width
 * @param {number} options.photoHeight - Target photo height
 * @param {Function} options.done - Callback function called after successful upload
 * @returns {Object} - PhotoUploader instance with show() method
 */
export function initializePhotoUploader(options) {
    const uppyInstance = new Uppy({
        id: 'photo-uploader',
        autoProceed: false,
        allowMultipleUploadBatches: false,
        restrictions: {
            maxNumberOfFiles: 1,
            maxFileSize: options.maxPhotoSize || 5000000, // Default 5MB
            allowedFileTypes: ['image/*']
        }
    })
    .use(Dashboard, {
        inline: false,
        trigger: null, // We'll trigger manually
        showProgressDetails: true,
        proudlyDisplayPoweredByUppy: false,
        note: `Max photo size: ${Math.round((options.maxPhotoSize || 5000000) / 1000000)}MB`,
        height: 470,
        metaFields: [],
        browserBackButtonClose: true,
        closeModalOnClickOutside: true,
        closeAfterFinish: false,
        disablePageScrollWhenModalOpen: true,
        animateOpenClose: true,
        fileManagerSelectionType: 'files',
        showLinkToFileUploadResult: false,
        showRemoveButtonAfterComplete: true,
        hideUploadButton: false,
        hideCancelButton: false,
        hideRetryButton: false,
        hidePauseResumeButton: true,
        hideProgressAfterFinish: false,
        doneButtonHandler: null,
        locale: {
            strings: {
                dropPasteFiles: 'Drop files here or %{browse}',
                dropPasteFolders: 'Drop files here or %{browse}',
                dropPasteBoth: 'Drop files here or %{browse}',
                dropPasteImportFiles: 'Drop files here, %{browse} or import from:',
                dropPasteImportFolders: 'Drop files here, %{browse} or import from:',
                dropPasteImportBoth: 'Drop files here, %{browse} or import from:',
                browse: 'browse',
                uploadComplete: 'Upload complete',
                uploadPaused: 'Upload paused',
                resumeUpload: 'Resume upload',
                pauseUpload: 'Pause upload',
                retryUpload: 'Retry upload',
                cancelUpload: 'Cancel upload',
                xFilesSelected: {
                    0: '%{smart_count} file selected',
                    1: '%{smart_count} files selected'
                },
                uploadingXFiles: {
                    0: 'Uploading %{smart_count} file',
                    1: 'Uploading %{smart_count} files'
                },
                processingXFiles: {
                    0: 'Processing %{smart_count} file',
                    1: 'Processing %{smart_count} files'
                },
                uploading: 'Uploading',
                complete: 'Complete',
                uploadFailed: 'Upload failed',
                uploadPausedResumeText: 'Resume upload',
                uploadPausedCancelText: 'Cancel upload',
                paused: 'Paused',
                error: 'Error',
                retry: 'Retry',
                cancel: 'Cancel',
                done: 'Done',
                filesUploadedOfTotal: {
                    0: '%{complete} of %{smart_count} file uploaded',
                    1: '%{complete} of %{smart_count} files uploaded'
                },
                dataUploadedOfTotal: '%{complete} of %{total}',
                xTimeLeft: '%{time} left',
                uploadXFiles: {
                    0: 'Upload %{smart_count} file',
                    1: 'Upload %{smart_count} files'
                },
                uploadXNewFiles: {
                    0: 'Upload +%{smart_count} file',
                    1: 'Upload +%{smart_count} files'
                },
                selectAllFilesFromFolderNamed: 'Select all files from folder %{name}',
                unselectAllFilesFromFolderNamed: 'Unselect all files from folder %{name}',
                selectAllFiles: 'Select all files',
                unselectAllFiles: 'Unselect all files',
                selectFiles: 'Select files',
                closeModal: 'Close Modal',
                importFrom: 'Import from %{name}',
                dashboardWindowTitle: 'Upload Photo',
                dashboardTitle: 'Upload Photo',
                myDevice: 'My Device',
                takePicture: 'Take a picture',
                takePictureBtn: 'Take Picture',
                recordVideo: 'Record a video',
                recording: 'Recording',
                stopRecording: 'Stop Recording',
                submitRecordedFile: 'Submit',
                streamActive: 'Stream active',
                streamPassive: 'Stream passive',
                micDisabled: 'Microphone access denied by user',
                cameraDisabled: 'Camera access denied by user'
            }
        }
    })
    .use(Webcam, {
        countdown: false,
        modes: ['picture'],
        mirror: true,
        videoConstraints: {
            facingMode: 'user',
            width: { ideal: options.photoWidth || 800 },
            height: { ideal: options.photoHeight || 800 }
        },
        preferredImageMimeType: 'image/jpeg',
        preferredVideoMimeType: null,
        showVideoSourceDropdown: true,
        showRecordingLength: false,
        locale: {}
    })
    .use(XHRUpload, {
        endpoint: options.url,
        method: 'POST',
        formData: true,
        fieldName: 'photo',
        headers: {
            'Accept': 'application/json'
        },
        limit: 1,
        timeout: 30 * 1000, // 30 seconds
        withCredentials: true,
        responseType: 'json',
        getResponseError: (responseText, response) => {
            try {
                const json = JSON.parse(responseText);
                return json.error || json.message || 'Upload failed';
            } catch (e) {
                return response.statusText || 'Upload failed';
            }
        }
    });

    // Handle successful upload
    uppyInstance.on('upload-success', (file, response) => {
        console.log('Upload successful:', file, response);
    });

    // Handle complete event
    uppyInstance.on('complete', (result) => {
        console.log('Upload complete:', result);
        if (result.successful && result.successful.length > 0) {
            // Call the done callback if provided
            if (options.done && typeof options.done === 'function') {
                setTimeout(() => {
                    options.done(result);
                }, 500);
            }
        }
    });

    // Handle errors
    uppyInstance.on('upload-error', (file, error, response) => {
        console.error('Upload error:', file, error, response);
    });

    // Return an object that mimics the jQuery PhotoUploader API
    return {
        show: function() {
            const dashboard = uppyInstance.getPlugin('Dashboard');
            if (dashboard) {
                dashboard.openModal();
            }
        },
        hide: function() {
            const dashboard = uppyInstance.getPlugin('Dashboard');
            if (dashboard) {
                dashboard.closeModal();
            }
        },
        destroy: function() {
            uppyInstance.close();
        },
        uppy: uppyInstance
    };
}

// jQuery plugin wrapper for backward compatibility
if (typeof window !== 'undefined' && window.jQuery) {
    (function($) {
        $.fn.PhotoUploader = function(options) {
            const photoUploader = initializePhotoUploader(options);
            
            // Store the instance on the element
            this.data('photoUploader', photoUploader);
            
            return photoUploader;
        };
    })(window.jQuery);
}
