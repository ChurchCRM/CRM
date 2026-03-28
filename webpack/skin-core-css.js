/**
 * Shared CSS-only module for ChurchCRM skin bundles.
 *
 * Imported by both skin-core.js (LTR) and skin-rtl.js (RTL).
 * Contains all shared CSS imports except the Tabler core CSS,
 * which differs between LTR (tabler.min.css) and RTL (tabler.rtl.min.css)
 * and is imported in each entry file before this module.
 *
 * When adding a new shared CSS dependency, add it here and it will
 * automatically appear in both output bundles.
 */

// Import FontAwesome CSS - webfonts are automatically bundled by webpack
import "@fortawesome/fontawesome-free/css/all.min.css";

// Import flag-icons CSS - flags are automatically bundled by webpack
import "flag-icons/css/flag-icons.min.css";

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

// Import Quill editor CSS
import "quill/dist/quill.snow.css";

// ChurchCRM SCSS overrides and custom styles
import "../src/skin/churchcrm.scss";
