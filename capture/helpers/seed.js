/**
 * helpers/seed.js — demo data seeding for capture scenes
 *
 * ── Two Docker paths ──────────────────────────────────────────────────────────
 *
 * PATH A — Demo-seeded stack (quick iteration):
 *   npm run docker:dev:start
 *   The dev compose mounts cypress/data/seed.sql which pre-populates the DB
 *   with test users, families, groups, and finance data.  Fast to start; data
 *   is present immediately.  Contains test-run artifacts.
 *
 * PATH B — Fresh install + demo import (preferred for capture):
 *   1. npm run docker:ci:new-system:start
 *      Starts ChurchCRM without any Config.php (triggers setup wizard).
 *   2. Complete the setup wizard in the browser (http://localhost) — set
 *      church name, admin password, and database credentials.
 *   3. Call importDemoData() below (or run the scene with CAPTURE_FORCE_DEMO=1)
 *      to populate realistic people, families, groups, events, and finance data
 *      from src/admin/demo/.
 *   4. Run scenes — data is clean, photogenic, and has no test artifacts.
 *
 *   DB reset (between captures):
 *     npm run docker:test:reset:db
 *     This replays cypress/data/seed.sql — use for PATH A only.
 *     For PATH B, tear down and recreate the stack:
 *       npm run docker:ci:new-system:down && npm run docker:ci:new-system:start
 *
 * ── importDemoData() ──────────────────────────────────────────────────────────
 *
 * Calls POST /api/demo/load (src/admin/routes/api/demo.php) with the
 * recommended flags.  Guards:
 *   - The endpoint requires the database to have exactly 1 person (the admin
 *     created by the setup wizard) unless force:true is passed.
 *   - Admin authentication is required (cookie session from a prior login, or
 *     pass credentials to login() first).
 *
 * The function accepts a Playwright page that is already authenticated (call
 * login() first).  It makes the API request via page.request so it shares the
 * authenticated browser session.
 *
 * @example
 *   const { login } = require('./auth');
 *   const { importDemoData } = require('./seed');
 *
 *   // After starting a fresh-install Docker stack and running the wizard:
 *   await login(page);
 *   await importDemoData(page, { force: false }); // fails if DB has > 1 person
 *   // – or –
 *   await importDemoData(page, { force: true });   // skips the fresh-install guard
 */

'use strict';

const { BASE_URL } = require('../capture.config');

/**
 * Trigger ChurchCRM's built-in demo importer via the admin API.
 *
 * Imports:
 *   - People & families (src/admin/demo/people.json)
 *   - Groups (src/admin/demo/groups.json)
 *   - Events with attendance (src/admin/demo/events.csv + attendees.csv)
 *   - Financial data: funds, fundraisers, pledges, deposits (finance.json)
 *   - Sunday school groups
 *
 * @param {import('playwright').Page} page
 *   An authenticated Playwright page (call login() before this).
 * @param {{ includeFinancial?: boolean, includeEvents?: boolean,
 *           includeSundaySchool?: boolean, force?: boolean }} [options]
 * @returns {Promise<object>} The API response body.
 * @throws {Error} if the API returns a non-200 status.
 */
async function importDemoData(page, options = {}) {
  const {
    includeFinancial = true,
    includeEvents = true,
    includeSundaySchool = true,
    force = false,
  } = options;

  const response = await page.request.post(`${BASE_URL}/api/demo/load`, {
    data: { includeFinancial, includeEvents, includeSundaySchool, force },
    headers: { 'Content-Type': 'application/json' },
  });

  const body = await response.json().catch(() => ({}));

  if (!response.ok()) {
    throw new Error(
      `Demo data import failed (HTTP ${response.status()}): ${body.message || JSON.stringify(body)}`
    );
  }

  if (!body.success) {
    throw new Error(
      `Demo data import returned success:false — ${body.message || JSON.stringify(body.errors)}`
    );
  }

  console.log(
    `[seed] Demo data imported in ${body.elapsedSeconds}s:`,
    JSON.stringify(body.imported)
  );

  return body;
}

module.exports = { importDemoData };
