/**
 * RTL webpack entry point for ChurchCRM assets (CSS only)
 *
 * Produces churchcrm-rtl.min.css containing the RTL Tabler CSS plus all
 * shared component CSS (icons, DataTables, TomSelect, etc.).  The JS
 * bundle (churchcrm.min.js) is shared and loaded on all pages regardless
 * of locale direction — only the CSS differs.
 *
 * skin-core-css.js is the single source of truth for shared CSS.
 * Any new shared CSS dependency belongs there.
 */

// Import Tabler RTL CSS (Bootstrap 5 admin UI — RTL variant)
import "@tabler/core/dist/css/tabler.rtl.min.css";
import "@tabler/core/dist/css/tabler-themes.rtl.min.css";

// Import all shared CSS (no JS — JS stays in churchcrm.min.js via skin-main.js)
import "./skin-core-css";
