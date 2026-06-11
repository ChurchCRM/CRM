/**
 * Attendance History tab module for the person-view page.
 *
 * Fetches attendance data lazily on first Bootstrap tab `shown.bs.tab` event
 * and renders it into the #attendance tab pane. Subsequent activations use
 * cached data (no repeated network requests).
 *
 * i18n strings are read from the `data-i18n` JSON attribute on #attendance-tab
 * (injected by attendance-tab.php) — no inline <script> needed.
 */

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
 * Parse a MySQL-style "YYYY-MM-DD HH:MM:SS" wall-clock string into a Date,
 * interpreting it as being in the church's local timezone (not the browser's).
 * We achieve this by replacing the space separator with "T" so it can be
 * fed to Intl.DateTimeFormat which handles the timezone offset correctly.
 */
function parseWallClock(isoString: string): Date {
  // Replace space with "T" if present (MySQL datetime format)
  return new Date(isoString.replace(" ", "T"));
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
    if (filterType && String(rec.eventTypeId ?? "") !== filterType) {
      return false;
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
 */
function populateTypeFilter(selectEl: HTMLSelectElement, records: AttendanceRecord[]): void {
  const seen = new Map<string, string>();
  for (const rec of records) {
    const key = String(rec.eventTypeId ?? "");
    if (!seen.has(key)) {
      seen.set(key, rec.eventTypeName);
    }
  }

  for (const [typeId, typeName] of seen) {
    const opt = document.createElement("option");
    opt.value = typeId;
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

      // Populate type filter
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
