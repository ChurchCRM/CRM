import { expect, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { settle } from '../support/human';

test.describe('Giving', () => {
  const financeDepositEntryCapture = defineCapture({
    name: 'finance-deposit-entry',
    locales: 'all',
    title: 'Deposit Entry',
    category: 'Finance & Giving',
    purpose: 'Show deposit slip records — the transactional side of giving',
    usedIn: ['docs'],
  });
  test('finance-deposit-entry', financeDepositEntryCapture, async ({ page }, testInfo) => {
    // The transactional side: seeded deposit slip records.
    await page.goto('/finance/deposit/search');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await settle(page, 700);

    await captureScreen(page, testInfo, financeDepositEntryCapture);
  });

  const financePledgeReportCapture = defineCapture({
    name: 'finance-pledge-report',
    locales: 'all',
    title: 'Pledges & Funds',
    category: 'Finance & Giving',
    purpose: 'Show fund/pledge totals and progress',
    usedIn: ['website'],
  });
  test('finance-pledge-report', financePledgeReportCapture, async ({ page }, testInfo) => {
    // The "accountable without being accounting software" shot — pledge
    // totals/progress, not a raw ledger.
    await page.goto('/finance/pledge/dashboard');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await settle(page, 700);

    await captureScreen(page, testInfo, financePledgeReportCapture);
  });
});
