/**
 * self-register.js — Self Registrations review dashboard.
 *
 * Lists pending self-registered families and individuals grouped by month,
 * with per-row and batch approve. Loaded by people/views/self-register.php.
 */
function uniqueValues(list) {
  return list.filter((v, i) => v && list.indexOf(v) === i);
}

function renderSelfRegisterContact(emails, phones) {
  if (!emails.length && !phones.length) {
    return `<span class="badge bg-warning-lt text-warning">${i18next.t("No contact info")}</span>`;
  }
  return (
    emails.map((e) => `<div>${window.CRM.escapeHtml(e)}</div>`).join("") +
    phones.map((p) => `<div class="text-body-secondary">${window.CRM.escapeHtml(p)}</div>`).join("")
  );
}

function formatAddress(r) {
  return [r.Address1, [r.City, [r.State, r.Zip].filter(Boolean).join(" ")].filter(Boolean).join(", ")]
    .filter(Boolean)
    .join(", ");
}

function daysAgo(date) {
  var n = moment().startOf("day").diff(moment(date).startOf("day"), "days");
  if (n <= 0) {
    return i18next.t("Today");
  }
  return n === 1 ? i18next.t("Yesterday") : `${n} ${i18next.t("days ago")}`;
}

let canApprove = false;

function updateSelectedCount() {
  var n = $("#selfRegistrations .row-select:checked").length;
  $("#selectedCount").text(n);
  $("#approveSelected").prop("disabled", n === 0);
}

function addMonthHeaders(api) {
  var cols = api.columns().count();
  var last = null;
  api.rows({ page: "current" }).every(function () {
    var key = moment(this.data().dateEntered).format("YYYY-MM");
    $(this.node()).attr("data-month", key);
    if (key !== last) {
      last = key;
      $(this.node()).before(
        '<tr class="month-group table-active"><td colspan="' +
          cols +
          '"><div class="d-flex align-items-center">' +
          (canApprove
            ? '<input type="checkbox" class="form-check-input month-select me-2" data-month="' +
              key +
              '" aria-label="' +
              window.CRM.escapeHtml(i18next.t("Select month")) +
              '">'
            : "") +
          "<strong>" +
          window.CRM.escapeHtml(moment(`${key}-01`).format("MMMM YYYY")) +
          "</strong></div></td></tr>",
      );
    }
  });
}

function initializeSelfRegister() {
  canApprove = Boolean(window.CRM.permissions?.editRecords);
  return $.when(
    $.get(`${window.CRM.root}/api/families/self-register`),
    $.get(`${window.CRM.root}/api/persons/self-register`),
  )
    .done((familiesResp, peopleResp) => {
      var families = (familiesResp[0].families || []).map((f) => ({
        type: "family",
        id: f.Id,
        name: f.Name,
        address: formatAddress(f),
        members: f.Members || [],
        emails: uniqueValues([f.Email].concat(f.MemberEmails || [])),
        phones: uniqueValues([f.HomePhone].concat(f.MemberPhones || [])),
        dateEntered: f.DateEntered,
        needsReview: !!f.NeedsReview,
      }));
      var people = (peopleResp[0].people || []).map((p) => ({
        type: "individual",
        id: p.Id,
        name: p.FullName,
        address: formatAddress(p),
        emails: uniqueValues([p.Email, p.WorkEmail]),
        phones: uniqueValues([p.CellPhone, p.HomePhone, p.WorkPhone]),
        dateEntered: p.DateEntered,
        needsReview: !!p.NeedsReview,
      }));

      var dataTableConfig = {
        data: families.concat(people),
        autoWidth: false,
        columns: [
          {
            title: canApprove
              ? '<input type="checkbox" class="form-check-input" id="selectAll" aria-label="' +
                window.CRM.escapeHtml(i18next.t("Select all")) +
                '">'
              : "",
            data: null,
            orderable: false,
            searchable: false,
            visible: canApprove,
            className: "w-1 no-export text-nowrap",
            responsivePriority: 1,
            render: (_data, _type, row) =>
              '<input type="checkbox" class="form-check-input row-select" data-entity-type="' +
              row.type +
              '" data-entity-id="' +
              row.id +
              '">',
          },
          {
            title: i18next.t("Type"),
            data: "type",
            responsivePriority: 5,
            width: "10%",
            render: (data) =>
              data === "family"
                ? `<span class="badge bg-secondary-lt text-secondary">${i18next.t("Family")}</span>`
                : `<span class="badge bg-info-lt text-info">${i18next.t("Individual")}</span>`,
          },
          {
            title: i18next.t("Name"),
            data: "name",
            responsivePriority: 1,
            width: "36%",
            render: (data, type, row) => {
              var members = row.members || [];
              if (type !== "display") {
                return `${data} ${members.join(" ")} ${row.address || ""}`;
              }
              var url =
                row.type === "family"
                  ? `${window.CRM.root}/people/family/${encodeURIComponent(row.id)}`
                  : `${window.CRM.root}/people/view/${encodeURIComponent(row.id)}`;
              var html = `<a href="${url}">${window.CRM.escapeHtml(data)}</a>`;
              if (members.length) {
                html += ` <span class="text-body-secondary small">· ${window.CRM.escapeHtml(members.join(", "))}</span>`;
              }
              if (row.address) {
                html +=
                  '<div class="text-body-secondary small"><i class="fa-solid fa-location-dot me-1"></i>' +
                  window.CRM.escapeHtml(row.address) +
                  "</div>";
              }
              return html;
            },
          },
          {
            title: i18next.t("Contact"),
            data: null,
            responsivePriority: 3,
            orderable: false,
            searchable: false,
            width: "28%",
            render: (_data, _type, row) => renderSelfRegisterContact(row.emails, row.phones),
          },
          {
            title: i18next.t("Registered"),
            data: "dateEntered",
            responsivePriority: 4,
            width: "15%",
            render: (data, type) =>
              type === "display"
                ? moment(data).format("ll") +
                  '<div class="text-body-secondary small">' +
                  window.CRM.escapeHtml(daysAgo(data)) +
                  "</div>"
                : data,
          },
          {
            title: i18next.t("Actions"),
            data: null,
            orderable: false,
            searchable: false,
            className: "text-end w-1 no-export text-nowrap",
            responsivePriority: 2,
            render: (_data, _type, row) =>
              row.type === "family"
                ? window.CRM.renderFamilyActionMenu(row.id, row.name, { needsReview: row.needsReview })
                : window.CRM.renderPersonActionMenu(row.id, row.name, { needsReview: row.needsReview }),
          },
        ],
        order: [[4, "desc"]],
        paging: false,
        drawCallback: function () {
          $("#selfRegistrations tr.month-group").remove();
          addMonthHeaders(this.api());
          updateSelectedCount();
        },
      };

      $.extend(dataTableConfig, window.CRM.plugin.dataTable);
      $("#selfRegistrations").DataTable(dataTableConfig);
      $("#bulkBar").toggleClass("d-none", !canApprove);
    })
    .fail(() => {
      window.CRM.notify(i18next.t("Error loading self-registered entries"), { type: "danger", delay: 6000 });
    });
}

