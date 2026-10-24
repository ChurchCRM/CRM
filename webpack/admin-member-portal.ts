/**
 * Admin → Member Portal (#9864) — the page's behaviour.
 *
 * The tabs are rendered server-side; this bundle adds what needs the browser:
 *
 *   1. three Settings Panels for the portal ConfigItems, each in the tab it
 *      belongs to, saving through POST /admin/api/system/config/{name}:
 *      the calendar switch (Calendars), Developer mode (Themes), and the
 *      birthday and volunteering switches (Permissions);
 *   2. the Themes tab's row actions: Check re-runs the validator and Activate
 *      goes through POST /admin/api/member-portal/theme so the validator can
 *      refuse. Either one shows what it found in the row under that theme;
 *   3. a refresh of the statistics numbers when that tab is opened;
 *   4. the Calendars tab's save, which posts the switched-on calendars to
 *      POST /admin/api/member-portal/calendars;
 *   5. the Sign-in tab's save, which posts every sign-in setting to
 *      POST /admin/api/member-portal/sign-in. The server checks them all and
 *      saves none when one is wrong, naming each problem.
 *
 * The Members, Permissions and Audit tabs (member sign-in design §11.2, §8.6)
 * live in webpack/admin-member-portal/ and read their rows a page at a time.
 *
 * Every user-visible string goes through i18next — the *global* instance the
 * locale loader initialises, never a bundled copy, which would be empty — and
 * is resolved inside the handlers, because i18next is only populated once the
 * locale loader has run.
 */
import { wireAuditTab } from "./admin-member-portal/audit-tab";
import { wireMembersTab } from "./admin-member-portal/members-tab";
import { loadPermissionsCatalog, wirePermissionsTab } from "./admin-member-portal/permissions-tab";

interface ThemeFinding {
  file: string;
  line: number;
  message: string;
  level: "error" | "warning";
}

interface ThemeResponse {
  name: string;
  activated?: boolean;
  status: "ok" | "warning" | "error";
  message?: string;
  findings: ThemeFinding[];
}

interface PortalStats {
  maySignIn: number;
  activeNow: number;
  signedIn24Hours: number;
  signedIn7Days: number;
  signedIn30Days: number;
  neverSignedIn: number;
}

const CALENDARS_TABLE_ID = "portalCalendarsTable";
const CALENDARS_STATUS_ID = "portalCalendarsStatus";

const SIGN_IN_FORM_ID = "memberSignInForm";
const SIGN_IN_SWITCHES = ["bMemberSignIn", "bPortalPasskeys", "bAllowLoginAsUser", "bLoginAsUserCanWrite"];
const SIGN_IN_NUMBERS = [
  "iMemberSignInMinAge",
  "iSignInLinkMinutes",
  "iMemberSignInRequestsPerIP",
  "iPortalIdleMinutes",
  "iRememberDeviceDays",
];
const SIGN_IN_CHECKBOX_LISTS = ["sMemberSignInFamilyRoles", "sMemberSignInClassifications"];
const SIGN_IN_OFFICE_LIST = "sMemberSignInNotificationRecipientIDs";

interface SignInSaveResponse {
  success?: boolean;
  message?: string;
  errors?: Record<string, string>;
  maySignInCount?: number;
  sharedAddresses?: number;
}

const root = (): string => window.CRM?.root ?? "";

const csrfToken = (): string => {
  const config = window.CRM?.memberPortalAdmin as { csrfToken?: string } | undefined;
  return config?.csrfToken ?? "";
};

/**
 * Translate through the global i18next the locale loader set up. A bundled
 * `import i18next` would be a second, never-initialised instance whose `t()`
 * returns an empty string, so every label on the page would render blank.
 */
function t(key: string): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key);
  return translated === undefined || translated === "" ? key : translated;
}

function escapeHtml(value: string): string {
  const div = document.createElement("div");
  div.textContent = value;
  return div.innerHTML;
}

/** Class, icon and label for a validation summary. */
function statusPresentation(status: string): { cssClass: string; icon: string; label: string } {
  if (status === "error") {
    return { cssClass: "bg-danger text-white", icon: "fa-circle-xmark", label: t("Errors") };
  }
  if (status === "warning") {
    return { cssClass: "bg-warning text-dark", icon: "fa-triangle-exclamation", label: t("Warnings") };
  }
  return { cssClass: "bg-success text-white", icon: "fa-circle-check", label: t("Valid") };
}

function setBusy(button: HTMLButtonElement | null, busy: boolean): void {
  if (!button) {
    return;
  }
  button.disabled = busy;
}

