/**
 * The small UI primitives the Volunteer v2 screens share (#9868).
 *
 * Every function here was lifted VERBATIM out of `webpack/ministries/ministry.ts`
 * when the Member Portal's My Teams page (MP7) needed the same tables, dialogs
 * and state machine in the portal layout. Nothing about their behaviour changed
 * in the move — the admin ministry page's own Cypress specs are the proof — and
 * nothing here knows about a ministry, a team or the portal.
 *
 * Two deliberate additions the extraction forced, both of which are no-ops on the
 * admin page:
 *
 *  - `initDataTable()` / `destroyDataTable()` now check that DataTables is
 *    actually loaded before touching it. The admin shell loads it from
 *    `Include/Footer.php`; the Member Portal does not load the admin shell at
 *    all, so on a portal page the tables are plain tables and the helpers do
 *    nothing rather than throwing on `$.fn.dataTable`.
 *  - `renderState()` takes the pane name as a plain string, because the portal's
 *    pane set is a subset of the ministry page's and an enum shared between them
 *    would have to be the union of both.
 */

/** Every state a pane can be in (design §5.8). */
export type PaneState = "loading" | "error" | "empty" | "loaded";

export function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

export function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

/**
 * The §5.8 state machine for one pane, in one place so no pane can forget a
 * state. `content` is only shown in the `loaded` state, and `empty` replaces it
 * when there is genuinely nothing rather than leaving an empty table.
 */
export function renderState(pane: string, state: PaneState, message = ""): void {
  show(byId(`${pane}-loading`), state === "loading");
  show(byId(`${pane}-error`), state === "error");
  show(byId(`${pane}-empty`), state === "empty");

  if (pane === "overview") {
    show(byId("overview-content"), state === "loaded");
  } else {
    show(byId(`${pane}-table-wrapper`), state === "loaded");
  }

  if (state === "error") {
    const text = byId(`${pane}-error`)?.querySelector(".volunteer-error-text");
    if (text) {
      text.textContent = message;
    }
  }
}

/**
 * Show an inline error inside an open modal, where the failed action happened.
 *
 * The toast is the caller's business: `ministry.ts` pairs this with
 * `notifyError()` and always has, so the notifier is passed in rather than
 * imported, which keeps this module free of any dependency on `./api`.
 */
