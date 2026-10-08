import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { addPaymentUrl, createDemoDeposit, deleteDemoDeposit } from '../support/deposit';
import { humanSelect, humanType, settle } from '../support/human';

let depositId = 0;

test.beforeAll(async ({ browser }) => {
  depositId = await createDemoDeposit(browser);
});

test.afterAll(async ({ browser }) => {
  if (depositId > 0) {
    await deleteDemoDeposit(browser, depositId);
  }
});

test.describe('Giving', () => {
  test('finance-payment-editor', async ({ page }, testInfo) => {
    await page.goto(addPaymentUrl(depositId));
    const family = page.locator('#FamilyName-ts-control');
    await expect(family).toBeFocused({ timeout: 15000 });
    await family.pressSequentially('Anderson');
    await expect(page.locator('.ts-dropdown .option', { hasText: 'Anderson:' })).toBeVisible({ timeout: 15000 });
    await page.keyboard.press('Enter');
    await expect(page.locator('#FamilyID')).not.toHaveValue('0');

    await humanType(page.locator('#CheckNo'), '1042');
    await humanSelect(page.locator('#fundRows .fund-select').first(), { label: 'Building Fund' });
    await humanType(page.locator('#fundRows .fund-amount').first(), '100');
    await expect(page.locator('#fundTotal')).toHaveText('100.00');
    await expect(page.locator('#saveShortcutHint')).toBeVisible();
    // The Add Person / Add Family buttons tuck away five seconds after load (Footer.js).
    await expect(page.locator('#fab-container .fab-button').first()).toHaveClass(/fab-hidden/, { timeout: 15000 });
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'finance-payment-editor',
      title: 'Payment Entry with Ctrl+Enter',
      category: 'Finance & Giving',
      purpose: 'Show a payment being entered on a deposit, with the Ctrl+Enter save-and-next shortcut by the buttons.',
    });
  });
});
