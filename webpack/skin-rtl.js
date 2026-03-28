/**
 * RTL webpack entry point for ChurchCRM assets
 *
 * This is the RTL (right-to-left) CSS bundle that mirrors skin-main.js
 * but loads the Tabler RTL CSS instead of the default LTR CSS.
 *
 * Used by pages where the active locale has isRTL=true (e.g., Arabic, Hebrew).
 * The JS bundle from skin-main.js is still used; only the CSS differs.
 */

// Import FontAwesome CSS - webfonts are automatically bundled by webpack
import "@fortawesome/fontawesome-free/css/all.min.css";

// Import flag-icons CSS - flags are automatically bundled by webpack
import "flag-icons/css/flag-icons.min.css";

// Import Tabler RTL CSS (Bootstrap 5 admin UI — RTL variant)
import "@tabler/core/dist/css/tabler.rtl.min.css";

// Import Tabler Icons webfont - font files bundled automatically by webpack
import "@tabler/icons-webfont/dist/tabler-icons.min.css";

// DataTables Bootstrap 5 integration CSS
import "datatables.net-bs5/css/dataTables.bootstrap5.min.css";
import "datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css";
import "datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css";
import "datatables.net-select-bs5/css/select.bootstrap5.min.css";

// Tom Select CSS — Bootstrap 5 themed
import "tom-select/dist/css/tom-select.bootstrap5.css";

// Bootstrap DatePicker and DateRangePicker CSS
import "bootstrap-datepicker/dist/css/bootstrap-datepicker.standalone.min.css";
import "daterangepicker/daterangepicker.css";

// bs-stepper CSS
import "bs-stepper/dist/css/bs-stepper.min.css";

// Import React DatePicker CSS - required for calendar styling
import "react-datepicker/dist/react-datepicker.min.css";

// Import Quill editor CSS and make it available globally
import "quill/dist/quill.snow.css";

import "../src/skin/churchcrm.scss";
