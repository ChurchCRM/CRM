/**
 * Admin → Member Portal → Members (member sign-in design §11.2, A7, §10.1):
 * the lists of who may sign in, who has, whose email stops them, recent family
 * confirmations and the family emails the 7.9.0 upgrade could not move. Each
 * list reads GET /admin/api/member-portal/lists/{list} a page at a time.
 */
import {
  escapeAttribute,
  escapeHtml,
  familyLink,
  getJSON,
  muted,
  onTabShown,
  type PageResult,
  personLink,
  root,
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
  problem?: string;
  problemLabel?: string;
  sharedWith?: Array<{ personId: number; name: string }>;
  unconfirmedEmail: string | null;
  unconfirmedSource: string | null;
  unconfirmedSourceLabel: string | null;
  unconfirmedAt: string | null;
  canConfirmEmail?: boolean;
  confirmEmailRefusal?: string | null;
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

interface ListSummary {
  [list: string]: unknown;
  emailProblems: Record<string, number>;
  familyEmailMoves: Record<string, number>;
}

type AnyRow = PersonRow | ConfirmationRow | NotMovedRow;

const TABLE_ID = "memberListTable";

let currentList = "may-sign-in";
let currentProblem = "";
let table: DataTablesApi | null = null;

const email = (row: PersonRow): string =>
  row.email ? `<span class="text-break">${escapeHtml(row.email)}</span>` : muted(t("None"));
const lastSignIn = (row: PersonRow): string =>
  row.lastSignInDisplay ? escapeHtml(row.lastSignInDisplay) : muted(t("Never"));

const personMenu = (row: PersonRow): string =>
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

function problemCell(row: PersonRow): string {
  const label = `<span class="badge bg-warning text-white">${escapeHtml(row.problemLabel ?? "")}</span>`;
  if (row.problem === "shared_email" && row.sharedWith?.length) {
    const others = row.sharedWith.map((other) => personLink(other.personId, other.name)).join(", ");
    return `${label}<div class="small mt-1">${escapeHtml(t("Also used by"))}: ${others}</div>`;
  }
  if (row.problem === "email_unconfirmed") {
    return `${label}<div class="small text-secondary mt-1">${escapeHtml(row.unconfirmedSourceLabel ?? "")}</div>`;
  }
  return label;
}

/**
 * What staff can do about the row. An unconfirmed address is confirmed here,
 * after checking who the person is, when §9 lets the reader; the rest are
 * fixed on the person's record.
 */
export function attentionFixCell(row: PersonRow): string {
  const attributes = `data-cy="attention-fix" data-person-id="${row.personId}" data-problem="${escapeAttribute(row.problem ?? "")}"`;
  if (row.problem === "email_unconfirmed") {
    if (row.canConfirmEmail) {
      return `<div ${attributes}><button type="button" class="btn btn-sm btn-outline-primary member-list-confirm-email"
        data-person-id="${row.personId}" data-email="${escapeAttribute(row.unconfirmedEmail ?? "")}">
        <i class="fa-solid fa-check me-1"></i>${escapeHtml(t("Confirm Email Address"))}</button></div>`;
    }
    return `<div ${attributes}>${muted(row.confirmEmailRefusal ?? "")}</div>`;
  }
  const label = row.problem === "shared_email" ? t("Give each person their own email") : t("Add an email");
  const href = `${root()}/PersonEditor.php?PersonID=${row.personId}`;
  return `<div ${attributes}><a class="btn btn-sm btn-outline-primary" href="${escapeAttribute(href)}">${escapeHtml(label)}</a></div>`;
}

async function confirmEmail(button: HTMLButtonElement): Promise<void> {
  const config = window.CRM?.memberPortalAdmin as { csrfToken?: string } | undefined;
  button.disabled = true;
  const response = await fetch(`${root()}/api/person/${button.dataset.personId}/sign-in/confirm-email`, {
    method: "POST",
    credentials: "same-origin",
    headers: { "content-type": "application/json", "X-CSRF-Token": config?.csrfToken ?? "" },
    body: JSON.stringify({ email: button.dataset.email ?? "" }),
  });
  button.disabled = false;
  if (!response.ok) {
    const body = (await response.json().catch(() => null)) as { message?: string } | null;
    const message = body?.message || t("The email could not be confirmed.");
    window.CRM?.notify?.(message, { type: "danger" });
    return;
  }
  window.CRM?.notify?.(t("The email is confirmed. It can now be used to sign in."), { type: "success" });
  table?.draw(false);
  void loadSummary();
}

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
    columns: () =>
      [
        nameColumn,
        { title: t("Problem"), render: problemCell, priority: 3 },
        { title: t("Email"), render: email, priority: 5 },
        { title: t("What to do"), render: attentionFixCell, priority: 4 },
        menuColumn,
      ] as ServerColumn<AnyRow>[],
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

  table = serverTable<AnyRow>(tableElement, definition.columns(), load);
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
  for (const badge of document.querySelectorAll<HTMLElement>("[data-problem-count]")) {
    badge.textContent = String(summary.emailProblems?.[badge.dataset.problemCount ?? ""] ?? 0);
  }
  for (const cell of document.querySelectorAll<HTMLElement>("[data-move-count]")) {
    cell.textContent = String(summary.familyEmailMoves?.[cell.dataset.moveCount ?? ""] ?? 0);
  }
}

export function wireMembersTab(): void {
  const picker = document.getElementById("memberListPicker");
  if (!picker) {
    return;
  }

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
    currentProblem = button.dataset.problem ?? "";
    for (const other of document.querySelectorAll<HTMLButtonElement>("#memberListProblems [data-problem]")) {
      other.classList.toggle("active", other === button);
    }
    table?.draw();
  });

  document.getElementById(TABLE_ID)?.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>(".member-list-confirm-email");
    if (!button) {
      return;
    }
    window.bootbox?.confirm({
      title: t("Confirm Email Address"),
      message: t(
        "Confirm this email only after checking, in person or by phone, that it is this person's own address. Once confirmed, it can be used to sign in to the Member Portal.",
      ),
      buttons: { confirm: { label: t("Confirm Email Address"), className: "btn-primary" } },
      callback: (confirmed: boolean) => {
        if (confirmed) {
          void confirmEmail(button);
        }
      },
    });
  });

  onTabShown("portal-members-tab", () => {
    void loadSummary();
    showList(currentList);
  });
}
