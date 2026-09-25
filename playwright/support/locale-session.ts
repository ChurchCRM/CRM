import type { Page } from '@playwright/test';

import { ADMIN_PERSON_ID, LOCALE, LOCALE_UI_VALUE } from './env';

/**
 * Sets the admin account's UI locale via the same API the Cypress
 * locale-smoke suite uses (cy.setupLocaleAdminSession ->
 * POST /api/user/{id}/setting/ui.locale — see
 * cypress/support/ui-commands.js). ChurchCRM resolves locale per-request
 * from this DB-stored preference, so calling it once here — using the
 * already-authenticated page's own session cookie, no separate API key
 * needed — is enough to flip every subsequent page load into the target
 * language for the rest of this run.
 *
 * Call this once, after the admin login step and before the
 * storageState() save in bootstrap.setup.ts's 'demo-data-import' test, so
 * every downstream screenshots/tablet/mobile project inherits the locale
 * along with the rest of the saved session.
 *
 * No-op when LOCALE is 'en' (or any code missing from LOCALE_MAP) — the
 * app's default install locale is already English, so there's nothing to
 * set and no reason to add a network call to the common case.
 */
export async function applyLocale(page: Page): Promise<void> {
  if (!LOCALE_UI_VALUE || LOCALE === 'en') {
    return;
  }

  const response = await page.request.post(`/api/user/${ADMIN_PERSON_ID}/setting/ui.locale`, {
    data: { value: LOCALE_UI_VALUE },
  });

  if (!response.ok()) {
    throw new Error(
      `Failed to set ui.locale to "${LOCALE_UI_VALUE}" (marketing locale "${LOCALE}") for user ` +
        `${ADMIN_PERSON_ID}: ${response.status()} ${await response.text()}`
    );
  }
}
