import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanPause } from '../support/human';

test.describe('Giving', () => {
  test('finance-deposit-entry', async ({ page }, testInfo) => {
    // The transactional side: seeded deposit slip records.
    await page.goto('/finance/deposit/search');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 700);

    await captureScreen(page, testInfo, {
      name: 'finance-deposit-entry',
      purpose: 'Show deposit slip records — the transactional side of giving',
    });
  });

  test('finance-pledge-report', async ({ page }, testInfo) => {
    // The "accountable without being accounting software" shot — pledge
    // totals/progress, not a raw ledger.
    await page.goto('/finance/pledge/dashboard');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 700);

    await captureScreen(page, testInfo, {
      name: 'finance-pledge-report',
      purpose: 'Show fund/pledge totals and progress',
    });
  });
});
