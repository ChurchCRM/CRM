/**
 * Main webpack entry point for ChurchCRM assets
 * 
 * This is the primary bundle that:
 * - Compiles all SCSS styles
 * - Imports external CSS from node_modules
 * - Bundles common JavaScript libraries
 * 
 * Used by both logged-in and logged-out pages to provide core styling and functionality.
 */

console.log('[skin-main.js] Module starting to load...');

// Import FontAwesome CSS - webfonts are automatically bundled by webpack
import '@fortawesome/fontawesome-free/css/all.min.css';
console.log('[skin-main.js] FontAwesome CSS imported');

// Import flag-icons CSS - flags are automatically bundled by webpack
import 'flag-icons/css/flag-icons.min.css';
console.log('[skin-main.js] Flag icons CSS imported');

// Import Select2 Bootstrap 4 theme
import '@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css';
console.log('[skin-main.js] Select2 theme imported');

// Import React DatePicker CSS - required for calendar styling
import 'react-datepicker/dist/react-datepicker.min.css';
console.log('[skin-main.js] React DatePicker CSS imported');

// Import Quill editor CSS and make it available globally
import 'quill/dist/quill.snow.css';
console.log('[skin-main.js] Quill CSS imported');

import { initializeQuillEditor } from './quill-editor.js';
console.log('[skin-main.js] Quill editor imported');

// Import Photo Uploader (Uppy)
import { createPhotoUploader } from './photo-uploader.js';
console.log('[skin-main.js] Photo uploader imported');

import '../src/skin/churchcrm.scss';
console.log('[skin-main.js] ChurchCRM SCSS imported');

// Immediate execution wrapper to ensure code runs
(function() {
    console.log('[skin-main.js] IIFE executing');
    
    // Ensure window.CRM namespace exists
    window.CRM = window.CRM || {};
    console.log('[skin-main.js] Setting up window.CRM namespace');

    // Make Quill initialization function available globally
    if (typeof window !== 'undefined') {
        // Create a queue for editors that are initialized before the function is available
        window._quillInitQueue = window._quillInitQueue || [];
        
        window.initializeQuillEditor = initializeQuillEditor;
        
        // Process any queued editors
        if (window._quillInitQueue && window._quillInitQueue.length > 0) {
            window._quillInitQueue.forEach(({ selector, options, callback }) => {
                try {
                    const editor = window.initializeQuillEditor(selector, options);
                    if (callback) callback(editor);
                } catch (e) {
                    console.error('Error initializing queued Quill editor:', e);
                }
            });
            window._quillInitQueue = [];
        }
    }

    // Make Photo Uploader available globally - assign immediately
    console.log('[skin-main.js] About to assign createPhotoUploader');
    console.log('[skin-main.js] createPhotoUploader function:', createPhotoUploader);
    window.CRM.createPhotoUploader = createPhotoUploader;
    console.log('[skin-main.js] Assignment complete. window.CRM.createPhotoUploader:', window.CRM.createPhotoUploader);
})();

// Set global Select2 defaults for Bootstrap 4 theme and language
// This needs to run after jQuery and Select2 are loaded
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', function() {
        if (window.$ && window.$.fn && window.$.fn.select2) {
            window.$.fn.select2.defaults.set("theme", "bootstrap4");
            
            // Set Select2 language based on current locale
            // The Select2 i18n files are bundled by Grunt into locale-specific JS files
            if (window.CRM && window.CRM.shortLocale) {
                window.$.fn.select2.defaults.set("language", window.CRM.shortLocale);
            }
        }
    });
}

// No additional exports needed - this bundle is for core CSS and JS assets
export {};