function themeRow(name: string): HTMLTableRowElement | null {
  return document.querySelector<HTMLTableRowElement>(`#portalThemesTable tr[data-theme="${CSS.escape(name)}"]`);
}

/**
 * The findings list for one theme. An empty list is a statement in its own
 * right — the designer asked a question and deserves an answer.
 */
function findingsHtml(response: ThemeResponse): string {
  if (response.findings.length === 0) {
    return `<div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-2"></i>${escapeHtml(
      t("This theme has no problems."),
    )}</div>`;
  }

  const rows = response.findings
    .map((finding) => {
      const badgeClass = finding.level === "error" ? "bg-danger text-white" : "bg-warning text-dark";
      const badgeLabel = finding.level === "error" ? t("Error") : t("Warning");
      // A file:line pair is the same in every language — no translation needed.
      const where = finding.file
        ? `<code>${escapeHtml(finding.file)}${finding.line > 0 ? `:${finding.line}` : ""}</code> `
        : "";
      return `<li class="py-1"><span class="badge ${badgeClass} me-2">${escapeHtml(badgeLabel)}</span>${where}<span>${escapeHtml(finding.message)}</span></li>`;
    })
    .join("");

  const heading = response.activated === false ? t("This theme cannot be activated") : t("What the check found");
  return `<div class="fw-medium mb-1">${escapeHtml(heading)}</div><ul class="list-unstyled mb-0">${rows}</ul>`;
}

/** Repaint one theme's row with a fresh result and open the findings under it. */
function paintThemeResult(name: string, response: ThemeResponse): void {
  const row = themeRow(name);
  const findingsRow = document.getElementById(`portalFindings-${name}`);
  if (!row || !findingsRow) {
    return;
  }

  const badge = row.querySelector<HTMLElement>(".portal-theme-status");
  if (badge) {
    const presentation = statusPresentation(response.status);
    badge.className = `badge ${presentation.cssClass} portal-theme-status`;
    badge.innerHTML = `<i class="fa-solid ${presentation.icon} me-1"></i>${escapeHtml(presentation.label)}`;
  }
  row.querySelector(".portal-theme-findings-toggle")?.classList.toggle("d-none", response.findings.length === 0);
  const count = row.querySelector(".portal-theme-findings-count");
  if (count) {
    count.textContent = String(response.findings.length);
  }

  const cell = findingsRow.querySelector(".portal-theme-findings");
  if (cell) {
    cell.innerHTML = findingsHtml(response);
  }
  window.bootstrap.Collapse?.getOrCreateInstance(findingsRow, { toggle: false }).show();
}

/** Run the validator against one theme and show what it found. */
async function checkTheme(name: string): Promise<void> {
  if (!name) {
    return;
  }
  const response = await fetch(`${root()}/admin/api/member-portal/theme/${encodeURIComponent(name)}/validation`);
  if (!response.ok) {
    window.CRM?.notify?.(t("That theme could not be checked."), { type: "danger" });
    return;
  }
  paintThemeResult(name, await response.json());
}

/**
 * Activate a theme. A 409 means the validator refused it: the findings are
 * listed and nothing was written, so the portal keeps the theme it had.
 */
async function activateTheme(name: string): Promise<void> {
  if (!name) {
    return;
  }
  const response = await fetch(`${root()}/admin/api/member-portal/theme`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ name }),
  });

  let body: ThemeResponse | null = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  if (response.status === 409 && body) {
    paintThemeResult(name, { ...body, status: "error", activated: false });
    window.CRM?.notify?.(t("That theme has errors, so it was not activated."), { type: "danger" });
    return;
  }

  if (!response.ok || !body) {
    window.CRM?.notify?.(t("That theme could not be activated."), { type: "danger" });
    return;
  }

  paintThemeResult(name, body);
  window.CRM?.notify?.(t("The Member Portal theme was changed."), { type: "success" });
  window.setTimeout(() => window.location.reload(), 1500);
}

/** Re-read the statistics so an admin who leaves the page open sees fresh numbers. */
async function refreshStatistics(): Promise<void> {
  const response = await fetch(`${root()}/admin/api/member-portal/stats`);
  if (!response.ok) {
    return;
  }
  const stats: PortalStats = await response.json();
  for (const [key, value] of Object.entries(stats)) {
    const cell = document.getElementById(`portalStat-${key}`);
    if (cell && typeof value === "number") {
      cell.textContent = String(value);
    }
  }
}

