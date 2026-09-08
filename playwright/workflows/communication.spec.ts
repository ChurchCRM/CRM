import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { gotoFirstActiveGroup } from '../support/groups';
import { humanClick, humanPause } from '../support/human';

test.describe('Communication', () => {
  test('communication-mailing-list', async ({ page }, testInfo) => {
    // Mailing-list selection: add a real group's membership to the cart —
    // the cart IS ChurchCRM's mailing-list-selection mechanism. Not the
    // /v2/email/dashboard SMTP settings page: that page's actual composer
    // is gated behind SystemConfig::isEmailEnabled(), which requires valid
    // SMTP server settings (SystemConfig.php's hasValidMailServerSettings())
    // in addition to the bEnabledEmail toggle — the demo instance has no
    // real SMTP server, so that page only ever shows "Email is Disabled" /
    // "SMTP Not Configured", which is not a usable marketing shot.
    await gotoFirstActiveGroup(page);

    await humanClick(page.locator('#group-view-toolbar .dropdown-toggle', { hasText: 'Cart' }));
    await page.locator('#addAllToCart').waitFor({ state: 'visible', timeout: 5000 });
    await humanClick(page.locator('#addAllToCart'));
    await humanPause(page, 500);

    await page.goto('/v2/cart');
    await expect(page.locator('#cart-listing-table tbody tr').first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 600);

    await captureScreen(page, testInfo, {
      name: 'communication-mailing-list',
      purpose: 'Show selecting a group into the cart as a mailing list',
    });
  });
});
