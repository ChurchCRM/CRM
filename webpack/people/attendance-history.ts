/**
 * Attendance History tab module for the person-view page.
 *
 * Fetches attendance data lazily on first Bootstrap tab `shown.bs.tab` event
 * and renders it into the #attendance tab pane. Subsequent activations use
 * cached data (no repeated network requests). On transient error the load flag
 * is reset so the user can retry by re-activating the tab.
 *
 * i18n strings are read from the `data-i18n` JSON attribute on #attendance-tab
 * (injected by attendance-tab.php) — no inline <script> needed.
 */

/**
 * Sentinel used as the <option> value for attendance records whose
 * eventTypeId is null. Must be a non-empty string that cannot collide
 * with a real type ID or with the "All types" empty-string option.
 */
const NULL_TYPE_SENTINEL = "__null__";

interface AttendanceRecord {
  attendId: number;
  eventId: number;
  eventUrl: string;
  eventTitle: string;
  eventTypeId: number | null;
  eventTypeName: string;
  eventStart: string;
  eventEnd: string;
  checkinDate: string | null;
  checkoutDate: string | null;
  eventInactive: boolean;
}

interface AttendanceSummary {
  totalEvents: number;
  lastAttendanceDate: string | null;
  streaks: Array<{ typeId: number | null; typeName: string; length: number }>;
}

interface AttendanceData {
  records: AttendanceRecord[];
  summary: AttendanceSummary;
}

interface I18n {
  loading: string;
  errorLoading: string;
  noRecords: string;
  filterByType: string;
  allTypes: string;
  dateFrom: string;
  dateTo: string;
  clearFilters: string;
  colEvent: string;
  colType: string;
  colDate: string;
  colCheckin: string;
  colCheckout: string;
  inactive: string;
  totalEvents: string;
  lastAttendance: string;
  currentStreak: string;
  streakEvents: string;
  never: string;
  noStreak: string;
}

/**
 * Return the church timezone string from window.CRM, falling back to "UTC".
 * ChurchCRM stores all timestamps as wall-clock strings in the church's timezone.
 */
function churchTimeZone(): string {
  return window.CRM?.timeZone ?? "UTC";
}

/**
 * Parse a MySQL-style "YYYY-MM-DD HH:MM:SS" wall-clock string into a Date
 * whose UTC instant represents the same wall-clock moment in the church timezone.
 *
 * Problem: `new Date("YYYY-MM-DDTHH:mm:ss")` is parsed in the *browser* timezone,
 * which may differ from the church timezone. Feeding that Date to
 * `Intl.DateTimeFormat({ timeZone: churchTz })` would shift the displayed time.
 *
 * Solution: treat the string as a naive (timezone-free) wall-clock value in the
 * church timezone. Determine the UTC offset that the church timezone applies to
 * that wall-clock moment via `Intl.DateTimeFormat.formatToParts`, then adjust
 * so the resulting Date encodes the correct UTC instant.
 */
function parseWallClock(isoString: string): Date {
  const clean = isoString.replace(" ", "T");
  const tz = window.CRM?.timeZone ?? "UTC";

  // Step 1: treat the wall-clock string as UTC to get a reference ms value.
  const parts = clean.slice(0, 19).split(/[-T:]/).map(Number) as [number, number, number, number, number, number];
  const naiveMs = Date.UTC(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5]);

  // Step 2: find what wall-clock time the church timezone displays for naiveMs.
  const dtfParts = new Intl.DateTimeFormat("en-CA", {
    timeZone: tz,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  }).formatToParts(new Date(naiveMs));

  const get = (type: string): number => Number(dtfParts.find((p) => p.type === type)?.value ?? "0");

  // Step 3: the difference between naiveMs (treated as UTC) and the church
  // wall-clock reading of naiveMs is the UTC offset. Apply it to get the
  // correct UTC instant for the original wall-clock string.
  const localMs = Date.UTC(get("year"), get("month") - 1, get("day"), get("hour"), get("minute"), get("second"));
  return new Date(naiveMs + (naiveMs - localMs));
}

/**
 * Format a wall-clock ISO datetime string as a locale-aware date.
 * Uses Intl.DateTimeFormat with the church timezone to avoid silent
 * date shifts when the browser is in a different timezone.
 */
function formatDate(isoString: string | null): string {
  if (!isoString) return "";
  try {
    return new Intl.DateTimeFormat(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      timeZone: churchTimeZone(),
    }).format(parseWallClock(isoString));
  } catch {
    return isoString;
  }
}

/**
 * Format a wall-clock ISO datetime string as a locale-aware time (HH:MM).
 * Uses Intl.DateTimeFormat with the church timezone.
 */
function formatTime(isoString: string | null): string {
  if (!isoString) return "";
  try {
    return new Intl.DateTimeFormat(undefined, {
      hour: "2-digit",
      minute: "2-digit",
      timeZone: churchTimeZone(),
    }).format(parseWallClock(isoString));
  } catch {
    return isoString;
  }
}

