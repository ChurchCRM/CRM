/**
 * Main webpack entry point for ChurchCRM assets (LTR / default)
 *
 * Loads the standard left-to-right Tabler CSS then delegates all shared
 * CSS and JavaScript to skin-core.js.  To add a new shared dependency,
 * edit skin-core.js — changes there will automatically appear in both
 * the standard (churchcrm.min.*) and RTL (churchcrm-rtl.min.*) bundles.
 *
 * Used by both logged-in and logged-out pages to provide core styling
 * and functionality when the active locale is LTR.
 */

// Import Tabler CSS (Bootstrap 5 admin UI — LTR variant)
import "@tabler/core/dist/css/tabler.min.css";

// Import all shared CSS and JS
import "./skin-core";
