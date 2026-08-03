/**
 * Apply the FullCalendar v7 locale to a Calendar instance via webpack dynamic import.
 *
 * Locales are code-split per-language (79 chunks); the correct chunk is loaded
 * on demand based on the CRM-configured locale. Fires after locale-loader.js has
 * already resolved the FC-compatible locale code into window.CRM.fcLocaleCode.
 *
 * Sets both `locales` (array) and `locale` (code string) so getOption('locale')
 * continues to return a string — compatible with existing Cypress assertions.
 *
 * Using a relative path into node_modules bypasses the fullcalendar package
 * exports-field restriction that prevents webpack from building a dynamic-import
 * context for the package-path form `fullcalendar/locales/${code}`.
 *
 * @param {import('fullcalendar/all').Calendar} cal - The FullCalendar Calendar instance.
 * @returns {Promise<void>}
 */
export async function applyFcLocale(cal) {
  // Resolved by locale-loader.js from localeConfig (honours fullCalendarLocale
  // override for region codes like pt-br vs pt). Falls back to window.CRM.lang.
  const code = (window.CRM.fcLocaleCode || window.CRM.lang || "").toLowerCase();
  // 'en' is the built-in default (no locales/en directory in FC v7); skip loading.
  if (!code || code === "en") return;
  try {
    const mod = await import(
      /* webpackChunkName: "fc-locale" */
      /* webpackMode: "lazy" */
      `../../node_modules/fullcalendar/locales/${code}/index.js`
    );
    cal.setOption("locales", [mod.default]);
    cal.setOption("locale", mod.default.code);
  } catch (e) {
    console.warn(`FullCalendar locale '${code}' unavailable, falling back to English:`, e);
  }
}