/**
 * Escape HTML special characters to prevent XSS.
 */
function esc(value: string): string {
  const div = document.createElement("div");
  div.appendChild(document.createTextNode(value));
  return div.innerHTML;
}

/**
 * Render the attendance table body with optional filtering applied.
 *
 * Date-range filter compares the raw YYYY-MM-DD date prefix of `eventStart`
 * directly against the filter values to avoid timezone-related Date shifts.
 *
 * The type filter uses NULL_TYPE_SENTINEL for records with no event type,
 * matching the sentinel written by populateTypeFilter().
 */
function renderTable(
  tbody: HTMLTableSectionElement,
  emptyEl: HTMLElement,
  records: AttendanceRecord[],
  filterType: string,
  filterFrom: string,
  filterTo: string,
  i18n: I18n,
): void {
  const filtered = records.filter((rec) => {
    if (filterType) {
      // Use the same sentinel as populateTypeFilter() for null type IDs
      const recTypeKey = rec.eventTypeId !== null ? String(rec.eventTypeId) : NULL_TYPE_SENTINEL;
      if (recTypeKey !== filterType) return false;
    }
    if (filterFrom || filterTo) {
      // Compare the YYYY-MM-DD prefix directly — avoids timezone-induced shifts
      const recDate = rec.eventStart.slice(0, 10);
      if (filterFrom && recDate < filterFrom) return false;
      if (filterTo && recDate > filterTo) return false;
    }
    return true;
  });

  tbody.innerHTML = "";

  if (filtered.length === 0) {
    emptyEl.classList.remove("d-none");
    return;
  }
  emptyEl.classList.add("d-none");

  for (const rec of filtered) {
    const tr = document.createElement("tr");

    // Use eventUrl from the API response (server-computed, correct path)
    const eventCell = `<td>
      <a href="${esc(rec.eventUrl)}" class="fw-semibold">
        ${esc(rec.eventTitle)}
      </a>
      ${rec.eventInactive ? `<span class="badge bg-secondary ms-1">${esc(i18n.inactive)}</span>` : ""}
    </td>`;

    const typeCell = `<td><span class="text-body-secondary">${esc(rec.eventTypeName)}</span></td>`;
    const dateCell = `<td>${esc(formatDate(rec.eventStart))}</td>`;
    const checkinCell = `<td>${rec.checkinDate ? esc(formatTime(rec.checkinDate)) : '<span class="text-body-secondary">—</span>'}</td>`;
    const checkoutCell = `<td>${rec.checkoutDate ? esc(formatTime(rec.checkoutDate)) : '<span class="text-body-secondary">—</span>'}</td>`;

    tr.innerHTML = eventCell + typeCell + dateCell + checkinCell + checkoutCell;
    tbody.appendChild(tr);
  }
}

/**
 * Populate the event-type filter <select> from distinct types in records.
 *
 * Clears all options back to the default "All types" entry first, so that
 * a retry after a transient error does not append duplicate options.
 *
 * Null type IDs are stored under NULL_TYPE_SENTINEL so they don't collide
 * with the empty-string value used by the "All types" option.
 */
function populateTypeFilter(selectEl: HTMLSelectElement, records: AttendanceRecord[]): void {
  // Reset to only the default "All types" option (index 0) before re-populating
  while (selectEl.options.length > 1) {
    selectEl.remove(1);
  }

  const seen = new Map<string, string>();
  for (const rec of records) {
    const key = rec.eventTypeId !== null ? String(rec.eventTypeId) : NULL_TYPE_SENTINEL;
    if (!seen.has(key)) {
      seen.set(key, rec.eventTypeName);
    }
  }

  for (const [typeKey, typeName] of seen) {
    const opt = document.createElement("option");
    opt.value = typeKey;
    opt.textContent = typeName;
    selectEl.appendChild(opt);
  }
}

/**
 * Render summary stats cards.
 */
function renderSummary(container: HTMLElement, summary: AttendanceSummary, i18n: I18n): void {
  const totalEl = container.querySelector<HTMLElement>(".attendance-stat-total");
  const lastEl = container.querySelector<HTMLElement>(".attendance-stat-last");
  const streakEl = container.querySelector<HTMLElement>(".attendance-stat-streak");

  if (totalEl) {
    totalEl.textContent = String(summary.totalEvents);
  }

  if (lastEl) {
    lastEl.textContent = summary.lastAttendanceDate ? formatDate(summary.lastAttendanceDate) : i18n.never;
  }

  if (streakEl) {
    const bestStreak = summary.streaks.length > 0 ? summary.streaks[0] : null;
    if (bestStreak) {
      streakEl.textContent = `${bestStreak.length} ${i18n.streakEvents}`;
      streakEl.title = bestStreak.typeName;
    } else {
      streakEl.textContent = i18n.noStreak;
    }
  }
}

