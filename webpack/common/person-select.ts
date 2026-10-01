/**
 * Shared AJAX person-search TomSelect — issue #9819.
 *
 * Before this module the same widget was hand-rolled three times
 * (`src/skin/js/GroupView.js`, and twice in `webpack/event-checkin.js`) with
 * three different option sets: the `valueField`/`labelField`/`searchField`
 * triple and the `load` callback were byte-for-byte identical, while
 * `dropdownParent`, `placeholder`, `render` and `maxOptions` had all drifted.
 *
 * Two settings are applied here that not every call site used to set:
 *
 * - `dropdownParent: "body"` — without it the dropdown is clipped by a
 *   `.card-body` or `.modal-content` that has constrained overflow (#9488).
 * - `maxOptions: null` — TomSelect renders at most 50 options by default and
 *   applies the cap *after* filtering, with no scrollbar and no "more results"
 *   hint, so a search route returning more than 50 rows would silently lose its
 *   tail (#9677).
 *
 * `window.CRM` is populated by PHP-rendered inline script that runs *after* the
 * webpack bundles load, so `window.CRM.root` is read inside `load()` rather
 * than captured at module scope (see `webpack/api-utils.ts`).
 *
 * The TomSelect constructor is taken from `window.TomSelect` (set by
 * `webpack/skin-core.js`) rather than imported, so every bundle keeps sharing
 * one TomSelect class instead of embedding its own copy.
 *
 * Types (`PersonSelectOptions`, `TomSelectInstance`, …) are declared globally in
 * `webpack/types/window.d.ts`, matching the `CRMEmailComposerOptions` pattern.
 */

/** Default search route. Overridable via `opts.endpoint`. */
export const PERSON_SEARCH_ENDPOINT = "/api/persons/search/";

/** Both class conventions in use today, so no `.php` template has to change. */
export const PERSON_SELECT_SELECTOR = ".personSearch, .person-search";

/** `GET /api/persons/search/{query}` ignores anything shorter. */
const MIN_QUERY_LENGTH = 2;

function identity(item: PersonSearchResult): PersonSearchResult {
  return item;
}

/**
 * Turn one `<select>` into an AJAX person picker.
 *
 * @param el   The `<select>` to wrap. Must not already be a TomSelect.
 * @param opts Optional overrides; see `PersonSelectOptions`.
 * @returns The TomSelect instance (also reachable as `el.tomselect`).
 */
export function initPersonSelect(el: HTMLSelectElement, opts: PersonSelectOptions = {}): TomSelectInstance {
  const endpoint = opts.endpoint ?? PERSON_SEARCH_ENDPOINT;
  const mapResult = opts.mapResult ?? identity;
  // `data-placeholder` is the template-side convention (src/event/views/checkin.php).
  const placeholder = opts.placeholder ?? el.dataset.placeholder;

  const settings: Record<string, unknown> = {
    valueField: "objid",
    labelField: "text",
    searchField: "text",
    dropdownParent: "body",
    maxOptions: null,
    load: (query: string, callback: (results?: PersonSearchResult[]) => void) => {
      if (query.length < MIN_QUERY_LENGTH) return callback();
      const root = window.CRM?.root ?? "";
      fetch(`${root}${endpoint}${encodeURIComponent(query)}`)
        .then((res) => res.json())
        .then((data: PersonSearchResult[]) => {
          callback(data.map(mapResult));
        })
        .catch(() => {
          callback();
        });
    },
  };

  // Only set the keys the caller actually asked for: TomSelect derives its own
  // defaults for `placeholder` and `render` from the element when they are absent.
  if (placeholder !== undefined) {
    settings.placeholder = placeholder;
  }
  if (opts.render) {
    settings.render = opts.render;
  }
  if (opts.onChange) {
    const onChange = opts.onChange;
    settings.onChange = function (this: TomSelectInstance, value: string) {
      onChange.call(this, value, el);
    };
  }

  return new window.TomSelect(el, settings);
}

/**
 * Initialise every not-yet-initialised person picker under `root`, matching both
 * the `.personSearch` and `.person-search` class conventions.
 *
 * @param opts Passed to `initPersonSelect` for each element.
 * @param root Subtree to scan. Defaults to the whole document.
 * @returns The instances created by this call (already-initialised elements are skipped).
 */
export function initAllPersonSelects(opts: PersonSelectOptions = {}, root: ParentNode = document): TomSelectInstance[] {
  const instances: TomSelectInstance[] = [];
  for (const el of root.querySelectorAll<HTMLSelectElement>(PERSON_SELECT_SELECTOR)) {
    if (el.tomselect) continue;
    instances.push(initPersonSelect(el, opts));
  }
  return instances;
}

/**
 * Wire a person picker that lives inside a Bootstrap 5 modal.
 *
 * Registers the `shown.bs.modal` → init / `hidden.bs.modal` → `destroy()` pair
 * once, instead of re-implementing it at each call site. Initialisation is
 * deferred to `shown.bs.modal` because TomSelect measures the control, which
 * needs the modal's CSS transition to have finished; teardown on
 * `hidden.bs.modal` is what keeps body-mounted `.ts-dropdown` nodes from
 * accumulating one per open/close.
 *
 * @param modalEl  The `.modal` element.
 * @param selector Selector for the `<select>` *within* the modal.
 * @param opts     `PersonSelectOptions` plus an `onInit` hook for post-init setup
 *                 (pre-populating a value, wiring extra buttons, …).
 */
export function attachToModal(
  modalEl: HTMLElement,
  selector: string,
  opts: PersonSelectModalOptions = {},
): PersonSelectModalHandle {
  let instance: TomSelectInstance | null = null;

  const destroyInstance = () => {
    if (!instance) return;
    try {
      instance.destroy();
    } catch (_e) {
      // The modal DOM may already be gone; nothing left to tear down.
    }
    instance = null;
  };

  const onShown = () => {
    const el = modalEl.querySelector<HTMLSelectElement>(selector);
    if (!el || el.tomselect) return;
    instance = initPersonSelect(el, opts);
    opts.onInit?.(instance, el);
  };

  modalEl.addEventListener("shown.bs.modal", onShown);
  modalEl.addEventListener("hidden.bs.modal", destroyInstance);

  return {
    getInstance: () => instance,
    detach: () => {
      modalEl.removeEventListener("shown.bs.modal", onShown);
      modalEl.removeEventListener("hidden.bs.modal", destroyInstance);
      destroyInstance();
    },
  };
}
