import { expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { addPaymentUrl, createDemoDeposit, deleteDemoDeposit } from '../support/deposit';
import { humanClick, humanPause, humanSelect, humanType, settle } from '../support/human';

/**
 * Three envelopes from a Sunday offering entered in a row: each payment is saved
 * with Ctrl+Enter, which opens a fresh form on the same deposit with the cursor
 * back in Family. Ends on the deposit slip with all three listed.
 */

interface Envelope {
  family: string;
  method: 'CHECK' | 'CASH';
  checkNo?: string;
  fund: string;
  amount: string;
}

const ENVELOPES: Envelope[] = [
  { family: 'Anderson', method: 'CHECK', checkNo: '1042', fund: 'Building Fund', amount: '100' },
  { family: 'Parker', method: 'CHECK', checkNo: '2317', fund: 'Missions & Outreach', amount: '50' },
  { family: 'Young', method: 'CASH', fund: 'Youth Ministry', amount: '25' },
];

let depositId = 0;

test.beforeAll(async ({ browser }) => {
  depositId = await createDemoDeposit(browser);
});

test.afterAll(async ({ browser }) => {
  if (depositId > 0) {
    await deleteDemoDeposit(browser, depositId);
  }
});

async function enterEnvelope(page: Page, envelope: Envelope): Promise<void> {
  const family = page.locator('#FamilyName-ts-control');
  await expect(family).toBeFocused({ timeout: 15000 });
  await humanPause(page, 1500);
  await family.pressSequentially(envelope.family, { delay: 110 });
  const option = page.locator('.ts-dropdown .option', { hasText: `${envelope.family}:` });
  await expect(option).toBeVisible({ timeout: 15000 });
  await humanPause(page, 500);
  await page.keyboard.press('Enter');
  await expect(page.locator('#FamilyID')).not.toHaveValue('0');

  await humanSelect(page.locator('#Method'), envelope.method);
  if (envelope.checkNo) {
    await humanType(page.locator('#CheckNo'), envelope.checkNo);
  }
  await humanSelect(page.locator('#fundRows .fund-select').first(), { label: envelope.fund });
  await humanType(page.locator('#fundRows .fund-amount').first(), envelope.amount);
  await humanPause(page, 1200);
}

test('finance-payment-ctrl-enter', async ({ page }, testInfo) => {
  await page.goto(`/DepositSlipEditor.php?DepositSlipID=${depositId}`);
  await expect(page.locator('#paymentsTable')).toBeAttached({ timeout: 15000 });
  await humanPause(page, 1500);

  await humanClick(page.locator(`a.btn[href*="depositId=${depositId}"]`));
  await page.waitForURL(/\/finance\/pledge\/new/, { timeout: 15000 });
  await expect(page.locator('#saveShortcutHint')).toBeVisible();

  for (const envelope of ENVELOPES) {
    await enterEnvelope(page, envelope);
    const saved = page.waitForResponse((r) => r.url().endsWith('/api/payments/pledges') && r.request().method() === 'POST');
    const freshForm = page.waitForEvent('load', { timeout: 30000 });
    await page.keyboard.press('Control+Enter');
    expect((await saved).ok()).toBe(true);
    await freshForm;
  }

  await humanPause(page, 1200);
  await humanClick(page.locator('a.btn-secondary', { hasText: 'Cancel' }));
  await page.waitForURL(/DepositSlipEditor\.php/, { timeout: 15000 });
  await expect(page.locator('#payment-count')).toHaveText(String(ENVELOPES.length), { timeout: 15000 });
  await settle(page, 3000);

  await captureScreen(page, testInfo, {
    name: 'finance-payment-ctrl-enter',
    title: 'Fast Payment Entry with Ctrl+Enter',
    category: 'Recordings',
    purpose: 'Show three offering envelopes entered in a row, each saved with Ctrl+Enter into a fresh form.',
  });
});
