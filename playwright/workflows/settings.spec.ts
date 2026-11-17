import { expect, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { settle } from '../support/human';

test.describe('Settings', () => {
  // Nice-to-have (shot list): reinforces the self-hosted/control story.
  const settingsUserPermissionsCapture = defineCapture({
    name: 'settings-user-permissions',
    locales: 'all',
    title: 'User Permissions',
    category: 'Admin & Settings',
    purpose: 'Show user/permission management — self-hosted control story',
    usedIn: ['docs', 'website'],
  });
  test('settings-user-permissions', settingsUserPermissionsCapture, async ({ page }, testInfo) => {
    await page.goto('/admin/system/users');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await settle(page, 600);

    await captureScreen(page, testInfo, settingsUserPermissionsCapture);
  });

  test('admin-plugin-management', async ({ page }, testInfo) => {
    // Route: src/plugins/index.php ("/plugins/management/..."), gated to
    // admins. Reinforces the plugin ecosystem story — core + community
    // plugins, verification/risk badges, all managed from one screen.
    await page.goto('/plugins/management');
    await expect(page.locator('[data-plugin-id]').first()).toBeVisible({ timeout: 15000 });
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'admin-plugin-management',
      title: 'Plugin Management',
      category: 'Admin & Settings',
      purpose: 'Show the plugin management admin page — core and community plugins',
      usedIn: ['docs', 'website'],
    });
  });
});
