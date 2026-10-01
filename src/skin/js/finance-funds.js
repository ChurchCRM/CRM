/**
 * Finance: Donation Funds admin page
 *
 * Handles:
 * - DataTable enhancement of the server-rendered funds table
 * - Inline add new fund (POST /finance/api/funds)
 * - Edit fund via modal (PUT /finance/api/funds/{id})
 * - Activate / deactivate (PUT /finance/api/funds/{id} with active)
 * - Move up / move down (PATCH /finance/api/funds/{id}/order)
 * - Delete fund with bootbox confirmation (DELETE /finance/api/funds/{id})
 * - Rename / delete a category across funds (PUT/DELETE /finance/api/funds/categories)
 *
 * Requires: jQuery, DataTables, bootbox, i18next, window.CRM (loaded globally)
 * window.CRM.funds is injected by the PHP view.
 */

(function initFinanceFunds() {
  var cfg = window.CRM.funds || {};
  var apiBase = cfg.apiBase || "";
  var i18n = cfg.i18n || {};

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  /**
   * JSON fetch wrapper: method, URL, optional body → Promise<{ok, status, data}>
   */
  function apiFetch(method, url, body) {
    var opts = {
      method: method,
      headers: { "Content-Type": "application/json", Accept: "application/json" },
    };
    if (body !== undefined && body !== null) {
      opts.body = JSON.stringify(body);
    }
    return fetch(url, opts).then((res) => res.json().then((data) => ({ ok: res.ok, status: res.status, data: data })));
  }

  function notify(msg, type) {
    if (window.CRM?.notify) {
      window.CRM.notify(msg, { type: type || "success" });
    }
  }

  // -----------------------------------------------------------------------
  // DataTable
  // -----------------------------------------------------------------------

  function initDataTable() {
    if (!$.fn.DataTable) {
      return;
    }
    var config = {
      columns: [
        null, // Name
        null, // Description
        null, // Category
        null, // Active
        { orderable: false, searchable: false }, // Actions
      ],
      order: [], // preserve server-defined order
      columnDefs: [{ targets: [4], className: "text-center" }],
    };
    $.extend(true, config, window.CRM.plugin ? window.CRM.plugin.dataTable : {});
    $("#fundsTable").DataTable(config);
  }

  // -----------------------------------------------------------------------
  // Add new fund
  // -----------------------------------------------------------------------

  function bindAddFund() {
    $("#addNewFund").on("click", () => {
      var name = $.trim($("#newFundName").val());
      var desc = $.trim($("#newFundDesc").val());
      var category = $.trim($("#newFundCategory").val());

      $("#addFundError").addClass("d-none").text("");

      if (!name) {
        $("#addFundError").removeClass("d-none").text(i18n.errRequired);
        return;
      }

      apiFetch("POST", apiBase, { name: name, description: desc, category: category, active: true })
        .then((res) => {
          if (res.ok) {
            notify(i18n.addedOk, "success");
            window.location.reload();
          } else {
            const msg = res.data?.message ? res.data.message : i18n.errServer;
            $("#addFundError").removeClass("d-none").text(msg);
          }
        })
        .catch(() => {
          $("#addFundError").removeClass("d-none").text(i18n.errServer);
        });
    });

    // Allow pressing Enter in the name field to trigger add
    $("#newFundName").on("keypress", (e) => {
      if (e.which === 13) {
        $("#addNewFund").trigger("click");
      }
    });
  }

  // -----------------------------------------------------------------------
  // Edit fund modal
  // -----------------------------------------------------------------------

  function bindEditFund() {
    // Open modal and populate fields
    $(document).on("click", ".fund-edit-btn", function () {
      var btn = $(this);
      $("#editFundId").val(btn.data("fund-id"));
      $("#editFundName").val(btn.data("fund-name"));
      $("#editFundDesc").val(btn.data("fund-desc"));
      $("#editFundCategory").val(btn.data("fund-category"));
      $("#editFundActive").prop("checked", btn.attr("data-fund-active") === "true");
      $("#editFundError").addClass("d-none").text("");
      $("#editFundModal").modal("show");
    });

    // Save edits
    $("#saveFundEdit").on("click", () => {
      var id = $("#editFundId").val();
      var name = $.trim($("#editFundName").val());

      $("#editFundError").addClass("d-none").text("");

      if (!name) {
        $("#editFundError").removeClass("d-none").text(i18n.errRequired);
        return;
      }

      var payload = {
        name: name,
        description: $.trim($("#editFundDesc").val()),
        category: $.trim($("#editFundCategory").val()),
        active: $("#editFundActive").is(":checked"),
      };

      apiFetch("PUT", `${apiBase}/${id}`, payload)
        .then((res) => {
          if (res.ok) {
            $("#editFundModal").modal("hide");
            notify(i18n.savedOk, "success");
            window.location.reload();
          } else {
            const msg = res.data?.message ? res.data.message : i18n.errServer;
            $("#editFundError").removeClass("d-none").text(msg);
          }
        })
        .catch(() => {
          $("#editFundError").removeClass("d-none").text(i18n.errServer);
        });
    });
  }

  // -----------------------------------------------------------------------
  // Activate / deactivate from the row menu
  // -----------------------------------------------------------------------

  function bindToggleActive() {
    $(document).on("click", ".fund-toggle-active-btn", function () {
      var btn = $(this);
      var activate = btn.attr("data-fund-active") !== "true";

      apiFetch("PUT", `${apiBase}/${btn.data("fund-id")}`, { active: activate })
        .then((res) => {
          if (res.ok) {
            notify(activate ? i18n.activatedOk : i18n.deactivatedOk, "success");
            window.location.reload();
          } else {
            notify(res.data?.message ? res.data.message : i18n.errServer, "error");
          }
        })
        .catch(() => notify(i18n.errServer, "error"));
    });
  }

  // -----------------------------------------------------------------------
  // Reorder (move up / move down)
  // -----------------------------------------------------------------------

  function bindReorder() {
    $(document).on("click", ".fund-order-btn", function () {
      var btn = $(this);
      var id = btn.data("fund-id");
      var dir = btn.data("direction");

      apiFetch("PATCH", `${apiBase}/${id}/order`, { direction: dir })
        .then((res) => {
          if (res.ok) {
            window.location.reload();
          } else {
            const msg = res.data?.message ? res.data.message : i18n.errServer;
            notify(msg, "error");
          }
        })
        .catch(() => {
          notify(i18n.errServer, "error");
        });
    });
  }

  // -----------------------------------------------------------------------
  // Delete fund
  // -----------------------------------------------------------------------

  function bindDelete() {
    $(document).on("click", ".fund-delete-btn", function () {
      var btn = $(this);
      var id = btn.data("fund-id");
      var name = btn.data("fund-name");

      bootbox.confirm({
        title: i18n.deleteTitle,
        message:
          "<p>" +
          i18n.confirmDelete +
          "</p><p><strong>" +
          $("<span>").text(name).html() +
          '</strong></p><p class="text-danger">' +
          i18n.deleteWarning +
          "</p>",
        buttons: {
          cancel: { label: i18n.cancel, className: "btn-secondary" },
          confirm: { label: i18n.delete, className: "btn-danger" },
        },
        callback: (result) => {
          if (!result) {
            return;
          }
          apiFetch("DELETE", `${apiBase}/${id}`)
            .then((res) => {
              if (res.ok) {
                notify(i18n.deletedOk, "success");
                window.location.reload();
              } else if (res.status === 409) {
                const msg = res.data?.message ? res.data.message : i18n.errServer;
                notify(msg, "error");
              } else {
                notify(i18n.errServer, "error");
              }
            })
            .catch(() => {
              notify(i18n.errServer, "error");
            });
        },
      });
    });
  }

  // -----------------------------------------------------------------------
  // Categories: rename / delete (applies to every fund in the category)
  // -----------------------------------------------------------------------

  function categoryRequest(method, url, body) {
    apiFetch(method, url, body)
      .then((res) => {
        if (res.ok) {
          notify(i18n.categoryUpdatedOk, "success");
          window.location.reload();
        } else {
          notify(res.data?.message ? res.data.message : i18n.errServer, "error");
        }
      })
      .catch(() => notify(i18n.errServer, "error"));
  }

  function bindCategories() {
    $(document).on("click", ".category-rename-btn", function () {
      var name = $(this).data("category");
      bootbox.prompt({
        title: i18n.renameCategoryTitle,
        message: i18n.renameCategoryPrompt,
        value: name,
        buttons: {
          cancel: { label: i18n.cancel, className: "btn-secondary" },
          confirm: { label: i18n.renameLabel, className: "btn-primary" },
        },
        callback: (result) => {
          var newName = $.trim(result || "");
          if (newName && newName !== name) {
            categoryRequest("PUT", `${apiBase}/categories`, { name: name, newName: newName });
          }
        },
      });
    });

    $(document).on("click", ".category-delete-btn", function () {
      var name = $(this).data("category");
      bootbox.confirm({
        title: i18n.deleteCategoryTitle,
        message:
          "<p><strong>" + $("<span>").text(name).html() + "</strong></p><p>" + i18n.deleteCategoryConfirm + "</p>",
        buttons: {
          cancel: { label: i18n.cancel, className: "btn-secondary" },
          confirm: { label: i18n.delete, className: "btn-danger" },
        },
        callback: (result) => {
          if (result) {
            categoryRequest("DELETE", `${apiBase}/categories?name=${encodeURIComponent(name)}`);
          }
        },
      });
    });
  }

  // -----------------------------------------------------------------------
  // Init
  // -----------------------------------------------------------------------

  function init() {
    initDataTable();
    bindAddFund();
    bindEditFund();
    bindToggleActive();
    bindReorder();
    bindDelete();
    bindCategories();
  }

  $(document).ready(() => {
    if (window.CRM?.onLocalesReady) {
      window.CRM.onLocalesReady(init);
    } else {
      init();
    }
  });
})();
