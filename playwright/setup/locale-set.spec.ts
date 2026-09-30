import { test } from '@playwright/test';

import { applyLocale } from '../support/locale-session';
import { LOCALE } from '../support/env';

/**
 * CRM #10048 — re-applies the admin account's ui.locale between capture
 * passes, without re-running the full setup wizard + demo-data-import.
 *
 * Why this exists as its own project instead of just re-running
 * bootstrap.setup.ts's applyLocale() call: 'setup' is a one-time,
 * first-run-only flow (setup wizard against a *fresh* install) — it
 * cannot be re-run against an already-installed instance. But an
 * 8-locale #10048 run needs the locale changed 7 more times *after* that
 * first install, against the one already-seeded instance, reusing the
 * same storageState session rather than logging in from scratch each
 * time.
 *
 * ChurchCRM resolves locale per-request from the DB-stored user
 * preference (not from the session cookie), so this only needs to fire
 * the API call once per locale — no UI interaction, no page navigation
 * required, and no re-seeding of data. See locale-session.ts for the
 * actual POST /api/user/{id}/setting/ui.locale call this wraps, and
 * scripts/capture-all-locales.sh for how this project is invoked between
 * 'screenshots' passes with --no-deps (see the 'locale-set' project in
 * playwright.config.ts — no dependency on 'setup', so this alone runs).
 */
test(`set-locale-${LOCALE}`, async ({ page }) => {
  // Reuses the 'locale-set' project's storageState (same admin.json saved
  // by bootstrap.setup.ts) — no login needed, just an authenticated
  // request context to call the settings API through.
  await page.goto('/people/dashboard');
  await applyLocale(page);
});
