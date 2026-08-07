/**
 * Finance: Deposit Search
 *
 * Handles:
 * - DataTable enhancement of the server-rendered deposits table
 * - Row checkbox selection (individual + Select All)
 * - Export button enable/disable with live selected count
 * - Bulk CSV / per-row OFX / per-row PDF export
 * - Filter persistence (repopulate form fields from URL params on page load)
 *
 * Requires: jQuery, DataTables, i18next, window.CRM (loaded globally)
 */

(function initDepositSearch() {
  var dataT = null;

  // ---------------------------------------------------------------------------
  // Utilities
  // ---------------------------------------------------------------------------

  /**
   * Read a single URL query parameter by name.
   * @param {string} name
   * @returns {string}
   */
  function _getQueryParam(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name) || "";
  }

  /**
   * Collect deposit IDs for all selected (checked) rows.
   * @returns {number[]}
   */
  function getSelectedIds() {
    var ids = [];
    $("#depositsTable tbody .row-select:checked").each(function () {
      ids.push(parseInt($(this).data("deposit-id"), 10));
    });
    return ids;
  }

  /**
   * Update the export buttons and the selected-count badge based on current
   * checkbox selection state.
   */
  function updateExportButtons() {
    var ids = getSelectedIds();
    var count = ids.length;
    var enabled = count > 0;

    $("#btnExportCSV, #btnExportOFX, #btnExportPDF").prop("disabled", !enabled);

    var $badge = $("#selectedCount");
    if (enabled) {
      $badge.text(`${count} ${i18next.t(count === 1 ? "row selected" : "rows selected")}`);
      $badge.css("display", "");
    } else {
      $badge.css("display", "none");
    }
  }

  // ---------------------------------------------------------------------------
  // DataTable
  // ---------------------------------------------------------------------------

  function initDataTable() {
    var config = {
      columns: [
        { orderable: false, searchable: false }, // checkbox column
        { type: "num" }, // ID
        { type: "date" }, // Date
        null, // Type
        null, // Comment
        { type: "num-fmt" }, // Total
        null, // Status
        null, // Teller
        { orderable: false, searchable: false }, // Actions
      ],
      order: [[1, "desc"]],
      columnDefs: [{ targets: [0, 8], className: "text-center" }],
    };
    $.extend(config, window.CRM.plugin.dataTable);

    dataT = $("#depositsTable").DataTable(config);
  }

  // ---------------------------------------------------------------------------
  // Selection
  // ---------------------------------------------------------------------------

  function bindSelectionHandlers() {
    // Individual row checkbox click — do NOT bubble to row-click handler
    $("#depositsTable tbody").on("change", ".row-select", () => {
      updateExportButtons();
      // Sync the header checkbox to reflect partial/full selection
      var total = $("#depositsTable tbody .row-select").length;
      var checked = $("#depositsTable tbody .row-select:checked").length;
      var $header = $("#selectAllCheckbox");
      $header.prop("checked", checked === total && total > 0);
      $header.prop("indeterminate", checked > 0 && checked < total);
    });

    // "Select All" checkbox in the header
    $("#selectAllCheckbox").on("change", function () {
      var checked = $(this).is(":checked");
      // Select/deselect only the rows currently visible in the DataTable
      dataT.rows({ search: "applied" }).nodes().to$().find(".row-select").prop("checked", checked);
      updateExportButtons();
    });

    // "Select All" button (text button above table)
    $("#btnSelectAll").on("click", () => {
      var allChecked =
        $("#depositsTable tbody .row-select").length === $("#depositsTable tbody .row-select:checked").length;
      var newState = !allChecked;
      dataT.rows({ search: "applied" }).nodes().to$().find(".row-select").prop("checked", newState);
      $("#selectAllCheckbox").prop("checked", newState).prop("indeterminate", false);
      updateExportButtons();
    });

    // Re-sync header checkbox after DataTable redraws (page change, search, sort)
    dataT.on("draw", () => {
      var total = $("#depositsTable tbody .row-select").length;
      var checked = $("#depositsTable tbody .row-select:checked").length;
      var $header = $("#selectAllCheckbox");
      $header.prop("checked", checked === total && total > 0);
      $header.prop("indeterminate", checked > 0 && checked < total);
      updateExportButtons();
    });
  }

  // ---------------------------------------------------------------------------
  // Export handlers
  // ---------------------------------------------------------------------------

  function bindExportHandlers() {
    $("#btnExportCSV").on("click", () => {
      var ids = getSelectedIds();
      if (!ids.length) return;
      var url = `${window.CRM.root}/api/deposits/csv?ids=${ids.join(",")}`;
      window.CRM.VerifyThenLoadAPIContent(url);
    });

    $("#btnExportOFX").on("click", () => {
      var ids = getSelectedIds();
      $.each(ids, (_i, id) => {
        var url = `${window.CRM.root}/api/deposits/${id}/ofx`;
        window.CRM.VerifyThenLoadAPIContent(url);
      });
    });

    $("#btnExportPDF").on("click", () => {
      var ids = getSelectedIds();
      var validDeposits = [];
      var skippedCount = 0;
      var validationPending = ids.length;

      if (!ids.length) return;

      $.each(ids, (_i, id) => {
        $.ajax({
          method: "GET",
          url: `${window.CRM.root}/api/deposits/${id}/payments`,
          dataType: "json",
        })
          .done((data) => {
            var count = Array.isArray(data) ? data.length : 0;
            if (count > 0) {
              validDeposits.push(id);
            } else {
              skippedCount++;
            }
          })
          .fail(() => {
            skippedCount++;
          })
          .always(() => {
            validationPending--;
            if (validationPending === 0) {
              if (skippedCount > 0) {
                window.CRM.notify(
                  `${i18next.t("Skipped")} ${skippedCount} ${i18next.t("deposit(s) with no payments")}`,
                  { type: "warning", delay: 5000 },
                );
              }
              $.each(validDeposits, (_j, depId) => {
                window.CRM.VerifyThenLoadAPIContent(`${window.CRM.root}/api/deposits/${depId}/pdf`);
              });
            }
          });
      });
    });
  }

  // ---------------------------------------------------------------------------
  // New deposit shortcut — the "New Deposit" button in the card header
  // redirects to the deposit editor after creation, same pattern as FindDepositSlip.js
  // ---------------------------------------------------------------------------

  // (No inline deposit-creation form on this page — handled by DepositSlipEditor.php
  //  via the "New Deposit" link which goes to the dashboard or editor directly.)

  // ---------------------------------------------------------------------------
  // New Deposit creation (modal)
  // ---------------------------------------------------------------------------

  function initNewDepositModal() {
    // Pre-fill today's date
    var today = new Date().toISOString().split("T")[0];
    $("#depositDate").val(today);

    $("#addNewDeposit").on("click", () => {
      var newDeposit = {
        depositType: $("#depositType").val(),
        depositComment: $("#depositComment").val(),
        depositDate: $("#depositDate").val(),
      };

      if (!newDeposit.depositComment.trim()) {
        bootbox.confirm({
          title: i18next.t("Add New Deposit"),
          message: i18next.t("You are about to add a new deposit without a comment"),
          buttons: {
            cancel: { label: i18next.t("Cancel") },
            confirm: { label: i18next.t("Confirm") },
          },
          callback: (result) => {
            if (result) {
              createDepositRequest(newDeposit);
            }
          },
        });
      } else {
        createDepositRequest(newDeposit);
      }
    });

    function createDepositRequest(newDeposit) {
      $.ajax({
        method: "POST",
        url: `${window.CRM.root}/api/deposits`,
        data: JSON.stringify(newDeposit),
        contentType: "application/json; charset=utf-8",
        dataType: "json",
      }).done((data) => {
        window.location.href = `${window.CRM.root}/DepositSlipEditor.php?DepositSlipID=${data.Id}`;
      });
    }
  }

  // ---------------------------------------------------------------------------
  // Initialise
  // ---------------------------------------------------------------------------

  function init() {
    initDataTable();
    bindSelectionHandlers();
    bindExportHandlers();
    initNewDepositModal();
    updateExportButtons();
  }

  $(document).ready(() => {
    window.CRM.onLocalesReady(init);
  });
})();
