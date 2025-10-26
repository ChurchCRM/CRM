/**
 * Photo Uploader using Uppy
 * Simple, modern photo upload with webcam support
 * Using MODAL mode with proper CSS
 * Converts images to base64 for ChurchCRM API
 */

import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import Webcam from '@uppy/webcam';

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
    });

    // Custom upload handler that converts image to base64
    uppy.on('upload', (data) => {
        // Get all files
        const files = Object.values(uppy.getState().files);
        if (!files || files.length === 0) {
            console.error('No files to upload');
            return;
        }
        
        const file = files[0];
        
        uppy.setFileState(file.id, {
            progress: { uploadStarted: Date.now(), uploadComplete: false, percentage: 0 }
        });

        const reader = new FileReader();
        
        reader.onload = function(e) {
            const base64 = e.target.result;
            
            // Send base64 image to API (backend now handles format detection)
            fetch(config.uploadUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ imgBase64: base64 })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || `Upload failed: ${response.statusText}`);
                    }).catch(() => {
                        throw new Error(`Upload failed: ${response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                uppy.setFileState(file.id, {
                    progress: { uploadComplete: true, percentage: 100 },
                    uploadURL: config.uploadUrl,
                    response: { body: data }
                });
                
                uppy.emit('upload-success', file, { body: data });
                uppy.emit('complete', { successful: [file], failed: [] });
            })
            .catch(error => {
                console.error('Upload error:', error);
                uppy.emit('upload-error', file, error);
                uppy.emit('complete', { successful: [], failed: [file] });
            });
        };
        
        reader.onerror = function(error) {
            console.error('FileReader error:', error);
            uppy.emit('upload-error', file, new Error('Failed to read file'));
            uppy.emit('complete', { successful: [], failed: [file] });
        };
        
        reader.readAsDataURL(file.data);
    });

    // Handle upload completion
    uppy.on('complete', (result) => {
        if (result.successful && result.successful.length > 0) {
            if (config.onComplete && typeof config.onComplete === 'function') {
                setTimeout(() => config.onComplete(result), 500);
            }
        }
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
