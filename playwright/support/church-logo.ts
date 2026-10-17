import { type APIRequestContext, type Page, request } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

import { BASE_URL } from './env';

/**
 * A made-up logo for the demo church "Main St. Cathedral" (simple shapes and
 * text, 1050x300, the 3.5:1 banner the upload card asks for). It sits on its
 * own white card so it reads on the dark portal header and dark sidebar too.
 */
export const DEMO_LOGO_PATH = path.join(__dirname, '..', 'fixtures', 'main-st-cathedral-logo.png');

const LOGO_API = '/admin/api/system/church-logo';
const ADMIN_STATE_PATH = path.join(__dirname, '..', '.auth', 'admin.json');

export function adminRequest(): Promise<APIRequestContext> {
  return request.newContext({ baseURL: BASE_URL, storageState: ADMIN_STATE_PATH, timeout: 60000 });
}

async function expectOk(response: { ok(): boolean; status(): number; text(): Promise<string> }, what: string) {
  if (!response.ok()) {
    throw new Error(`${what} failed: ${response.status()} ${(await response.text()).slice(0, 300)}`);
  }
}

export async function uploadDemoLogo(api: APIRequestContext): Promise<void> {
  const imgBase64 = `data:image/png;base64,${fs.readFileSync(DEMO_LOGO_PATH).toString('base64')}`;
  await expectOk(await api.post(LOGO_API, { data: { imgBase64 } }), 'Upload church logo');
}

export async function removeChurchLogo(api: APIRequestContext): Promise<void> {
  await expectOk(await api.delete(LOGO_API), 'Remove church logo');
}

/** Waits until every visible <img> on the page has finished loading. */
export async function waitForImages(page: Page): Promise<void> {
  await page.waitForFunction(
    () =>
      Array.from(document.images)
        .filter((img) => img.offsetParent !== null)
        .every((img) => img.complete && img.naturalWidth > 0),
    undefined,
    { timeout: 30000 },
  );
}
