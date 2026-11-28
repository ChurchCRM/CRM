window.CRM.onLocalesReady(() => {
  const cartTableConfig = $.extend(true, {}, window.CRM.plugin.dataTable);
  cartTableConfig.buttons[0].action = () => {
    bootstrap.Modal.getOrCreateInstance(document.getElementById("cartCsvExportModal")).show();
  };
  $("#cart-listing-table").DataTable(cartTableConfig);
});
