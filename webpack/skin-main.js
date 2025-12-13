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

// Import jQuery and expose it globally for legacy code compatibility
import $ from 'jquery';
window.jQuery = window.$ = $;

// Import FontAwesome CSS - webfonts are automatically bundled by webpack
import '@fortawesome/fontawesome-free/css/all.min.css';

// Import flag-icons CSS - flags are automatically bundled by webpack
import 'flag-icons/css/flag-icons.min.css';

// Import Select2 Bootstrap 4 theme
import '@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css';

// Import React DatePicker CSS - required for calendar styling
import 'react-datepicker/dist/react-datepicker.min.css';

// Import Quill editor CSS and make it available globally
import 'quill/dist/quill.snow.css';

import { initializeQuillEditor } from './quill-editor.js';

// Import notifier module (Notyf wrapper for notifications)
import '../src/skin/js/notifier.js';

// Import cart management module
import '../src/skin/js/cart.js';

// Import avatar loader (for person and family photos with client-side initials/gravatar)
import './avatar-loader';

// Import photo utilities (lightbox and delete functions for Person/Family views)
import { showPhotoLightbox, deletePhoto } from './photo-utils';

import '../src/skin/churchcrm.scss';

// Make Quill initialization function available globally
if (typeof window !== 'undefined') {
    // Create a queue for editors that are initialized before the function is available
    window._quillInitQueue = window._quillInitQueue || [];
    
    window.initializeQuillEditor = initializeQuillEditor;
    
    // Make photo utilities available globally
    window.CRM = window.CRM || {};
    window.CRM.showPhotoLightbox = showPhotoLightbox;
    window.CRM.deletePhoto = deletePhoto;
    
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
