/**
 * Photo Uploader using Uppy
 * Simple, modern photo upload with webcam support
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
 * Create a photo uploader instance
 * 
 * @param {Object} config - Configuration options
 * @param {string} config.uploadUrl - Upload endpoint URL
 * @param {number} config.maxFileSize - Maximum file size in bytes (default: 5MB)
 * @param {number} config.photoWidth - Target photo width
 * @param {number} config.photoHeight - Target photo height
 * @param {Function} config.onComplete - Callback after successful upload
 * @returns {Object} - Uppy instance with show() and hide() methods
 */
export function createPhotoUploader(config) {
    // Add custom styles to ensure modal appears above all other elements (only once)
    if (!document.getElementById('uppy-modal-fix-styles')) {
        const style = document.createElement('style');
        style.id = 'uppy-modal-fix-styles';
        style.textContent = `
            /* Only apply positioning when modal is NOT hidden */
            .uppy-Dashboard--modal:not([aria-hidden="true"]) {
                z-index: 9999 !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }
            .uppy-Dashboard--modal .uppy-Dashboard-overlay {
                z-index: 9998 !important;
            }
        `;
        document.head.appendChild(style);
    }
    
    const uppy = new Uppy({
        id: 'photo-uploader',
        autoProceed: false,
        restrictions: {
            maxNumberOfFiles: 1,
            maxFileSize: config.maxFileSize || 5000000, // Default 5MB
            allowedFileTypes: ['image/*']
        }
    })
    .use(Dashboard, {
        inline: false,
        trigger: null,
        proudlyDisplayPoweredByUppy: false,
        note: `Max file size: ${Math.round((config.maxFileSize || 5000000) / 1000000)}MB`,
        height: 470,
        closeModalOnClickOutside: true,
        closeAfterFinish: false,
        animateOpenClose: true,
        showProgressDetails: true,
        hideUploadButton: false,
        hideCancelButton: false,
        hideRetryButton: false,
        hidePauseResumeButton: false,
        disableStatusBar: false,
        locale: {
            strings: {
                dashboardWindowTitle: 'Upload Photo',
                dashboardTitle: 'Upload Photo'
            }
        }
    })
    .use(Webcam, {
        countdown: false,
        modes: ['picture'],
        mirror: true,
        videoConstraints: {
            facingMode: 'user',
            width: { ideal: config.photoWidth || 800 },
            height: { ideal: config.photoHeight || 800 }
        },
        preferredImageMimeType: 'image/jpeg'
    })
    .use(XHRUpload, {
        endpoint: config.uploadUrl,
        method: 'POST',
        formData: true,
        fieldName: 'photo',
        headers: { 'Accept': 'application/json' },
        limit: 1,
        timeout: 30000,
        withCredentials: true,
        responseType: 'json'
    });

    // Handle upload completion
    uppy.on('complete', (result) => {
        if (result.successful && result.successful.length > 0) {
            if (config.onComplete && typeof config.onComplete === 'function') {
                setTimeout(() => config.onComplete(result), 500);
            }
        }
    });

    // Handle errors
    uppy.on('upload-error', (file, error) => {
        console.error('Upload error:', error);
    });

    // Handle Dashboard open/close for better focus management
    const dashboard = uppy.getPlugin('Dashboard');
    
    uppy.on('dashboard:modal-open', () => {
        // Clear any existing focus issues by letting Uppy handle it naturally
        // The dashboard will manage focus automatically
    });

    uppy.on('dashboard:modal-closed', () => {
        // Reset the uppy state to avoid focus retention
        uppy.cancelAll();
    });

    // Add convenience methods
    return {
        show() {
            if (!dashboard) {
                console.error('[photo-uploader] Dashboard plugin not found!');
                return;
            }
            dashboard.openModal();
        },
        hide() {
            if (dashboard) {
                dashboard.closeModal();
            }
        },
        destroy() {
            uppy.close();
        },
        instance: uppy
    };
}
