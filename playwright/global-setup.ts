import { request as playwrightRequest } from '@playwright/test';

import { BASE_URL } from './support/env';

const MAX_WAIT_ATTEMPTS = 30;
const WAIT_INTERVAL_MS = 5000;

/**
 * Waits for the ChurchCRM instance to accept connections. This is the only
 * thing left in globalSetup — driving the setup wizard, church info, and
 * demo data import used to happen here too, but globalSetup pages are never
 * video-recorded (`use.video` only applies to a real test's `page` fixture).
 * That work now lives in playwright/setup/bootstrap.setup.ts as real,
 * recorded tests — see playwright/README.md.
 */
export default async function globalSetup(): Promise<void> {
  const api = await playwrightRequest.newContext();
  try {
    for (let attempt = 1; attempt <= MAX_WAIT_ATTEMPTS; attempt++) {
      try {
        const response = await api.get(BASE_URL, { timeout: 5000 });
        if (response.ok() || response.status() === 302) {
          return;
        }
      } catch {
        // Server not accepting connections yet — keep polling.
      }
      await new Promise((resolve) => setTimeout(resolve, WAIT_INTERVAL_MS));
    }
    throw new Error(`ChurchCRM did not become ready at ${BASE_URL} after ${MAX_WAIT_ATTEMPTS} attempts`);
  } finally {
    await api.dispose();
  }
}
