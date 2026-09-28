/**
 * Finance: Deposit Search
 *
 * Handles:
 * - DataTable enhancement of the server-rendered deposits table
 * - Row checkbox selection (individual + Select All)
 * - Export button enable/disable with live selected count
 * - Bulk delete / CSV / OFX / PDF export
 * - New-Deposit modal (comment, type, date → POST /api/deposits → redirect to editor)
 *
 * Requires: jQuery, DataTables, bootbox, i18next, window.CRM (loaded globally)
 */

(function initDepositSearch() {
  var dataT = null;

  // ---------------------------------------------------------------------------
  // Utilities
  // ---------------------------------------------------------------------------

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
   * Update action buttons (delete + export) based on current checkbox state.
   */
  function updateActionButtons() {
    var ids = getSelectedIds();
    var count = ids.length;
    var enabled = count > 0;

    $("#btnDeleteSelected, #btnExportCSV, #btnExportOFX, #btnExportPDF").prop("disabled", !enabled);

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
    // Individual row checkbox — sync header + update buttons
    $("#depositsTable tbody").on("change", ".row-select", () => {
      updateActionButtons();
      var total = $("#depositsTable tbody .row-select").length;
      var checked = $("#depositsTable tbody .row-select:checked").length;
      var $header = $("#selectAllCheckbox");
      $header.prop("checked", checked === total && total > 0);
      $header.prop("indeterminate", checked > 0 && checked < total);
    });

    // "Select All" checkbox in the header
    $("#selectAllCheckbox").on("change", function () {
      var checked = $(this).is(":checked");
      dataT.rows({ search: "applied" }).nodes().to$().find(".row-select").prop("checked", checked);
      updateActionButtons();
    });

    // "Select All" text button above the table
    $("#btnSelectAll").on("click", () => {
      var allChecked =
        $("#depositsTable tbody .row-select").length === $("#depositsTable tbody .row-select:checked").length;
      var newState = !allChecked;
      dataT.rows({ search: "applied" }).nodes().to$().find(".row-select").prop("checked", newState);
      $("#selectAllCheckbox").prop("checked", newState).prop("indeterminate", false);
      updateActionButtons();
    });

    // Re-sync after DataTable redraws (page change, search, sort)
    dataT.on("draw", () => {
      var total = $("#depositsTable tbody .row-select").length;
      var checked = $("#depositsTable tbody .row-select:checked").length;
      var $header = $("#selectAllCheckbox");
      $header.prop("checked", checked === total && total > 0);
      $header.prop("indeterminate", checked > 0 && checked < total);
      updateActionButtons();
    });
  }

  // ---------------------------------------------------------------------------
  // Delete handler
  // ---------------------------------------------------------------------------

  function bindDeleteHandler() {
    $("#btnDeleteSelected").on("click", () => {
      var ids = getSelectedIds();
      if (!ids.length) return;

      bootbox.confirm({
        title: i18next.t("Confirm Delete"),
        message:
          `<p>${i18next.t("Are you sure you want to delete the selected")} ${ids.length} ${i18next.t("Deposit(s)")}?</p>` +
          `<p>${i18next.t("This will also delete all payments associated with this deposit")}</p>` +
          `<p class="text-danger fw-bold">${i18next.t("This action CANNOT be undone, and may have legal implications!")}</p>`,
        buttons: {
          cancel: { label: i18next.t("Close"), className: "btn-secondary" },
          confirm: { label: i18next.t("Delete"), className: "btn-danger" },
        },
        callback: (result) => {
          if (!result) return;

          var deletePromises = ids.map((id) =>
            window.CRM.APIRequest({
              method: "DELETE",
              path: `deposits/${id}`,
            }),
          );

          $.when(...deletePromises).always(() => {
            // Reload page to reflect deletions (server-side filtered table)
            window.location.reload();
          });
        },
      });
    });
  }

  // ---------------------------------------------------------------------------
  // Export handlers
  // ---------------------------------------------------------------------------

  function bindExportHandlers() {
    $("#btnExportCSV").on("click", () => {
      var ids = getSelectedIds();
      if (!ids.length) return;
      window.CRM.VerifyThenLoadAPIContent(`${window.CRM.root}/api/deposits/csv?ids=${ids.join(",")}`);
    });

    $("#btnExportOFX").on("click", () => {
      var ids = getSelectedIds();
      $.each(ids, (_i, id) => {
        window.CRM.VerifyThenLoadAPIContent(`${window.CRM.root}/api/deposits/${id}/ofx`);
      });
    });

    $("#btnExportPDF").on("click", () => {
      var ids = getSelectedIds();
      if (!ids.length) return;

      var validDeposits = [];
      var skippedCount = 0;
      var validationPending = ids.length;

      $.each(ids, (_i, id) => {
        $.ajax({ method: "GET", url: `${window.CRM.root}/api/deposits/${id}/payments`, dataType: "json" })
          .done((data) => {
            if (Array.isArray(data) && data.length > 0) {
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
  // New Deposit modal
  // ---------------------------------------------------------------------------

  function initNewDepositModal() {
    // Pre-fill today's date
    $("#depositDate").val(new Date().toISOString().split("T")[0]);

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
          callback: (confirmed) => {
            if (confirmed) {
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
      })
        .done((data) => {
          window.location.href = `${window.CRM.root}/DepositSlipEditor.php?DepositSlipID=${data.Id}`;
        })
        .fail((_jqXHR, _textStatus, errorThrown) => {
          window.CRM.notify(`${i18next.t("Failed to create deposit")}: ${errorThrown || i18next.t("Unknown error")}`, {
            type: "error",
            delay: 6000,
          });
        });
    }
  }

  // ---------------------------------------------------------------------------
  // Initialise
  // ---------------------------------------------------------------------------

  function init() {
    initDataTable();
    bindSelectionHandlers();
    bindDeleteHandler();
    bindExportHandlers();
    initNewDepositModal();
    updateActionButtons();
  }

  $(document).ready(() => {
    window.CRM.onLocalesReady(init);
  });
})();
