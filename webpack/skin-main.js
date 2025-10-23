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

// Import FontAwesome CSS - webfonts are automatically bundled by webpack
import '@fortawesome/fontawesome-free/css/all.min.css';

// Import flag-icons CSS - flags are automatically bundled by webpack
import 'flag-icons/css/flag-icons.min.css';

// Import Select2 Bootstrap 4 theme
import '@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css';

import '../src/skin/churchcrm.scss';

// Set global Select2 defaults for Bootstrap 4 theme
// This needs to run after jQuery and Select2 are loaded
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', function() {
        if (window.$ && window.$.fn && window.$.fn.select2) {
            window.$.fn.select2.defaults.set("theme", "bootstrap4");
        }
    });
}

// No additional exports needed - this bundle is for core CSS and JS assets
export {};