function wireThemeControls(): void {
  for (const button of document.querySelectorAll<HTMLButtonElement>(".portal-theme-activate")) {
    button.addEventListener("click", () => activateTheme(button.dataset.theme ?? ""));
  }
  for (const button of document.querySelectorAll<HTMLButtonElement>(".portal-theme-check")) {
    button.addEventListener("click", () => checkTheme(button.dataset.theme ?? ""));
  }
}

/**
 * The portal ConfigItems carry no System Settings category, so each lives in
 * the tab it affects, and a link to that page would be a dead end.
 */
function wireSettingsPanels(): void {
  const SettingsPanel = window.CRM?.SettingsPanel;
  if (!SettingsPanel) {
    return;
  }
  const panel = (
    container: string,
    options: { title: string; icon: string; settings: CRMSettingsPanelSetting[]; onSave?: () => void },
  ): void => {
    if (document.querySelector(container)) {
      new SettingsPanel().init({ container, showAllSettingsLink: false, autoSave: true, ...options });
    }
  };

  panel("#portalCalendarSectionPanel", {
    title: t("Calendar section"),
    icon: "fa-solid fa-calendar-days",
    settings: [
      {
        name: "bPortalShowCalendar",
        type: "boolean",
        label: t("Show the church calendar"),
        tooltip: t("Show the church calendar section in the Member Portal."),
      },
    ],
  });

  panel("#portalDeveloperModePanel", {
    title: t("Theme development"),
    icon: "fa-solid fa-code",
    settings: [
      {
        name: "bPortalDeveloperMode",
        type: "boolean",
        label: t("Developer mode"),
        tooltip: t(
          "Turns off the portal template cache and prints the name of each template in an HTML comment. For theme designers; leave this off in normal use.",
        ),
      },
    ],
  });

  // Both switches feed the catalog's On/Off column, so it is redrawn after a save.
  panel("#portalMemberFeaturesPanel", {
    title: t("What members can use"),
    icon: "fa-solid fa-toggle-on",
    settings: [
      {
        name: "bPortalAllowBirthdayEdit",
        type: "boolean",
        label: t("Let members edit birthdays"),
        tooltip: t("Allow members to change their own and their family members' birthdays."),
      },
      {
        name: "bPortalShowVolunteer",
        type: "boolean",
        label: t("Show volunteering"),
        tooltip: t("Show the volunteering and team sections in the Member Portal."),
      },
    ],
    onSave: () => void loadPermissionsCatalog(),
  });
}

/**
 * Save the Calendars tab: the set of switched-on calendars, as the whole list
 * rather than a diff, so what the server stores is exactly what the page shows.
 */
async function saveCalendars(button: HTMLButtonElement | null): Promise<void> {
  const table = document.getElementById(CALENDARS_TABLE_ID);
  const status = document.getElementById(CALENDARS_STATUS_ID);
  if (!table) {
    return;
  }

  const visible: Array<{ type: string; id: number }> = [];
  for (const row of table.querySelectorAll<HTMLTableRowElement>("tr[data-calendar-type]")) {
    const input = row.querySelector<HTMLInputElement>(".portal-calendar-switch");
    if (!input?.checked) {
      continue;
    }
    visible.push({
      type: row.dataset.calendarType ?? "",
      id: Number.parseInt(row.dataset.calendarId ?? "0", 10),
    });
  }

  setBusy(button, true);
  if (status) {
    status.textContent = "";
  }

  const response = await fetch(`${root()}/admin/api/member-portal/calendars`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ visible }),
  });
  setBusy(button, false);

  if (!response.ok) {
    window.CRM?.notify?.(t("The calendars could not be saved."), { type: "danger" });
    return;
  }

  if (status) {
    status.textContent =
      visible.length === 0
        ? t("No calendar is shared with members.")
        : t("Saved. Members see the calendars switched on above.");
  }
  window.CRM?.notify?.(t("The Member Portal calendars were saved."), { type: "success" });
}

function wireCalendarControls(): void {
  const button = document.getElementById("portalCalendarsSaveButton") as HTMLButtonElement | null;
  button?.addEventListener("click", () => {
    void saveCalendars(button);
  });
}

function collectSignInSettings(form: HTMLFormElement): Record<string, unknown> {
  const settings: Record<string, unknown> = {};
  for (const name of SIGN_IN_SWITCHES) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      settings[name] = input.checked;
    }
  }
  for (const name of SIGN_IN_NUMBERS) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      settings[name] = input.value.trim();
    }
  }
  for (const name of SIGN_IN_CHECKBOX_LISTS) {
    settings[name] = Array.from(form.querySelectorAll<HTMLInputElement>(`input[name="${name}"]:checked`), (input) =>
      Number.parseInt(input.value, 10),
    );
  }
  const office = form.querySelector<HTMLSelectElement>(`select[name="${SIGN_IN_OFFICE_LIST}"]`);
  if (office) {
    const picked = office.tomselect ? office.tomselect.getValue() : Array.from(office.selectedOptions, (o) => o.value);
    settings[SIGN_IN_OFFICE_LIST] = (Array.isArray(picked) ? picked : picked.split(","))
      .filter((value) => value !== "")
      .map((value) => Number.parseInt(value, 10));
  }
  return settings;
}