export function showModalError(prefix: string, message: string, notify?: (message: string) => void): void {
  const box = byId(`${prefix}-form-error`);
  const text = box?.querySelector(".volunteer-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
  notify?.(message);
}

/**
 * Write a dialog's heading from the browser.
 *
 * "Add Volunteer to {Team}" cannot be a `gettext()` string in the view: only the
 * browser knows which team the grid is showing, and an `i18next.t()` call inside
 * a .php file or a .twig template is scanned by neither extractor and would never
 * be translated (§5.10, F31). So the view ships the short static title and this
 * replaces it on open.
 */
export function setModalTitle(id: string, text: string): void {
  const title = byId(id);
  if (title) {
    title.textContent = text;
  }
}

export function modal(id: string): { show(): void; hide(): void } | null {
  const el = byId(id);
  if (!el || !window.bootstrap?.Modal) {
    return null;
  }

  return window.bootstrap.Modal.getOrCreateInstance(el);
}

/** Which modals have finished their show transition, by element id. */
const shownModals = new Set<string>();
/** Modals asked to close while still fading in, to be closed the moment they are open. */
const pendingModalHides = new Set<string>();

/**
 * Register the fade guard for one modal.
 *
 * Bootstrap 5's `Modal.hide()` returns early while `_isTransitioning` is true: the
 * request is accepted and thrown away, silently, and the dialog stays open. A local
 * API answering in a few milliseconds lands squarely inside the 150 ms fade, so
 * "hide on success" cannot simply call `hide()` — which is exactly what a dialog with
 * no round trip of its own to wait for does.
 *
 * `occurrence.ts` carries a hand-rolled pair of booleans for the same trap; this is
 * the same idea, keyed by element id so one call per modal is all it costs.
 */
export function wireModalFadeGuard(id: string): void {
  const el = byId(id);
  el?.addEventListener("shown.bs.modal", () => {
    shownModals.add(id);
    if (pendingModalHides.delete(id)) {
      modal(id)?.hide();
    }
  });
  el?.addEventListener("hidden.bs.modal", () => {
    shownModals.delete(id);
    pendingModalHides.delete(id);
  });
}

/** Close a modal, queueing the request when it is still fading in. */
export function hideModal(id: string): void {
  if (!shownModals.has(id)) {
    pendingModalHides.add(id);

    return;
  }

  modal(id)?.hide();
}

export function statusBadge(active: boolean): string {
  return active
    ? `<span class="badge bg-green-lt text-green">${i18next.t("Active")}</span>`
    : `<span class="badge bg-secondary-lt">${i18next.t("Inactive")}</span>`;
}

/**
 * Row actions through the shared builder (U1/#9820) — it owns the scaffold and
 * every bit of escaping, so labels and `data-*` values are passed raw here.
 */
export function actionMenu(items: Array<CRMActionMenuItem | false>): string {
  const build = window.CRM?.buildActionMenu;
  if (!build) {
    return "";
  }

  return build(items);
}

export function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

export function escapeAttribute(value: string): string {
  return window.CRM?.escapeAttribute?.(value) ?? "";
}

/** Is DataTables actually on this page? The Member Portal does not load it. */
function hasDataTables(): boolean {
  return typeof $ !== "undefined" && $.fn?.dataTable !== undefined;
}

/**
 * Tear down an existing DataTable **before** the `<tbody>` is rewritten.
 *
 * Order is load-bearing and easy to get backwards: `destroy()` puts the rows
 * DataTables cached at init time back into the DOM, so destroying *after*
 * writing new HTML silently restores the old rows and the update appears to have
 * been ignored.
 */
export function destroyDataTable(tableId: string): void {
  if (!hasDataTables()) {
    return;
  }
  if ($.fn.dataTable.isDataTable(`#${tableId}`)) {
    $(`#${tableId}`).DataTable().destroy();
  }
}

/**
 * DataTables through the canonical `window.CRM.plugin.dataTable` merge idiom.
 *
 * `overrides` is merged AFTER the shared defaults, so a single table can turn a
 * feature off without touching `window.CRM.plugin.dataTable` — which every list
 * page in the install reads, and which the user's "Rows per page" preference
 * lives in.
 *
 * `buttonsInto` puts the Export CSV / Print group into a container of the
 * caller's choosing instead of the DataTables layout row. The group is built
 * HERE, explicitly, from the very same shared `buttons` config — so the two
 * buttons keep their `:not(.no-export)` column rule verbatim — because the
 * extension's own group is created on `init.dt`, which fires only after
 * `DataTable()` has returned; asking for it on the next line gets an empty set.
 * Building it ourselves also sets `settings._buttons`, so that later listener
 * finds a group already there and does not add a second one.
 */
export function initDataTable(tableId: string, overrides: Record<string, unknown> = {}, buttonsInto?: string): void {
  if (!hasDataTables()) {
    return;
  }

  const table = $(`#${tableId}`);
  if (table.length === 0) {
    return;
  }

  // §5.8: a failed ajax must render the inline block, never a browser alert.
  $.fn.dataTable.ext.errMode = "none";

  const defaults = window.CRM?.plugin?.dataTable ?? {};
  const api = table.DataTable({ ...defaults, ...overrides });

  if (buttonsInto) {
    const container = byId(buttonsInto);
    if (container) {
      // Emptied first: `destroyDataTable()` leaves the old group orphaned in here,
      // and every re-run of the Occurrences query re-inits the table.
      container.textContent = "";
      new $.fn.dataTable.Buttons(api, { buttons: defaults.buttons }).container().appendTo(container);
    }
  }
}

/**
 * Destructive actions go behind a bootbox confirm (U3). The 409 the API raises
 * when the record is still referenced is surfaced verbatim, because its message
 * names the counts and tells the coordinator to deactivate instead.
 */
export function confirmDelete(title: string, message: string, onConfirm: () => void): void {
  window.bootbox?.confirm({
    title,
    message,
    buttons: {
      confirm: { label: i18next.t("Yes"), className: "btn-danger" },
      cancel: { label: i18next.t("No"), className: "btn-default" },
    },
    callback: (result: boolean) => {
      if (result) {
        onConfirm();
      }
    },
  });
}

/**
 * Keep a row action menu usable inside a horizontally scrolling table.
 *
 * `.volunteer-scroll-x` sets `overflow-x: auto` so the qualification grid can be
 * wider than the page. Per the CSS Overflow spec that coerces `overflow-y` from
 * `visible` to `auto`, which clips any absolutely-positioned descendant — and a
 * Bootstrap dropdown is exactly that. `data-bs-display="static"` does not help:
 * it only turns Popper off, and the menu is still positioned inside the clipping
 * box.
 *
 * The fix is to take the OPEN menu out of that box altogether. A `position: fixed`
 * element is not clipped by an ancestor's overflow at all, so on `shown.bs.dropdown`
 * the menu is switched to fixed and anchored to the trigger's viewport rectangle,
 * and on `hide.bs.dropdown` it is handed back to Bootstrap untouched. Nothing is
 * moved in the DOM, so Bootstrap's own focus handling, the delegated row-action
 * click handlers and `dropdown-menu-end` alignment all keep working.
 *
 * Coordinates are re-derived on scroll and resize while the menu is open, because
 * a fixed element does not follow the row it belongs to.
 */
export function wireUnclippedRowMenus(wrapperId: string): void {
  const wrapper = byId(wrapperId);
  if (!wrapper) {
    return;
  }

  let open: { menu: HTMLElement; toggle: HTMLElement } | null = null;

  const place = (): void => {
    if (!open) {
      return;
    }
    const rect = open.toggle.getBoundingClientRect();
    // `dropdown-menu-end` aligns the menu's right edge with the trigger's.
    open.menu.style.top = `${rect.bottom}px`;
    open.menu.style.left = `${Math.max(0, rect.right - open.menu.offsetWidth)}px`;
  };

  const release = (): void => {
    if (!open) {
      return;
    }
    open.menu.classList.remove("volunteer-menu-fixed");
    open.menu.style.top = "";
    open.menu.style.left = "";
    open = null;
    window.removeEventListener("resize", place);
    window.removeEventListener("scroll", place, true);
  };

  // `shown`, not `show`: a `.dropdown-menu` without `.show` is `display: none`,
  // so its width — which right-alignment needs — is zero until Bootstrap has
  // opened it. The handler runs before the browser paints, so there is no flash.
  wrapper.addEventListener("shown.bs.dropdown", (event) => {
    // Bootstrap fires this on the TOGGLE, not on the `.dropdown` wrapper — both
    // are accepted here so the handler survives either reading.
    const node = event.target as HTMLElement | null;
    const toggle = node?.matches("[data-bs-toggle='dropdown']")
      ? node
      : (node?.querySelector<HTMLElement>("[data-bs-toggle='dropdown']") ?? null);
    const menu = toggle?.parentElement?.querySelector<HTMLElement>(".dropdown-menu") ?? null;
    if (!menu || !toggle) {
      return;
    }

    release();
    open = { menu, toggle };
    menu.classList.add("volunteer-menu-fixed");
    place();
    window.addEventListener("resize", place);
    // Capture: the wrapper's own scroll does not bubble.
    window.addEventListener("scroll", place, true);
  });

  wrapper.addEventListener("hide.bs.dropdown", release);
}

/** `YYYY-MM-DD`, `offsetDays` from today, in the browser's own calendar. */
export function isoDate(offsetDays: number): string {
  const date = new Date();
  date.setHours(12, 0, 0, 0);
  date.setDate(date.getDate() + offsetDays);

  return formatIsoDate(date);
}

export function formatIsoDate(date: Date): string {
  const pad = (n: number): string => String(n).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
