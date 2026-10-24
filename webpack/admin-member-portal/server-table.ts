/**
 * A DataTable that reads one page at a time from an admin API returning
 * `{total, filtered, rows}`, for the Members, Permissions and Audit tabs of
 * Admin → Member Portal. Columns are not sortable: each list has one order,
 * chosen on the server.
 */

export interface PageResult<T> {
  total: number;
  filtered: number;
  rows: T[];
}

export interface ServerColumn<T> {
  title: string;
  render: (row: T) => string;
  className?: string;
  /** DataTables Responsive: lower numbers stay visible longest on a narrow screen. */
  priority?: number;
}

export type PageLoader<T> = (start: number, length: number, search: string) => Promise<PageResult<T>>;

interface DataTablesRequest {
  draw: number;
  start: number;
  length: number;
  search?: { value?: string };
}

interface DataTablesReply {
  draw: number;
  recordsTotal: number;
  recordsFiltered: number;
  data: unknown[];
}

const MAX_PAGE = 100;

/** Translate through the global i18next the locale loader set up. */
export function t(key: string, options?: Record<string, unknown>): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key, options);
  return translated === undefined || translated === "" ? key : String(translated);
}

export function escapeHtml(value: unknown): string {
  return window.CRM?.escapeHtml ? window.CRM.escapeHtml(String(value ?? "")) : String(value ?? "");
}

export function escapeAttribute(value: unknown): string {
  return window.CRM?.escapeAttribute ? window.CRM.escapeAttribute(String(value ?? "")) : String(value ?? "");
}

export const root = (): string => window.CRM?.root ?? "";

export const muted = (text: string): string => `<span class="text-secondary">${escapeHtml(text)}</span>`;

export function personLink(personId: number | null, name: string | null): string {
  if (!personId) {
    return muted("—");
  }
  return `<a href="${root()}/people/view/${personId}">${escapeHtml(name || `#${personId}`)}</a>`;
}

export function familyLink(familyId: number | null, name: string | null): string {
  if (!familyId) {
    return muted("—");
  }
  return `<a href="${root()}/people/family/${familyId}">${escapeHtml(name || `#${familyId}`)}</a>`;
}

/** GET an admin API path and parse the JSON, or throw with the server's message. */
export async function getJSON<T>(
  path: string,
  params: Record<string, string | number | null | undefined> = {},
): Promise<T> {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== "") {
      query.set(key, String(value));
    }
  }
  const response = await fetch(`${root()}/admin/api/${path}${query.toString() ? `?${query.toString()}` : ""}`, {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  });
  if (!response.ok) {
    let message = "";
    try {
      message = ((await response.json()) as { message?: string }).message ?? "";
    } catch {
      message = "";
    }
    throw new Error(message || String(response.status));
  }
  return (await response.json()) as T;
}

export function destroyTable(table: HTMLTableElement): void {
  if ($.fn.dataTable.isDataTable(table)) {
    $(table).DataTable().destroy();
  }
  table.innerHTML = "";
}

export function serverTable<T>(
  table: HTMLTableElement,
  columns: ServerColumn<T>[],
  load: PageLoader<T>,
  options: { searching?: boolean; onError?: (message: string) => void } = {},
): DataTablesApi {
  destroyTable(table);
  $.fn.dataTable.ext.errMode = "none";

  const defaults = (window.CRM?.plugin?.dataTable ?? {}) as Record<string, unknown>;
  const preferredLength = Number(defaults.pageLength);
  const searching = options.searching ?? true;

  return $(table).DataTable({
    ...defaults,
    serverSide: true,
    processing: true,
    ordering: false,
    searching,
    searchDelay: 400,
    autoWidth: false,
    lengthMenu: [10, 25, 50, 100],
    pageLength: preferredLength > 0 && preferredLength <= MAX_PAGE ? preferredLength : 25,
    // Export buttons would print only the page on screen.
    buttons: [],
    layout: {
      topStart: searching ? "search" : null,
      topEnd: null,
      bottomStart: "pageLength",
      bottomEnd: ["info", "paging"],
    },
    columns: columns.map((column) => ({
      title: column.title,
      data: null,
      className: column.className ?? "",
      responsivePriority: column.priority,
      render: (_data: unknown, _type: string, row: T) => column.render(row),
    })),
    ajax: (request: DataTablesRequest, callback: (reply: DataTablesReply) => void) => {
      const length = request.length > 0 ? Math.min(request.length, MAX_PAGE) : MAX_PAGE;
      load(request.start, length, request.search?.value ?? "")
        .then((page) => {
          callback({ draw: request.draw, recordsTotal: page.total, recordsFiltered: page.filtered, data: page.rows });
        })
        .catch((error: unknown) => {
          callback({ draw: request.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
          const message = error instanceof Error && error.message ? error.message : t("The list could not be loaded.");
          if (options.onError) {
            options.onError(message);
          } else {
            window.CRM?.notify?.(message, { type: "danger" });
          }
        });
    },
  });
}

/** Run `callback` the first time a tab is shown, and each later time if `everyTime`. */
export function onTabShown(tabLinkId: string, callback: () => void, everyTime = false): void {
  const link = document.getElementById(tabLinkId);
  if (!link) {
    return;
  }
  let shown = false;
  link.addEventListener("shown.bs.tab", () => {
    if (!shown || everyTime) {
      shown = true;
      callback();
    }
  });
  if (link.classList.contains("active")) {
    shown = true;
    callback();
  }
}
