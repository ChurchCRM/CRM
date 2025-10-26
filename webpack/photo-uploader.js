/**
 * Photo Uploader using Uppy
 * Simple, modern photo upload with webcam support
 * Using MODAL mode with proper CSS
 */

import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import Webcam from '@uppy/webcam';
import XHRUpload from '@uppy/xhr-upload';

/**
 * Create a photo uploader instance with modal dashboard
 * 
 * @param {Object} config - Configuration options
 * @param {string} config.uploadUrl - Upload endpoint URL
 * @param {number} config.maxFileSize - Maximum file size in bytes (default: 5MB)
 * @param {number} config.photoWidth - Target photo width
 * @param {number} config.photoHeight - Target photo height
 * @param {Function} config.onComplete - Callback after successful upload
 * @returns {Object} - Uppy instance with show/hide methods
 */
export function createPhotoUploader(config) {
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
        inline: false, // Use modal mode
        trigger: null, // Don't auto-bind to a trigger
        proudlyDisplayPoweredByUppy: false,
        note: `Max file size: ${Math.round((config.maxFileSize || 5000000) / 1000000)}MB`,
        closeModalOnClickOutside: true,
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

    // Get the Dashboard plugin instance for modal control
    const dashboard = uppy.getPlugin('Dashboard');

    // Return wrapper with show/hide methods
    return {
        uppy: uppy,
        show: () => dashboard.openModal(),
        hide: () => dashboard.closeModal()
    };
}
