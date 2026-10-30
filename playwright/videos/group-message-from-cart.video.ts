import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { gotoFirstActiveGroup } from '../support/groups';
import { humanClick, humanType, settle } from '../support/human';

test('group-message-from-cart', async ({ page }, testInfo) => {
  await gotoFirstActiveGroup(page);
  await settle(page, 900);

  await humanClick(page.locator('#group-view-toolbar .dropdown-toggle', { has: page.locator('.fa-cart-plus') }));
  await expect(page.locator('#addAllToCart')).toBeVisible();
  await humanClick(page.locator('#addAllToCart'));
  await settle(page, 800);

  await page.goto('/people/cart');
  await expect(page.locator('#cart-listing-table tbody tr').first()).toBeVisible({ timeout: 15000 });
  await settle(page, 900);

  const emailButton = page.locator("[data-email-composer][data-email-endpoint='cart/emails']");
  await expect(emailButton).toBeVisible();
  await humanClick(emailButton);
  await expect(page.locator('#crm-email-composer-modal')).toBeVisible();
  await expect(page.locator('#crm-email-composer-modal .modal-title .badge')).toBeVisible();
  await settle(page, 700);

  await humanClick(page.locator('#crm-email-send-btn'));
  await expect(page.locator('#crm-email-subject')).toBeVisible();
  await humanType(page.locator('#crm-email-subject'), 'Welcome team update');
  await humanType(page.locator('#crm-email-body'), 'Thank you for serving our church. Here is this week\'s welcome-team update.');
  await humanPause(page, 700);
  await humanClick(page.locator('#crm-email-send-submit'));
  await expect(page.locator('#crm-email-composer-modal')).toBeHidden({ timeout: 15000 });
  await settle(page, 1200);

  await captureScreen(page, testInfo, {
    name: 'group-message-from-cart',
    title: 'Message a Group',
    category: 'Communication',
    purpose: 'Show a church admin moving group members into the People Cart and sending one message to the team',
  });
});