/**
 * Initialize the attendance history tab.
 *
 * Binds to `shown.bs.tab` on the #nav-item-attendance link and lazy-loads
 * attendance data on first activation.
 */
export function initAttendanceHistory(): void {
  const tabLink = document.getElementById("nav-item-attendance");
  const tabPane = document.getElementById("attendance-tab");

  if (!tabLink || !tabPane) {
    return;
  }

  const personId = tabPane.dataset.personId;
  const apiRoot = tabPane.dataset.apiRoot ?? "";
  const i18nRaw = tabPane.dataset.i18n;

  let i18n: I18n = {} as I18n;
  try {
    i18n = JSON.parse(i18nRaw ?? "{}") as I18n;
  } catch {
    // keep the empty fallback
  }

  const loadingEl = tabPane.querySelector<HTMLElement>(".attendance-loading");
  const summaryEl = tabPane.querySelector<HTMLElement>(".attendance-summary");
  const filtersEl = tabPane.querySelector<HTMLElement>(".attendance-filters");
  const tableWrapperEl = tabPane.querySelector<HTMLElement>(".attendance-table-wrapper");
  const errorEl = tabPane.querySelector<HTMLElement>(".attendance-error");
  const tbody = tabPane.querySelector<HTMLTableSectionElement>(".attendance-tbody");
  const emptyEl = tabPane.querySelector<HTMLElement>(".attendance-empty");
  const typeSelect = tabPane.querySelector<HTMLSelectElement>(".attendance-filter-type");
  const fromInput = tabPane.querySelector<HTMLInputElement>(".attendance-filter-from");
  const toInput = tabPane.querySelector<HTMLInputElement>(".attendance-filter-to");
  const clearBtn = tabPane.querySelector<HTMLButtonElement>(".attendance-filter-clear");

  if (!tbody || !emptyEl || !typeSelect || !fromInput || !toInput || !clearBtn) {
    return;
  }

  // Capture narrowed (non-null) references for use inside closures
  const safeTypeSelect = typeSelect;
  const safeFromInput = fromInput;
  const safeToInput = toInput;
  const safeTbody = tbody;
  const safeEmptyEl = emptyEl;
  const safeClearBtn = clearBtn;

  // loaded=true is set BEFORE the first await to prevent double-fetch race.
  // Reset to false in catch to allow retry on transient error.
  let loaded = false;
  let cachedRecords: AttendanceRecord[] = [];

  function applyFilters(): void {
    renderTable(
      safeTbody,
      safeEmptyEl,
      cachedRecords,
      safeTypeSelect.value,
      safeFromInput.value,
      safeToInput.value,
      i18n,
    );
  }

  safeTypeSelect.addEventListener("change", applyFilters);
  safeFromInput.addEventListener("change", applyFilters);
  safeToInput.addEventListener("change", applyFilters);
  safeClearBtn.addEventListener("click", () => {
    safeTypeSelect.value = "";
    safeFromInput.value = "";
    safeToInput.value = "";
    applyFilters();
  });

  async function loadData(): Promise<void> {
    if (loaded) return;
    // Set flag BEFORE the first await to close the race window where two rapid
    // tab activations could both pass the `if (loaded)` check.
    loaded = true;

    // Reset UI to loading state at the start of each attempt so that:
    // - a retry after error shows the spinner again and hides the error banner
    // - stale data/summary from a previous partial load is hidden
    if (errorEl) errorEl.classList.add("d-none");
    if (loadingEl) loadingEl.classList.remove("d-none");
    if (summaryEl) summaryEl.classList.add("d-none");
    if (tableWrapperEl) tableWrapperEl.classList.add("d-none");
    if (filtersEl) filtersEl.classList.add("d-none");

    try {
      const url = `${apiRoot}/api/attendance/person/${encodeURIComponent(personId ?? "")}`;
      const resp = await fetch(url, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });

      if (!resp.ok) {
        throw new Error(`HTTP ${resp.status}`);
      }

      const data = (await resp.json()) as AttendanceData;
      cachedRecords = data.records ?? [];

      // Render summary
      if (summaryEl) {
        renderSummary(summaryEl, data.summary, i18n);
        summaryEl.classList.remove("d-none");
      }

      // Populate type filter (clears options first to avoid duplicates on retry)
      populateTypeFilter(safeTypeSelect, cachedRecords);

      // Show filters and table
      if (filtersEl) filtersEl.classList.remove("d-none");
      if (tableWrapperEl) tableWrapperEl.classList.remove("d-none");

      // Render table
      applyFilters();
    } catch {
      // Allow retry on transient network errors
      loaded = false;
      if (errorEl) errorEl.classList.remove("d-none");
    } finally {
      if (loadingEl) loadingEl.classList.add("d-none");
    }
  }

  // Lazy load on first tab activation
  tabLink.addEventListener("shown.bs.tab", () => {
    void loadData();
  });
}
