import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

import { humanClick, humanPause } from './human';

/**
 * Navigates to the first active group in the demo data's groups dashboard.
 * Demo data includes inactive groups too, so this walks the list looking
 * for the first active one rather than assuming row order — an "Inactive"
 * group is not a good look for marketing material. Ends with the page on
 * that group's view (`/groups/view/{id}`).
 */
export async function gotoFirstActiveGroup(page: Page): Promise<void> {
  await page.goto('/groups/dashboard');

  const rows = page.locator('#groupsTable tbody tr');
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  await humanPause(page, 500);

  const rowCount = await rows.count();

  for (let i = 0; i < rowCount; i++) {
    await humanClick(rows.nth(i).locator('a').first());
    await page.waitForURL(/\/groups\/view\/\d+/, { timeout: 15000 });
    await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });

    const isActive = await page.evaluate(() => window.CRM?.groupIsActive === true);
    if (isActive) {
      return;
    }

    await page.goBack();
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
  }

  throw new Error('No active group found in the demo data — every seeded group was inactive.');
}
