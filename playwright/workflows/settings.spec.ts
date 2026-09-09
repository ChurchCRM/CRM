import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanPause } from '../support/human';

test.describe('Settings', () => {
  // Nice-to-have (shot list): reinforces the self-hosted/control story.
  test('settings-user-permissions', async ({ page }, testInfo) => {
    await page.goto('/admin/system/users');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 600);

    await captureScreen(page, testInfo, {
      name: 'settings-user-permissions',
      purpose: 'Show user/permission management — self-hosted control story',
    });
  });
});
