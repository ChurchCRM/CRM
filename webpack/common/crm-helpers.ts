/**
 * The three `window.CRM` helpers the shared volunteer components need, for pages
 * that do not load the admin shell (#9868).
 *
 * `src/skin/js/CRMJSOM.js` defines `escapeHtml`, `escapeAttribute` and
 * `buildActionMenu`, and `Include/Header.php` loads it — but only on admin-shell
 * pages. The Member Portal deliberately loads no part of the admin shell (Member
 * Portal design P10), so a portal page that reuses a component written for the
 * admin side finds all three missing: escaping degrades to the raw string, and
 * `buildActionMenu` returning nothing means a table row with no actions at all.
 *
 * So the portal bundle installs them, from here, **only when they are absent**.
 *
 * Why a copy rather than a refactor of `CRMJSOM.js`: that file is loaded as a
 * plain `<script src>`, not through webpack, so it cannot import this module —
 * making it the single source would mean rewriting how the admin shell loads its
 * core script, on a branch whose whole guarantee is that the admin pages behave
 * exactly as they did. The implementations below are byte-for-byte equivalent to
 * that file's, and the security notes that go with them are reproduced so a
 * reader here is not one indirection away from knowing why the quote handling
 * matters. If one is ever changed, change both — they answer the same question
 * and are asserted by the same specs.
 */

/**
 * Escape text for safe insertion as a TEXT NODE.
 *
 * GHSA-8r36-fvxj-26qv: used to sanitize values before rendering. Encodes `&`,
 * `<` and `>` — NOT the quote characters, so it is unsafe for attribute values.
 */
function escapeHtml(text: string): string {
  if (text === null || text === undefined) {
    return "";
  }
  const div = document.createElement("div");
  div.textContent = String(text);

  return div.innerHTML;
}

/**
 * Escape text for safe insertion into an ATTRIBUTE VALUE.
 *
 * GHSA-369j-c5w2-48m4: extends `escapeHtml` to encode both quote characters, so
 * a value cannot break out of the quoted attribute it is written into.
 */
function escapeAttribute(text: string): string {
  if (text === null || text === undefined) {
    return "";
  }

  return escapeHtml(text).replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

/**
 * Build the canonical Tabler row-action dropdown (U1/#9820).
 *
 * The single place the scaffold is written: the wrapper, the
 * `btn-ghost-secondary` trigger (including `data-bs-display="static"`, which is
 * load-bearing — without it the menu is clipped inside a scrolling table
 * container, see #9373), the `fa-ellipsis-vertical` icon and the
 * `dropdown-menu dropdown-menu-end` container.
 *
 * It is also the single place menu content is escaped: everything landing in an
 * attribute (`href`, classes, every `data-*` value) goes through
 * `escapeAttribute()` and every label through `escapeHtml()`. Callers pass raw
 * strings and must not pre-escape. Falsy items are skipped, so a caller can write
 * `condition && item` inline.
 */
function buildActionMenu(
  items: Array<CRMActionMenuItem | null | false | undefined>,
  opts?: CRMActionMenuOptions,
): string {
  const options = opts ?? {};

  // ` data-foo="a" data-bar="b"` — the one place data-* values are escaped.
  // GHSA-hm7v-jrhm-fmfx: escapeAttribute (encodes quotes) for data-* context.
  const dataAttributes = (data?: Record<string, string | number | null | undefined>): string =>
    data
      ? Object.keys(data)
          .map((key) => ` data-${key}="${escapeAttribute(String(data[key] ?? ""))}"`)
          .join("")
      : "";

  const classAttribute = (item: CRMActionMenuItem): string => {
    const extra = `${item.danger ? "text-danger " : ""}${item.className || ""}`.trim();

    return `class="dropdown-item${extra ? ` ${escapeAttribute(extra)}` : ""}"`;
  };

  const itemBody = (item: CRMActionMenuItem): string => {
    const icon = item.icon ? `<i class="${escapeAttribute(item.icon)} me-2"></i>` : "";
    const label = escapeHtml(item.label || "");

    return icon + (item.labelClass ? `<span class="${escapeAttribute(item.labelClass)}">${label}</span>` : label);
  };

  const renderItem = (item: CRMActionMenuItem | null | false | undefined): string => {
    if (!item) {
      return "";
    }
    if (item.type === "divider") {
      return '<div class="dropdown-divider"></div>';
    }
    if (item.type === "link") {
      return `<a ${classAttribute(item)} href="${escapeAttribute(item.href || "")}"${dataAttributes(item.data)}>${itemBody(item)}</a>`;
    }
    // classBeforeType keeps the cart button's historical attribute order.
    const leading = item.classBeforeType
      ? `${classAttribute(item)} type="button"`
      : `type="button" ${classAttribute(item)}`;

    return `<button ${leading}${dataAttributes(item.data)}>${itemBody(item)}</button>`;
  };

  return (
    `<div class="${escapeAttribute(options.wrapperClass || "dropdown")}">` +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="fa-solid fa-ellipsis-vertical"></i>' +
    "</button>" +
    `<div class="${escapeAttribute(options.menuClass || "dropdown-menu dropdown-menu-end")}">` +
    items.map(renderItem).join("") +
    "</div></div>"
  );
}

/**
 * Install any of the three that this page does not already have.
 *
 * Idempotent, and never overwrites: on a page that DOES load `CRMJSOM.js` every
 * call here is a no-op, which is what keeps the admin shell byte-identical.
 */
export function ensureCrmHelpers(): void {
  window.CRM = window.CRM || {};
  const crm = window.CRM;

  if (typeof crm.escapeHtml !== "function") {
    crm.escapeHtml = escapeHtml;
  }
  if (typeof crm.escapeAttribute !== "function") {
    crm.escapeAttribute = escapeAttribute;
  }
  if (typeof crm.buildActionMenu !== "function") {
    crm.buildActionMenu = buildActionMenu;
  }
}
