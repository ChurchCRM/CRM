/**
 * Admin → Member Portal → Audit (member sign-in design §11.2): the member
 * sign-in audit, newest first, filtered by person, date range and event.
 */
import { escapeHtml, getJSON, muted, onTabShown, type PageResult, personLink, serverTable, t } from "./server-table";

interface AuditRow {
  timeDisplay: string;
  personId: number | null;
  personName: string | null;
  event: string;
  eventLabel: string;
  methodLabel: string | null;
  reasonLabel: string | null;
  actorId: number | null;
  actorName: string | null;
  email: string | null;
  detail: string | null;
  summary: string | null;
}

let table: DataTablesApi | null = null;
let personFilter = "";

const value = (id: string): string =>
  (document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null)?.value ?? "";

function showError(message: string): void {
  const slot = document.getElementById("portalAuditError");
  if (slot) {
    slot.textContent = message;
  }
}

function load(start: number, length: number): Promise<PageResult<AuditRow>> {
  showError("");
  return getJSON<PageResult<AuditRow>>("member-portal/audit", {
    start,
    length,
    personId: personFilter,
    from: value("portalAuditFrom"),
    to: value("portalAuditTo"),
    event: value("portalAuditEvent"),
  });
}

function eventCell(row: AuditRow): string {
  const tone = row.event === "refused" || row.event === "throttled" ? "text-danger" : "";
  const text = row.summary ?? row.detail;
  const detail = text ? `<div class="small text-secondary text-break">${escapeHtml(text)}</div>` : "";
  return `<span class="${tone}">${escapeHtml(row.eventLabel)}</span>${detail}`;
}

function drawTable(): void {
  const element = document.getElementById("portalAuditTable") as HTMLTableElement | null;
  if (!element) {
    return;
  }
  table = serverTable<AuditRow>(
    element,
    [
      { title: t("Time"), render: (row) => `<span class="text-nowrap">${escapeHtml(row.timeDisplay)}</span>` },
      {
        title: t("Person"),
        render: (row) => (row.personId ? personLink(row.personId, row.personName) : muted(t("No match"))),
      },
      { title: t("Event"), render: eventCell },
      { title: t("How"), render: (row) => escapeHtml(row.methodLabel ?? "") },
      { title: t("Reason"), render: (row) => escapeHtml(row.reasonLabel ?? "") },
      { title: t("By"), render: (row) => (row.actorId ? personLink(row.actorId, row.actorName) : "") },
      {
        title: t("Address"),
        render: (row) => (row.email ? `<span class="text-break">${escapeHtml(row.email)}</span>` : ""),
      },
    ],
    (start, length) => load(start, length),
    { searching: false, stackOnNarrowScreens: true, onError: showError },
  );
}

export function wireAuditTab(): void {
  const form = document.getElementById("portalAuditFilters") as HTMLFormElement | null;
  if (!form) {
    return;
  }

  const person = document.getElementById("portalAuditPerson") as HTMLSelectElement | null;
  let personSelect: TomSelectInstance | null = null;
  if (person && typeof window.CRM?.initPersonSelect === "function") {
    personSelect = window.CRM.initPersonSelect(person, {
      onChange: (selected: string) => {
        personFilter = selected ?? "";
        table?.draw();
      },
    });
  }

  for (const id of ["portalAuditFrom", "portalAuditTo", "portalAuditEvent"]) {
    document.getElementById(id)?.addEventListener("change", () => table?.draw());
  }
  form.addEventListener("submit", (event) => event.preventDefault());
  form.addEventListener("reset", () => {
    personSelect?.clear(true);
    personFilter = "";
    window.setTimeout(() => table?.draw(), 0);
  });

  onTabShown("portal-audit-tab", drawTable);
}
