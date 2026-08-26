/**
 * helpers/auth.js — authentication helper for capture scenes
 *
 * Ported from cypress/support/ui-commands.js (setupLoginSession / setupAdminSession).
 * Uses the same form-based login flow ChurchCRM ships: POST to /login, fill
 * input[name=User] and input[name=Password], then wait until the URL is no
 * longer at the session/begin (login) page.
 *
 * Credentials are read from capture.config.js (which reads env vars), so no
 * secrets ever appear in scene files.
 */

'use strict';

const { BASE_URL, DEFAULT_CREDENTIALS } = require('../capture.config');

/**
 * Log in to ChurchCRM using the standard login form.
 *
 * Call this once after creating the page (before recording meaningful frames).
 * The page must be inside a context with the correct baseURL already applied,
 * or pass absolute URLs.
 *
 * @param {import('playwright').Page} page
 * @param {{ username?: string, password?: string }} [credentials]
 *   Defaults to DEFAULT_CREDENTIALS from capture.config.js (admin/changeme).
 * @returns {Promise<void>}
 */
async function login(page, credentials = {}) {
  const username = credentials.username || DEFAULT_CREDENTIALS.username;
  const password = credentials.password || DEFAULT_CREDENTIALS.password;

  // Navigate to the login page.  ChurchCRM's login form lives at /login and
  // also at /session/begin — /login is the canonical entry point.
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });

  // Fill in credentials exactly as Cypress does in setupLoginSession.
  await page.fill('input[name=User]', username);
  await page.fill('input[name=Password]', password);
  await page.keyboard.press('Enter');

  // Wait until the URL leaves the session/begin (login) page.
  await page.waitForFunction(
    () => !window.location.href.includes('/session/begin'),
    { timeout: 30_000 }
  );

  // Give the page a moment to settle after redirect.
  await page.waitForLoadState('networkidle');
}

module.exports = { login };
