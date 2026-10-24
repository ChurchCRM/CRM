/**
 * Email log card (`src/people/views/partials/email-log-table.php`): a server-side DataTable over
 * GET /api/email/log. The status, type and sender filters and the search box are sent to the
 * server, and kept in the query string so a reload or a badge link opens the same view.
 */
import { buildAPIUrl } from "../api-utils";

const FILTERS = { status: "email-log-status", kind: "email-log-kind", sentBy: "email-log-sent-by" };
const PAGE_LENGTHS = [10, 25, 50, 100];
const STATUS_BADGES = {
  sent: "bg-success-lt text-success",
  failed: "bg-danger-lt text-danger",
  queued: "bg-azure-lt text-azure",
  sending: "bg-azure-lt text-azure",
};

const esc = (value) => window.CRM.escapeHtml(value ?? "");
const escAttr = (value) => window.CRM.escapeAttribute(value ?? "");

function filterSelects() {
  return Object.entries(FILTERS)
    .map(([param, id]) => [param, document.getElementById(id)])
    .filter(([, select]) => select !== null);
}

function filterParams(search) {
  const params = new URLSearchParams();
  for (const [param, select] of filterSelects()) {
    if (select.value) params.set(param, select.value);
  }
  if (search) params.set("q", search);
  return params;
}

/** Selects the filters the query string names and returns its search text. */
function applyQueryString() {
  const query = new URLSearchParams(window.location.search);
  for (const [param, select] of filterSelects()) {
    const value = query.get(param) ?? "";
    if ([...select.options].some((option) => option.value === value)) select.value = value;
  }
  return query.get("q") ?? "";
}

function fetchPage(data, callback) {
  const search = (data.search?.value ?? "").trim();
  const filters = filterParams(search);
  const query = filters.toString();
  window.history.replaceState(null, "", `${window.location.pathname}${query ? `?${query}` : ""}`);

  filters.set("page", String(Math.floor(data.start / data.length) + 1));
  filters.set("limit", String(data.length));
  fetch(`${buildAPIUrl("email/log")}?${filters}`, {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  })
    .then(async (res) => {
      const body = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(body.message || i18next.t("Failed to load email history"));
      return body;
    })
    .then((body) =>
      callback({ draw: data.draw, recordsTotal: body.total, recordsFiltered: body.total, data: body.rows }),
    )
    .catch((error) => {
      window.CRM.notify(error.message, { type: "danger", delay: 6000 });
      callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
    });
}

function subjectCell(row) {
  const subject = row.subject || i18next.t("(no subject)");
  let html =
    `<a href="#" class="email-history-open fw-semibold" data-email-log-id="${Number(row.id)}"` +
    ` title="${escAttr(i18next.t("View this email"))}">${esc(subject)}</a>`;
  if (row.error) {
    const tone = row.status === "failed" ? "text-danger" : "text-body-secondary";
    html += `<div class="small ${tone} text-break" data-email-failure-error>${esc(row.error)}</div>`;
  }
  return html;
}

function recipientCell(row) {
  if (!row.recipientName || !row.recipientUrl) {
    return `<span class="text-body-secondary">${esc(row.address)}</span>`;
  }
  return (
    `<a href="${escAttr(row.recipientUrl)}" data-email-recipient-link>${esc(row.recipientName)}</a>` +
    `<div class="small text-body-secondary">${esc(row.address)}</div>`
  );
}

const COLUMNS = [
  {
    data: "dateSent",
    type: "string",
    className: "text-nowrap text-body-secondary min-tablet-p",
    responsivePriority: 3,
    render: (value) => esc(String(value ?? "").slice(0, 16)),
  },
  {
    data: "kindLabel",
    className: "text-nowrap",
    responsivePriority: 5,
    render: (value) => `<span class="badge bg-blue-lt text-blue">${esc(value)}</span>`,
  },
  { data: "subject", responsivePriority: 1, render: (_value, _type, row) => subjectCell(row) },
  { data: "address", responsivePriority: 4, render: (_value, _type, row) => recipientCell(row) },
  {
    data: "status",
    className: "text-nowrap",
    responsivePriority: 2,
    render: (value, _type, row) =>
      `<span class="badge ${STATUS_BADGES[value] ?? "bg-secondary-lt text-secondary"}">${esc(row.statusLabel)}</span>`,
  },
  {
    data: "sentBy",
    className: "text-nowrap text-body-secondary",
    responsivePriority: 6,
    render: (value) => (value ? esc(value) : `<span class="text-secondary">${esc(i18next.t("Automatic"))}</span>`),
  },
];

function init() {
  const tableEl = document.getElementById("email-log-table");
  if (!tableEl) return;

  const shared = window.CRM.plugin?.dataTable ?? {};
  const pageLength = PAGE_LENGTHS.includes(shared.pageLength) ? shared.pageLength : 100;
  const table = $(tableEl).DataTable({
    ...shared,
    serverSide: true,
    processing: true,
    ordering: false,
    searchDelay: 400,
    search: { search: applyQueryString() },
    pageLength,
    lengthMenu: PAGE_LENGTHS,
    layout: { topStart: "search", topEnd: null, bottomStart: "pageLength", bottomEnd: ["info", "paging"] },
    buttons: [],
    ajax: fetchPage,
    columns: COLUMNS,
    createdRow: (row, data) => {
      row.dataset.emailLogRow = String(data.id);
      row.dataset.emailStatus = data.status;
    },
  });

  for (const [, select] of filterSelects()) {
    select.addEventListener("change", () => table.draw());
  }

  for (const link of document.querySelectorAll("[data-email-log-status-link]")) {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      for (const [param, select] of filterSelects()) {
        select.value = param === "status" ? link.dataset.emailLogStatusLink : "";
      }
      table.search("").draw();
    });
  }
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(init);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
