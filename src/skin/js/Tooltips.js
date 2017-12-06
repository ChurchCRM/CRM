$(function() {
	// Automatically convert all items that want Bootstrap tooltips.
  // All they need is the `data-tooltip` attribute and a `title`.
  if (window.CRM.showTooltip) {
	$('[data-toggle="tooltip"]').tooltip();
  } else {
  	$('[data-toggle="tooltip"]').tooltip('disable');
  }
});
