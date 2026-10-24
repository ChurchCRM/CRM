/**
 * Admin → Member Portal → Members (member sign-in design §11.2, A7, §10.1):
 * the lists of who may sign in, who has, whose email stops them, recent family
 * confirmations and the family emails the 7.9.0 upgrade could not move. Each
 * list reads GET /admin/api/member-portal/lists/{list} a page at a time.
 */
import {
  type AttentionCounts,
  type AttentionRow,
  type AttentionTools,
  attentionColumns,
  wireEmailAttentionTools,
} from "../sign-in-email/email-attention";
import {
  escapeHtml,
  familyLink,
  getJSON,
  muted,
  onTabShown,
  type PageResult,
  personLink,
  type ServerColumn,
  serverTable,
  t,
} from "./server-table";

interface PersonRow {
  personId: number;
  name: string;
  familyId: number | null;
  familyName: string;
  email: string;
  lastSignInDisplay: string;
  lastMethodLabel: string | null;
  lastActivityDisplay: string;
}

interface ConfirmationRow {
  familyId: number;
  familyName: string;
  confirmedAtDisplay: string;
  personId: number | null;
  personName: string | null;
  methodLabel: string | null;
  viaLabel: string;
  result: string;
  resultLabel: string;
}

interface NotMovedRow {
  familyId: number;
  familyName: string;
  email: string;
  reason: string | null;
  reasonLabel: string;
  holderId: number | null;
  holderName: string | null;
  dateDisplay: string;
}

interface ListSummary extends AttentionCounts {
  [list: string]: unknown;
  familyEmailMoves: Record<string, number>;
}

type AnyRow = PersonRow | AttentionRow | ConfirmationRow | NotMovedRow;

const TABLE_ID = "memberListTable";

let currentList = "may-sign-in";
let currentProblem = "";
let table: DataTablesApi | null = null;
let attention: AttentionTools | null = null;

const email = (row: PersonRow): string =>
  row.email ? `<span class="text-break">${escapeHtml(row.email)}</span>` : muted(t("None"));
const lastSignIn = (row: PersonRow): string =>
  row.lastSignInDisplay ? escapeHtml(row.lastSignInDisplay) : muted(t("Never"));

const personMenu = (row: { personId: number; name: string; familyId: number | null }): string =>
  window.CRM?.renderPersonActionMenu?.(row.personId, row.name, { familyId: row.familyId ?? undefined }) ?? "";

const familyMenu = (row: { familyId: number; familyName: string }): string =>
  window.CRM?.renderFamilyActionMenu?.(row.familyId, row.familyName) ?? "";

const nameColumn: ServerColumn<PersonRow> = {
  title: t("Name"),
  render: (row) => personLink(row.personId, row.name),
  priority: 1,
};
const familyColumn: ServerColumn<PersonRow> = {
  title: t("Family"),
  render: (row) => familyLink(row.familyId, row.familyName),
  priority: 5,
};
const menuColumn: ServerColumn<PersonRow> = {
  title: "",
  render: personMenu,
  className: "text-end w-1 no-export",
  priority: 2,
};

const LISTS: Record<string, { description: () => string; columns: () => ServerColumn<AnyRow>[] }> = {
  "may-sign-in": {
    description: () =>
      t("The sign-in rules let them in, and each has their own email that nobody else who may sign in uses."),
    columns: () =>
      [
        nameColumn,
        familyColumn,
        { title: t("Email"), render: email, priority: 3 },
        { title: t("Last sign-in"), render: lastSignIn, priority: 4 },
        menuColumn,
      ] as ServerColumn<AnyRow>[],
  },
  "signed-in-recently": {
    description: () => t("Everyone who signed in to the Member Portal in the last 30 days, newest first."),
    columns: () =>
      [
        nameColumn,
        familyColumn,
        { title: t("Last sign-in"), render: lastSignIn, priority: 3 },
        { title: t("How"), render: (row: PersonRow) => escapeHtml(row.lastMethodLabel ?? ""), priority: 6 },
        {
          title: t("Last seen in the portal"),
          render: (row: PersonRow) => (row.lastActivityDisplay ? escapeHtml(row.lastActivityDisplay) : muted("—")),
          priority: 7,
        },
        menuColumn,
      ] as ServerColumn<AnyRow>[],
  },
  "never-signed-in": {
    description: () => t("They may sign in but never have."),
    columns: () =>
      [
        nameColumn,
        familyColumn,
        { title: t("Email"), render: email, priority: 3 },
        menuColumn,
      ] as ServerColumn<AnyRow>[],
  },
  "email-attention": {
    description: () =>
      t(
        "The sign-in rules let them in, but their email does not: they have none, share it with someone else who may sign in, or it is not confirmed yet.",
      ),
    columns: () => attentionColumns(personMenu) as ServerColumn<AnyRow>[],
  },
  "families-confirmed": {
    description: () =>
      t("Families a member confirmed in the last 30 days, from the Member Portal or the emailed verification link."),
    columns: () =>
      [
        { title: t("Family"), render: (row: ConfirmationRow) => familyLink(row.familyId, row.familyName), priority: 1 },
        { title: t("Confirmed"), render: (row: ConfirmationRow) => escapeHtml(row.confirmedAtDisplay), priority: 3 },
        {
          title: t("By"),
          render: (row: ConfirmationRow) =>
            row.personId ? personLink(row.personId, row.personName) : muted(row.viaLabel),
          priority: 4,
        },
        {
          title: t("Result"),
          render: (row: ConfirmationRow) =>
            row.result === "no-change"
              ? `<span class="badge bg-success text-white">${escapeHtml(row.resultLabel)}</span>`
              : `<span class="badge bg-warning text-white">${escapeHtml(row.resultLabel)}</span>`,
          priority: 5,
        },
        { title: "", render: familyMenu, className: "text-end w-1 no-export", priority: 2 },
      ] as ServerColumn<AnyRow>[],
  },
  "family-emails-not-moved": {
    description: () =>
      t(
        "The 7.9.0 upgrade gave each family email to a person. These it could not, so it left a note in the family's timeline instead.",
      ),
    columns: () =>
      [
        { title: t("Family"), render: (row: NotMovedRow) => familyLink(row.familyId, row.familyName), priority: 1 },
        {
          title: t("Family email"),
          render: (row: NotMovedRow) => `<span class="text-break">${escapeHtml(row.email)}</span>`,
          priority: 3,
        },
        {
          title: t("Why"),
          render: (row: NotMovedRow) =>
            escapeHtml(row.reasonLabel) +
            (row.holderId ? `<div class="small mt-1">${personLink(row.holderId, row.holderName)}</div>` : ""),
          priority: 4,
        },
        { title: "", render: familyMenu, className: "text-end w-1 no-export", priority: 2 },
      ] as ServerColumn<AnyRow>[],
  },
};

