/**
 * Ambient declarations for the DataTables jQuery plugin.
 *
 * DataTables is loaded as a jQuery plugin by the skin bundle and the project
 * carries no `@types/datatables.net`, so `$(...).DataTable()` is invisible to
 * TypeScript. Rather than casting at every call site — which would hide real
 * mistakes along with the missing type — the small surface this codebase
 * actually uses is declared once here, merged into the global `JQuery` and
 * `JQueryStatic` interfaces from `@types/jquery`.
 *
 * Keep this minimal: add a member when a bundle genuinely needs it, not
 * speculatively. The full DataTables API is large and mostly unused here.
 */

/**
 * DataTables' own lightweight DOM wrapper (`DataTable.Dom`), NOT jQuery — v3
 * dropped the jQuery dependency internally, so anything the plugin hands back as
 * a "node set" is one of these. Only the one method this codebase calls is
 * declared.
 */
interface DataTablesDom {
  appendTo(target: Element | string | DataTablesDom): DataTablesDom;
}

/**
 * One Buttons group, as far as this codebase uses it.
 *
 * `container()` is the finished `div.dt-buttons` element. A group built with
 * `new $.fn.dataTable.Buttons(api, …)` is NOT inserted anywhere by itself, which
 * is exactly what lets a table put its export buttons somewhere of its own
 * choosing instead of in the DataTables layout row.
 */
interface DataTablesButtonsGroup {
  container(): DataTablesDom;
}

/**
 * `new $.fn.dataTable.Buttons(api, config)`.
 *
 * Constructing a group explicitly is the only way to have one synchronously:
 * the extension's own `init.dt` listener builds the default group AFTER
 * `$(…).DataTable()` has returned, so a caller that wants to relocate the
 * buttons in the same statement has to create them itself. Doing so also sets
 * `settings._buttons`, so that later listener sees a group already exists and
 * does not build a second one.
 */
interface DataTablesButtonsConstructor {
  new (dt: DataTablesApi, config: { buttons?: unknown }): DataTablesButtonsGroup;
}

/** The subset of the DataTables API object this codebase calls. */
interface DataTablesApi {
  /** Tear the instance down so the table can be re-initialised after a re-render. */
  destroy(remove?: boolean): void;
  draw(paging?: boolean | string): DataTablesApi;
  clear(): DataTablesApi;
}

/** The plugin's own namespace, reached as `$.fn.dataTable`. */
interface DataTablesNamespace {
  /** Has this selector already been turned into a DataTable? */
  isDataTable(target: string | Element | JQuery): boolean;
  /** The Buttons extension's constructor; the skin bundle always loads it. */
  Buttons: DataTablesButtonsConstructor;
  ext: {
    /**
     * `"none"` suppresses DataTables' own browser alert on an ajax failure, so a
     * page can render its inline error block instead — required on every V2
     * screen (volunteer-v2-design.md §5.8).
     */
    errMode: string;
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

/**
 * `DataTable()` is the per-element entry point; `dataTable` is the plugin
 * namespace, reached through `$.fn` (which `@types/jquery` types as a `JQuery`).
 * Both therefore belong on this one interface.
 */
interface JQuery<TElement = HTMLElement> {
  DataTable(options?: Record<string, unknown>): DataTablesApi;
  dataTable: DataTablesNamespace;
}
