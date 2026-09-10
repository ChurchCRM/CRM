import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanPause } from '../support/human';

test.describe('Dashboard', () => {
  test('dashboard-hero', async ({ page }, testInfo) => {
    // The landing view after login — the marketing "hero" shot: nav +
    // summary widgets, populated by the seeded demo data.
    await page.goto('/v2/dashboard');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'dashboard-hero',
      purpose: 'Landing dashboard after login — hero shot',
    });
  });
});
