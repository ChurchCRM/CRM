import { expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { settle } from '../support/human';
import { BASE_URL } from '../support/env';
import { STORAGE_STATE_PATH, setVolunteerVersion, waitForFloatingButtonsToTuck } from '../support/volunteer';

const V1_API = '/admin/api/volunteer-opportunities';

const OPPORTUNITIES: Record<string, string[]> = {
  Greeter: ['emily.evans@demo.churchcrm.io', 'sarah.carter@demo.churchcrm.io', 'paul.mitchell@demo.churchcrm.io'],
  Usher: ['kevin.thomas@demo.churchcrm.io', 'paul.mitchell@demo.churchcrm.io'],
  'Coffee Hour': ['linda.brown@demo.churchcrm.io', 'nancy.robinson@demo.churchcrm.io'],
  Nursery: ['laura.lee@demo.churchcrm.io'],
};

const created: Array<{ id: number; personIds: number[] }> = [];

async function json<T>(page: Page, method: string, url: string, data?: unknown): Promise<T> {
  const response = await page.request.fetch(url, { method, data });
  if (!response.ok()) {
    throw new Error(`${method} ${url} failed: ${response.status()} ${await response.text()}`);
  }
  return (await response.json()) as T;
}

async function personId(page: Page, email: string): Promise<number> {
  const matches = await json<Array<{ objid: number }>>(page, 'GET', `/api/persons/search/${encodeURIComponent(email)}`);
  if (matches.length !== 1) {
    throw new Error(`Expected one demo person for ${email}, found ${matches.length}`);
  }
  return matches[0].objid;
}

async function createOpportunities(page: Page): Promise<void> {
  await setVolunteerVersion(page, 'v1');
  for (const [name, emails] of Object.entries(OPPORTUNITIES)) {
    const { volunteerOpportunity } = await json<{ volunteerOpportunity: { id: number } }>(page, 'POST', V1_API, {
      name,
      description: `${name} volunteers`,
    });
    const personIds = await Promise.all(emails.map((email) => personId(page, email)));
    for (const id of personIds) {
      await page.request.post(`/people/view/${id}`, { form: { 'VolunteerOpportunityIDs[0]': String(volunteerOpportunity.id) } });
    }
    created.push({ id: volunteerOpportunity.id, personIds });
  }
}

test.describe('Volunteer Opportunities retirement', () => {
  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext({ baseURL: BASE_URL, storageState: STORAGE_STATE_PATH });
    try {
      const page = await context.newPage();
      for (const { id, personIds } of created) {
        for (const person of personIds) {
          await page.request.get(`/people/view/${person}?RemoveVO=${id}`);
        }
        await json(page, 'DELETE', `${V1_API}/${id}`);
      }
      await setVolunteerVersion(page, 'v2');
    } finally {
      await context.close();
    }
  });

  test('volunteer-v1-conversion', async ({ page }, testInfo) => {
    await createOpportunities(page);
    await page.goto('/admin/volunteer-conversion');
    await expect(page.locator('#volunteerConversionTable tbody tr')).toHaveCount(Object.keys(OPPORTUNITIES).length, {
      timeout: 30000,
    });
    await expect(page.locator('#volunteerConversionRun')).toBeEnabled();
    await waitForFloatingButtonsToTuck(page);
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'volunteer-v1-conversion',
      title: 'Convert Volunteer Opportunities',
      category: 'Admin & Settings',
      purpose: 'Show the preview that converts legacy Volunteer Opportunities into positions on a ministry before anything changes.',
    });
  });
});
