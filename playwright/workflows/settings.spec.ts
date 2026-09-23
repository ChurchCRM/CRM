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

  test('admin-plugin-management', async ({ page }, testInfo) => {
    // Route: src/plugins/index.php ("/plugins/management/..."), gated to
    // admins. Reinforces the plugin ecosystem story — core + community
    // plugins, verification/risk badges, all managed from one screen.
    await page.goto('/plugins/management');
    await expect(page.locator('[data-plugin-id]').first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 600);

    await captureScreen(page, testInfo, {
      name: 'admin-plugin-management',
      purpose: 'Show the plugin management admin page — core and community plugins',
    });
  });
});
