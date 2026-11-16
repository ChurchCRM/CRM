import { initConfirmForms } from "./common/confirm-forms";

initConfirmForms();

window.CRM.onLocalesReady(() => {
  // Auto-submit filter form on select change (CSP-safe, no inline onchange)
  document.querySelectorAll("#fundraiserFilterForm select").forEach((sel) => {
    sel.addEventListener("change", () => {
      document.getElementById("fundraiserFilterForm").submit();
    });
  });

  // Active fundraisers DataTable
  const dtCfg = {
    order: [[2, "desc"]],
    columnDefs: [
      { targets: -1, orderable: false, searchable: false },
      { targets: 6, orderable: false },
    ],
    pageLength: 25,
  };
  $.extend(dtCfg, window.CRM.plugin.dataTable);
  $("#fundraisers").DataTable(dtCfg);

  // Archive fundraisers DataTable — not rendered at all when a status filter is
  // active (see fundraiser-list.php), so guard on the element existing.
  // autoWidth:false prevents column-width miscomputation when the container is
  // hidden (Bootstrap collapse). columns.adjust() is called on the
  // shown.bs.collapse event to finalise widths when the user first expands it.
  if (document.getElementById("fundraisers-archive")) {
    const dtCfgArc = {
      order: [[2, "desc"]],
      autoWidth: false,
      columnDefs: [
        { targets: -1, orderable: false, searchable: false },
        { targets: 6, orderable: false },
      ],
      pageLength: 25,
    };
    $.extend(dtCfgArc, window.CRM.plugin.dataTable);
    const archiveTable = $("#fundraisers-archive").DataTable(dtCfgArc);

    // Adjust column widths when the collapse reveals the table for the first time.
    document.getElementById("archiveCollapse")?.addEventListener(
      "shown.bs.collapse",
      () => {
        archiveTable.columns.adjust();
      },
      { once: true },
    );
  }
});
