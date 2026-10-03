/**
 * jQuery plus Bootstrap's jQuery plugin bridge.
 * Webpack gives each entry its own jQuery. Alias "jquery" to this module so
 * $(...).tooltip / .modal / .collapse work in every bundle.
 *
 * Export jQuery before loading Bootstrap. Bootstrap imports "jquery", which
 * is this file, and a live ESM import runs before this body finishes.
 */
const $ = require("jquery-core");

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