function load(start: number, length: number, search: string): Promise<PageResult<AnyRow>> {
  return getJSON<PageResult<AnyRow>>(`member-portal/lists/${currentList}`, {
    start,
    length,
    search,
    problem: currentList === "email-attention" ? currentProblem : "",
    kind: currentList === "email-attention" ? (attention?.kind() ?? "") : "",
  });
}

function showList(list: string): void {
  const definition = LISTS[list];
  const tableElement = document.getElementById(TABLE_ID) as HTMLTableElement | null;
  if (!definition || !tableElement) {
    return;
  }
  currentList = list;

  for (const button of document.querySelectorAll<HTMLButtonElement>("#memberListPicker [data-list]")) {
    const active = button.dataset.list === list;
    button.classList.toggle("active", active);
    button.setAttribute("aria-selected", active ? "true" : "false");
  }
  const title = document.getElementById("memberListTitle");
  const picked = document.querySelector<HTMLElement>(`#memberListPicker [data-list="${list}"] span:first-child`);
  if (title && picked) {
    title.textContent = picked.textContent?.trim() ?? "";
  }
  const description = document.getElementById("memberListDescription");
  if (description) {
    description.textContent = definition.description();
  }
  document.getElementById("memberListProblemBar")?.classList.toggle("d-none", list !== "email-attention");
  document.getElementById("memberListMoveCounts")?.classList.toggle("d-none", list !== "family-emails-not-moved");

  table = serverTable<AnyRow>(tableElement, definition.columns(), load, { onDraw: () => attention?.afterDraw() });
}

async function loadSummary(): Promise<void> {
  let summary: ListSummary;
  try {
    summary = await getJSON<ListSummary>("member-portal/lists");
  } catch {
    return;
  }
  for (const badge of document.querySelectorAll<HTMLElement>("[data-count-for]")) {
    const count = summary[badge.dataset.countFor ?? ""];
    badge.textContent = typeof count === "number" ? String(count) : "";
  }
  attention?.applyCounts(summary);
  for (const cell of document.querySelectorAll<HTMLElement>("[data-move-count]")) {
    cell.textContent = String(summary.familyEmailMoves?.[cell.dataset.moveCount ?? ""] ?? 0);
  }
}

function refresh(): void {
  table?.draw(false);
  void loadSummary();
}

/** Redraw the lists after a sign-in rule change, once the tab has drawn them. */
export function refreshMemberLists(): void {
  if (table) {
    refresh();
  }
}

function setProblem(problem: string): void {
  currentProblem = problem;
  for (const other of document.querySelectorAll<HTMLButtonElement>("#memberListProblems [data-problem]")) {
    other.classList.toggle("active", (other.dataset.problem ?? "") === problem);
  }
  attention?.setProblem(problem);
}

/** The Sign-in tab's "Fix shared emails" link: this tab, the shared addresses, and the preview. */
function openSharedEmailFix(): void {
  const link = document.getElementById("portal-members-tab");
  const tabs = (window.bootstrap as unknown as { Tab?: { getOrCreateInstance(el: Element): { show(): void } } }).Tab;
  if (link && tabs) {
    tabs.getOrCreateInstance(link).show();
  }
  setProblem("shared_email");
  if (currentList === "email-attention") {
    table?.draw();
  } else {
    showList("email-attention");
  }
  attention?.openSharedEmailFix();
}

export function wireMembersTab(): void {
  const picker = document.getElementById("memberListPicker");
  const tableElement = document.getElementById(TABLE_ID) as HTMLTableElement | null;
  if (!picker || !tableElement) {
    return;
  }
  attention = wireEmailAttentionTools({
    table: tableElement,
    csrfToken: () => (window.CRM?.memberPortalAdmin as { csrfToken?: string } | undefined)?.csrfToken ?? "",
    onChanged: refresh,
    onFilter: () => table?.draw(),
  });

  picker.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>("[data-list]");
    if (button?.dataset.list && button.dataset.list !== currentList) {
      showList(button.dataset.list);
    }
  });

  document.getElementById("memberListProblems")?.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>("[data-problem]");
    if (!button) {
      return;
    }
    setProblem(button.dataset.problem ?? "");
    table?.draw();
  });

  for (const link of document.querySelectorAll<HTMLElement>("[data-open-shared-email-fix]")) {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      openSharedEmailFix();
    });
  }

  onTabShown("portal-members-tab", () => {
    void loadSummary();
    showList(currentList);
  });
}
