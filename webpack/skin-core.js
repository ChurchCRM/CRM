/**
 * Shared JS module for ChurchCRM skin bundles.
 *
 * This module is imported by skin-main.js (LTR entry point) only.
 * It contains all shared JavaScript and delegates all shared CSS to
 * skin-core-css.js so the RTL entry (skin-rtl.js) can import only
 * the CSS without duplicating the JavaScript bundle.
 *
 * When adding a new shared JS dependency, add it here.
 * When adding a new shared CSS dependency, add it to skin-core-css.js.
 */

// Import jQuery and expose it globally for legacy code compatibility
import $ from "jquery";
window.jQuery = window.$ = $;

// Import ApexCharts - Tabler-recommended charting library (replacing Chart.js)
import ApexCharts from "apexcharts";
window.ApexCharts = ApexCharts;

// Import Tabler JS (Bootstrap 5 + Popper bundled) — replaces AdminLTE JS
// Must come after jQuery so legacy jQuery plugins can still work
import * as tabler from "@tabler/core";
window.bootstrap = tabler.bootstrap;

// Import all shared CSS (icons, DataTables, TomSelect, DatePicker, etc.)
import "./skin-core-css";

import { initializeQuillEditor } from "./quill-editor.js";

// Import notifier module (Notyf wrapper for notifications)
import "../src/skin/js/notifier.js";

// Import cart management module
import "../src/skin/js/cart.js";

// Import avatar loader (for person and family photos with client-side initials/gravatar)
import "./avatar-loader";

// Import photo utilities (lightbox and delete functions for Person/Family views)
import { showPhotoLightbox, deletePhoto } from "./photo-utils";

// Import form utilities (phone mask toggles, etc.)
import "../src/skin/js/form-utils.js";

// Import issue reporter (GitHub issue modal)
import "../src/skin/js/IssueReporter.js";

// Make Quill initialization function available globally
if (typeof window !== "undefined") {
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
        console.error("Error initializing queued Quill editor:", e);
      }
    });
    window._quillInitQueue = [];
  }
}

// Tom Select — expose globally and provide jQuery bridge
import TomSelect from "tom-select";

if (typeof window !== "undefined") {
  window.TomSelect = TomSelect;

  /**
   * jQuery bridge for TomSelect — drop-in replacement for $.fn.select2()
   * Supports the most common Select2 option patterns used in this codebase.
   *
   * Usage:  $('#mySelect').tomselect({ placeholder: '...', allowClear: true });
   *         $('#mySelect').tomselect('destroy');
   *
   * The bridge stores TomSelect instances on the DOM element as `element.tomselect`.
   */
  if (window.$ && window.$.fn) {
    window.$.fn.tomselect = function (optionsOrCommand) {
      // Handle string commands: 'destroy', 'val', etc.
      if (typeof optionsOrCommand === "string") {
        return this.each(function () {
          const ts = this.tomselect;
          if (!ts) return;
          if (optionsOrCommand === "destroy") {
            ts.destroy();
          } else if (optionsOrCommand === "clear") {
            ts.clear(true);
          }
        });
      }

      const opts = optionsOrCommand || {};

      return this.each(function () {
        // Skip if already initialized
        if (this.tomselect) return;

        const tsOpts = {};

        // Placeholder
        if (opts.placeholder) {
          tsOpts.placeholder = opts.placeholder;
        } else if (this.dataset && this.dataset.placeholder) {
          tsOpts.placeholder = this.dataset.placeholder;
        }

        // Allow clearing
        if (opts.allowClear) {
          tsOpts.allowEmptyOption = true;
        }

        // Multiple selection — auto-detect from <select multiple>
        if (this.multiple) {
          tsOpts.plugins = tsOpts.plugins || [];
          tsOpts.plugins.push("remove_button");
        }

        // Tags mode (e.g. disallowed passwords)
        if (opts.tags) {
          tsOpts.create = true;
          tsOpts.persist = false;
          if (opts.tokenSeparators) {
            tsOpts.delimiter = opts.tokenSeparators[0] || ",";
            tsOpts.createOnBlur = true;
            tsOpts.plugins = tsOpts.plugins || [];
            tsOpts.plugins.push("remove_button");
          }
        }

        // Pre-loaded data array (for selects populated via JS data)
        if (opts.data && Array.isArray(opts.data)) {
          tsOpts.options = opts.data.map((item) => {
            if (typeof item === "string") {
              return { value: item, text: item };
            }
            return { value: String(item.id), text: item.text };
          });
          tsOpts.items = opts.data.map((item) => (typeof item === "string" ? item : String(item.id)));
          tsOpts.valueField = "value";
          tsOpts.labelField = "text";
          tsOpts.searchField = "text";
        }

        // Dropdown parent (for modals like bootbox)
        if (opts.dropdownParent) {
          tsOpts.dropdownParent = opts.dropdownParent instanceof $ ? opts.dropdownParent[0] : opts.dropdownParent;
        }

        // Merge any extra TomSelect-native options
        if (opts.tsOptions) {
          Object.assign(tsOpts, opts.tsOptions);
        }

        new TomSelect(this, tsOpts);
      });
    };
  }
}
