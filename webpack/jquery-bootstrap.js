/**
 * jQuery plus Bootstrap's jQuery plugin bridge.
 * Webpack gives each entry its own jQuery, and Bootstrap 5 only patches
 * window.jQuery on DOMContentLoaded. Alias "jquery" to this module so
 * $(...).tooltip / .modal / .collapse work in every bundle.
 */
import * as bootstrap from "bootstrap";
import $ from "jquery-core";

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

export default $;
