/**
 * Separate webpack entry for photo uploader functionality
 *
 * This creates a standalone bundle that can be loaded only on pages that need it.
 * The createPhotoUploader function is exposed via window._CRM_createPhotoUploader
 *
 * Usage:
 * Include the compiled bundle in your page, for example:
 * <script src="/skin/v2/photo-uploader.min.js"></script>
 * (When rendering server-side you may prepend the application's root path.)
 */

// Import Uppy CSS
// @uppy/core v5+ requires ./css/ export path instead of ./dist/
import "@uppy/core/css/style.min.css";
import "@uppy/dashboard/dist/style.min.css";
import "@uppy/webcam/dist/style.min.css";

import { createPhotoUploader } from "./photo-uploader.js";

// Expose to global scope - this runs before Header-function.php
// Store in temporary location that won't be overwritten
if (typeof window !== "undefined") {
  window._CRM_createPhotoUploader = createPhotoUploader;
}