function paintSignInErrors(form: HTMLFormElement, errors: Record<string, string>): void {
  for (const slot of form.querySelectorAll<HTMLElement>("[data-error-for]")) {
    slot.textContent = errors[slot.dataset.errorFor ?? ""] ?? "";
  }
  for (const input of form.querySelectorAll<HTMLElement>(".is-invalid")) {
    input.classList.remove("is-invalid");
  }
  for (const name of Object.keys(errors)) {
    for (const input of form.querySelectorAll<HTMLElement>(`[name="${name}"]`)) {
      input.classList.add("is-invalid");
    }
  }
}

async function saveSignInSettings(form: HTMLFormElement, button: HTMLButtonElement | null): Promise<void> {
  const status = document.getElementById("memberSignInStatus");
  setBusy(button, true);
  if (status) {
    status.textContent = "";
  }

  const response = await fetch(`${root()}/admin/api/member-portal/sign-in`, {
    method: "POST",
    headers: { "content-type": "application/json", "X-CSRF-Token": csrfToken() },
    body: JSON.stringify(collectSignInSettings(form)),
  });
  setBusy(button, false);

  let body: SignInSaveResponse | null = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  if (response.status === 400 && body?.errors) {
    paintSignInErrors(form, body.errors);
    window.CRM?.notify?.(t("Some sign-in settings need fixing. Nothing was saved."), { type: "danger" });
    return;
  }
  if (!response.ok) {
    window.CRM?.notify?.(t("The sign-in settings could not be saved."), { type: "danger" });
    return;
  }

  paintSignInErrors(form, {});
  const count = document.getElementById("memberSignInMayCount");
  if (count && typeof body?.maySignInCount === "number") {
    count.textContent = String(body.maySignInCount);
  }
  if (typeof body?.sharedAddresses === "number") {
    for (const shared of document.querySelectorAll<HTMLElement>("[data-shared-address-count]")) {
      shared.textContent = String(body.sharedAddresses);
    }
    document.getElementById("memberSignInSharedEmails")?.classList.toggle("d-none", body.sharedAddresses === 0);
  }
  const memberSignIn = form.querySelector<HTMLInputElement>('input[name="bMemberSignIn"]');
  if (memberSignIn && !memberSignIn.checked && document.getElementById("memberSignInEmailProblem")) {
    memberSignIn.disabled = true;
  }
  if (status) {
    status.textContent = t("Saved.");
  }
  window.CRM?.notify?.(t("The sign-in settings were saved."), { type: "success" });
}

function wireSignInTab(): void {
  const form = document.getElementById(SIGN_IN_FORM_ID) as HTMLFormElement | null;
  if (!form) {
    return;
  }

  const office = form.querySelector<HTMLSelectElement>(`select[name="${SIGN_IN_OFFICE_LIST}"]`);
  if (office && typeof window.CRM?.initPersonSelect === "function") {
    window.CRM.initPersonSelect(office, {
      endpoint: "/admin/api/member-portal/sign-in/office-candidates/",
      plugins: ["remove_button"],
    });
  }

  const button = document.getElementById("memberSignInSaveButton") as HTMLButtonElement | null;
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    void saveSignInSettings(form, button);
  });
}

/** Open the tab a link points at, such as /admin/member-portal#portal-signin. */
function openTabFromHash(): void {
  const hash = window.location.hash;
  if (!/^#portal-[a-z-]+$/.test(hash)) {
    return;
  }
  const link = document.getElementById(`${hash.slice(1)}-tab`);
  const tabs = (window.bootstrap as unknown as { Tab?: { getOrCreateInstance(el: Element): { show(): void } } }).Tab;
  if (link && tabs) {
    tabs.getOrCreateInstance(link).show();
  }
}

function start(): void {
  wireThemeControls();
  wireSettingsPanels();
  wireCalendarControls();
  wireSignInTab();
  wireMembersTab();
  wirePermissionsTab();
  wireAuditTab();
  openTabFromHash();
  window.addEventListener("hashchange", openTabFromHash);
  document.getElementById("portal-statistics-tab")?.addEventListener("click", () => {
    void refreshStatistics();
  });
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(start);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
