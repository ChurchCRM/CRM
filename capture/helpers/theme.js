/**
 * helpers/theme.js — dark-theme forcing for capture scenes
 *
 * ChurchCRM's real dark-theme mechanism (src/Include/Header.php):
 *   - Server renders data-bs-theme="dark" on <html> when the user's
 *     ui.style setting is 'dark'.
 *   - A synchronous inline <head> script exposes window.CRM.theme.setMode()
 *     which applies / removes data-bs-theme="dark" on <html> client-side.
 *
 * Strategy for capture:
 *  1. page.addInitScript() stamps data-bs-theme="dark" on documentElement
 *     as early as possible (before any CSS paints), preventing flash-of-
 *     wrong-theme (FOWT) for the very first navigation.
 *  2. After each navigation (goto), call forceDarkTheme(page) which invokes
 *     window.CRM.theme.setMode('dark') — the real toggle — so the theme is
 *     enforced at the app layer and not just via an attribute.
 *  3. We verify the attribute is present before recording key frames.
 *
 * Do NOT invent a new mechanism (localStorage key, cookie, etc.).
 * This code exclusively uses the API defined in Header.php.
 */

'use strict';

/**
 * Install an init script on this page that stamps data-bs-theme="dark" on
 * <html> as early as possible — before any stylesheets are evaluated.
 *
 * Call this ONCE immediately after page creation (before the first goto).
 * It survives across same-context navigations.
 *
 * @param {import('playwright').Page} page
 */
async function installDarkThemeInitScript(page) {
  await page.addInitScript(() => {
    // Run synchronously before any script / CSS evaluation.
    document.documentElement.setAttribute('data-bs-theme', 'dark');
  });
}

/**
 * Force dark theme after a page navigation.
 *
 * Calls the real ChurchCRM toggle (window.CRM.theme.setMode('dark')) so the
 * app layer is aligned with the attribute.  Falls back to setting the
 * attribute directly if CRM.theme is not yet initialised (e.g. very early in
 * page load).  Then asserts the attribute is present.
 *
 * @param {import('playwright').Page} page
 */
async function forceDarkTheme(page) {
  // Wait for the page to be interactive enough for CRM globals to exist.
  await page.waitForLoadState('domcontentloaded');

  await page.evaluate(() => {
    if (window.CRM && window.CRM.theme && typeof window.CRM.theme.setMode === 'function') {
      // Use the real app toggle defined in src/Include/Header.php.
      window.CRM.theme.setMode('dark');
    } else {
      // Fallback: stamp the attribute that the real toggle would set.
      document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
  });

  // Verify dark theme is active before the scene records meaningful frames.
  await page.waitForFunction(
    () => document.documentElement.getAttribute('data-bs-theme') === 'dark',
    { timeout: 5_000 }
  );
}

module.exports = { installDarkThemeInitScript, forceDarkTheme };