var approvalInFlight = false;

function refreshSelfRegisterCounts() {
  window.CRM.APIRequest({
    method: "GET",
    path: "persons/self-register/count",
    suppressErrorDialog: true,
  }).done((counts) => {
    $("#selfRegPending").text(counts.count);
    $("#selfRegApproved").text(counts.approved);
    $("#selfRegTotal").text(counts.total);
    window.CRM.dashboard.loadSelfRegisterPendingCount();
  });
}

function reloadSelfRegister() {
  refreshSelfRegisterCounts();
  $("#selfRegistrations tr.month-group").remove();
  $("#selfRegistrations").DataTable().destroy();
  return initializeSelfRegister().always(() => {
    approvalInFlight = false;
  });
}

function approve(path, payload) {
  if (approvalInFlight) {
    return;
  }
  approvalInFlight = true;
  window.CRM.APIRequest({
    method: "POST",
    path: path,
    data: payload ? JSON.stringify(payload) : undefined,
  })
    .done(() => {
      window.CRM.notify(i18next.t("Approved"), { type: "success", delay: 3000 });
      reloadSelfRegister();
    })
    .fail((xhr) => {
      approvalInFlight = false;
      var msg = xhr.responseJSON?.message ? xhr.responseJSON.message : i18next.t("An error occurred");
      window.CRM.notify(msg, { type: "danger", delay: 5000 });
    });
}

// Approve one self-registered family or family-less person from its row menu
$(document).on("click", ".approve-review", function () {
  var entityType = $(this).data("entity-type");
  approve(`${(entityType === "family" ? "family/" : "person/") + $(this).data("entity-id")}/approve-review`);
});

// Approve every ticked row in one request
$(document).on("click", "#approveSelected", () => {
  var payload = { families: [], persons: [] };
  $("#selfRegistrations .row-select:checked").each(function () {
    payload[$(this).data("entity-type") === "family" ? "families" : "persons"].push($(this).data("entity-id"));
  });
  approve("persons/self-register/approve", payload);
});

$(document).on("change", "#selectAll", function () {
  $("#selfRegistrations .row-select").prop("checked", this.checked);
  $("#selfRegistrations .month-select").prop("checked", this.checked);
  updateSelectedCount();
});

$(document).on("change", ".month-select", function () {
  $(`#selfRegistrations tr[data-month="${$(this).data("month")}"] .row-select`).prop("checked", this.checked);
  updateSelectedCount();
});

$(document).on("change", ".row-select", updateSelectedCount);

// Wait for locales to load before initializing
$(document).ready(() => {
  window.CRM.onLocalesReady(initializeSelfRegister);
});
