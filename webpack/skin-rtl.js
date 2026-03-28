/**
 * RTL webpack entry point for ChurchCRM assets
 *
 * Loads the right-to-left Tabler CSS variant then delegates all shared
 * CSS and JavaScript to skin-core.js.  To add a new shared dependency,
 * edit skin-core.js — changes there will automatically appear in both
 * the standard (churchcrm.min.*) and RTL (churchcrm-rtl.min.*) bundles.
 *
 * Used by pages where the active locale has isRTL=true (e.g., Arabic, Hebrew).
 * The JS bundle from skin-main.js is still used; only the CSS differs.
 */

// Import Tabler RTL CSS (Bootstrap 5 admin UI — RTL variant)
import "@tabler/core/dist/css/tabler.rtl.min.css";

// Import all shared CSS and JS
import "./skin-core";
