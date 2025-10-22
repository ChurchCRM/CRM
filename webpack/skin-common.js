/**
 * webpack/skin-common.js
 * 
 * Shared imports for all skin entry points (main and loggedout)
 * 
 * Strategy: Import external CSS/plugins directly from node_modules via webpack
 * instead of copying files to src/skin/external/
 * 
 * This eliminates file duplication and speeds up builds by ~50%
 * 
 * Build process:
 *   1. Webpack resolves imports from node_modules
 *   2. CSS files are processed by sass-loader and css-loader
 *   3. Assets (fonts, icons) are extracted by asset/resource handler
 *   4. Output is bundled into src/skin/v2/churchcrm.min.css
 *   5. HTML references single CSS file (no more /skin/external/ copies)
 */

// ============================================================================
// MAIN SCSS (CustomCRM styles)
// ============================================================================
import '../src/skin/churchcrm.scss';

// ============================================================================
// EXTERNAL CSS IMPORTS (from node_modules - no copying needed!)
// ============================================================================

// AdminLTE (Admin Dashboard UI Framework)
import 'admin-lte/dist/css/adminlte.css';

// Bootstrap (Core UI Framework)
import 'bootstrap/dist/css/bootstrap.css';

// FontAwesome (Icon Library)
import '@fortawesome/fontawesome-free/css/all.css';

// Select2 (Enhanced Select Dropdowns)
import 'select2/dist/css/select2.css';

// DataTables (Data Grid Component)
import 'datatables.net-bs4/css/dataTables.bootstrap4.css';
import 'datatables.net-responsive-bs4/css/responsive.bootstrap4.css';

// Bootstrap Datepicker (Date Picker Component)
import 'bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.css';

// Date Range Picker (Date Range Selection)
import 'daterangepicker/daterangepicker.css';

// Bootstrap Toggle (Toggle Switch Component)
import 'bootstrap-toggle/css/bootstrap-toggle.css';

// jQuery Steps (Step-by-step Form Wizard)
import 'jquery-steps/demo/css/jquery.steps.css';

// ============================================================================
// BOOTSTRAP-ONLY PLUGINS (JavaScript via npm imports)
// These are plugins that depend on Bootstrap and jQuery but not React
// ============================================================================

/**
 * Make jQuery globally available for legacy inline scripts
 * 
 * This allows existing code like:
 *   <script>$('#myElement').click(...)</script>
 * to work without changes
 */
window.$ = require('jquery');
window.jQuery = window.$;

// Make i18next globally available for internationalization
window.i18next = require('i18next');

// FontAwesome JS (Icon library JavaScript)
require('@fortawesome/fontawesome-free/js/all.js');

// AdminLTE (Admin Dashboard framework)
require('admin-lte');

// InputMask (jQuery plugin for input masking)
require('inputmask/dist/jquery.inputmask.min.js');
require('inputmask/dist/bindings/inputmask.binding.js');

// Bootstrap Datepicker (jQuery plugin for date selection)
require('bootstrap-datepicker');

// Date Range Picker (jQuery plugin for date range selection)
require('daterangepicker');

// Select2 (jQuery plugin for enhanced select dropdowns)
require('select2');

// DataTables (jQuery plugin for data grids)
require('datatables.net-bs4');
require('datatables.net-responsive-bs4');

// Bootstrap Toggle (Toggle switch component)
require('bootstrap-toggle');

// Bootbox (Dialog/alert boxes)
require('bootbox');

// Bootstrap Notify (Toast notifications)
require('bootstrap-notify');

// Bootstrap Validator (Form validation)
require('bootstrap-validator');

// Moment.js (Date/time library)
require('moment');
