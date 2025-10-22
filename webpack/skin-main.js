/**
 * webpack/skin-main.js
 * 
 * Entry point for all UI pages (authenticated and unauthenticated)
 * 
 * Bundled Assets:
 *   - All CSS: Bootstrap, AdminLTE, FontAwesome, Select2, DataTables, DatePicker, Toggle, Steps
 *   - All JS Plugins: jQuery, AdminLTE, InputMask, DatePicker, DateRangePicker, Select2, DataTables, 
 *                     Bootbox, BootstrapNotify, BootstrapValidator, Moment.js, PhotoUploader
 *   - CKEditor: Rich text editor (included in all builds for simplicity)
 *   - i18next: Internationalization support
 *   - Custom ChurchCRM SCSS
 * 
 * Strategy: Import all CSS and plugins directly from node_modules via webpack
 * instead of copying files to src/skin/external/
 */

// ============================================================================
// MAIN SCSS (ChurchCRM styles)
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

// jQuery Photo Uploader (Photo upload component CSS)
import 'jquery-photo-uploader/dist/PhotoUploader.css';

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

// jQuery Photo Uploader (Photo upload component JS)
require('jquery-photo-uploader/dist/PhotoUploader.js');

// CKEditor (Rich text editor - included in all builds for simplicity)
require('ckeditor4');

export {};
