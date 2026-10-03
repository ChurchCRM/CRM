/**
 * jQuery plus Bootstrap's jQuery plugin bridge.
 * Webpack gives each entry its own jQuery. Alias "jquery" to this module so
 * $(...).tooltip / .modal / .collapse work in every bundle.
 *
 * Export jQuery before loading Bootstrap. Bootstrap imports "jquery", which
 * is this file, and a live ESM import runs before this body finishes.
 */
// churchcrm.min.js runs first and installs window.jQuery. Later entries must
// reuse that object so DataTables and the other plugins attached there stay
// on the jQuery the page calls.
const existing = typeof window !== "undefined" ? window.jQuery : undefined;
if (existing && existing.fn) {
  module.exports = existing;
} else {
  const $ = require("jquery-core");
  if (typeof window !== "undefined") {
    window.jQuery = window.$ = $;
  }

  module.exports = $;

  const bootstrap = require("bootstrap");

  const plugins = [
    bootstrap.Alert,
    bootstrap.Button,
    bootstrap.Carousel,
    bootstrap.Collapse,
    bootstrap.Dropdown,
    bootstrap.Modal,
    bootstrap.Offcanvas,
    bootstrap.Popover,
    bootstrap.ScrollSpy,
    bootstrap.Tab,
    bootstrap.Toast,
    bootstrap.Tooltip,
  ];

  for (const Plugin of plugins) {
    if (typeof Plugin?.jQueryInterface === "function" && typeof Plugin.NAME === "string") {
      $.fn[Plugin.NAME] = Plugin.jQueryInterface;
      $.fn[Plugin.NAME].Constructor = Plugin;
    }
  }
}
