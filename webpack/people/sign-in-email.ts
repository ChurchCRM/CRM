/**
 * /people/sign-in-email: "Email needs attention" for staff with Edit Records
 * (member sign-in design §9, §11.2), with Fix shared emails and Confirm
 * selected. Reads GET /api/persons/sign-in-email/attention a page at a time.
 */
import { getFrom, type PageResult, serverTable } from "../admin-member-portal/server-table";
import {
  type AttentionRow,
  type AttentionTools,
  attentionColumns,
  attentionCounts,
  wireEmailAttentionTools,
} from "../sign-in-email/email-attention";

function start(): void {
  const tableElement = document.getElementById("memberListTable") as HTMLTableElement | null;
  if (!tableElement) {
    return;
  }
  const config = window.CRM?.signInEmailAttention as { csrfToken?: string } | undefined;
  let problem = "";
  let table: DataTablesApi | null = null;
  let tools: AttentionTools | null = null;

  const loadCounts = (): void => {
    attentionCounts()
      .then((counts) => tools?.applyCounts(counts))
      .catch(() => undefined);
  };

  tools = wireEmailAttentionTools({
    table: tableElement,
    csrfToken: () => config?.csrfToken ?? "",
    onChanged: () => {
      table?.draw(false);
      loadCounts();
    },
    onFilter: () => table?.draw(),
  });

  const menu = (row: AttentionRow): string =>
    window.CRM?.renderPersonActionMenu?.(row.personId, row.name, { familyId: row.familyId ?? undefined }) ?? "";
  const load = (startAt: number, length: number, search: string): Promise<PageResult<AttentionRow>> =>
    getFrom<PageResult<AttentionRow>>("/api/persons/sign-in-email/attention", {
      start: startAt,
      length,
      search,
      problem,
      kind: tools?.kind() ?? "",
    });

  table = serverTable<AttentionRow>(tableElement, attentionColumns(menu), load, { onDraw: () => tools?.afterDraw() });

  document.getElementById("memberListProblems")?.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>("[data-problem]");
    if (!button) {
      return;
    }
    problem = button.dataset.problem ?? "";
    for (const other of document.querySelectorAll<HTMLButtonElement>("#memberListProblems [data-problem]")) {
      other.classList.toggle("active", other === button);
    }
    tools?.setProblem(problem);
    table?.draw();
  });

  loadCounts();
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(start);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
