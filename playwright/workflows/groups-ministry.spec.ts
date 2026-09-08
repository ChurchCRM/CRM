import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause } from '../support/human';

test.describe('Groups & Ministry', () => {
  test('groups-ministry-overview', async ({ page }, testInfo) => {
    // A church user browsing ministry organization: open the groups
    // dashboard, then drill into an active group to see its membership.
    // Demo data includes inactive groups too, so this walks the list
    // looking for the first active one rather than assuming row order —
    // an "Inactive" group is not a good look for a marketing screenshot.
    await page.goto('/groups/dashboard');

    const rows = page.locator('#groupsTable tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    const rowCount = await rows.count();
    let foundActiveGroup = false;

    for (let i = 0; i < rowCount; i++) {
      await humanClick(rows.nth(i).locator('a').first());
      await page.waitForURL(/\/groups\/view\/\d+/, { timeout: 15000 });
      await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });

      const isActive = await page.evaluate(() => window.CRM?.groupIsActive === true);
      if (isActive) {
        foundActiveGroup = true;
        break;
      }

      await page.goBack();
      await expect(rows.first()).toBeVisible({ timeout: 15000 });
    }

    if (!foundActiveGroup) {
      throw new Error('No active group found in the demo data to capture — every seeded group was inactive.');
    }

    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: 'groups-ministry-overview',
      purpose: 'Show ministry group organization and membership',
    });
  });
});
