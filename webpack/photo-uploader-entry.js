/**
 * Separate webpack entry for photo uploader functionality
 * 
 * This creates a standalone bundle that can be loaded only on pages that need it.
 * The createPhotoUploader function is exposed via window._CRM_createPhotoUploader
 * 
 * Usage in PHP:
 * <script src="<?= SystemURLs::getRootPath() ?>/skin/v2/photo-uploader.min.js"></script>
 */

// Import Uppy CSS
import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';
import '@uppy/webcam/dist/style.min.css';

import { createPhotoUploader } from './photo-uploader.js';

// Expose to global scope - this runs before Header-function.php
// Store in temporary location that won't be overwritten
if (typeof window !== 'undefined') {
    window._CRM_createPhotoUploader = createPhotoUploader;
}
