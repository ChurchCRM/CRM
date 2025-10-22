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

// Import FontAwesome CSS and webfonts
import '@fortawesome/fontawesome-free/css/all.min.css';
import '@fortawesome/fontawesome-free/js/all.min.js';

import '../src/skin/churchcrm.scss';

// No additional exports needed - this bundle is for CSS/assets only
export {};
