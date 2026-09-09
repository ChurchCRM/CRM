import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType } from '../support/human';

/**
 * themeMode ('ui.style': 'auto' | 'default' | 'dark') is a per-user setting
 * persisted server-side (see src/Include/Header.php reading
 * User::getThemeMode() on every render), not a per-browser cookie or local
 * storage flag. Every workflow spec in this pipeline authenticates as the
 * same demo admin via the shared storageState, so leaving this flipped to
 * dark would silently make every other spec's screenshots dark too. Each
 * test below sets dark, captures, then reverts to 'default' in a finally
 * block so the revert always runs even if the capture itself fails.
 *
 * Uses the same direct API call as playwright/support/marketing-clean.ts's
 * dismissSystemNotifications (POST /api/user/{id}/setting/{name}, no CSRF
 * middleware on this route) instead of clicking through the Settings UI —
 * faster, and the appearance tab's radio-button save flow isn't itself the
 * thing being screenshotted here.
 */
async function setThemeMode(page: import('@playwright/test').Page, mode: 'auto' | 'default' | 'dark'): Promise<void> {
  const userId = await page.evaluate(() => (window as unknown as { CRM?: { userId?: number } }).CRM?.userId);
  if (!userId) {
    throw new Error('window.CRM.userId not available — cannot set theme mode');
  }
  await page.request.post(`/api/user/${userId}/setting/ui.style`, { data: { value: mode } });
}

test.describe('Dark Mode', () => {
  test('dashboard-hero-dark', async ({ page }, testInfo) => {
    await page.goto('/v2/dashboard');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });

    try {
      await setThemeMode(page, 'dark');
      await page.reload();
      await expect(page.locator('html[data-bs-theme="dark"]')).toBeAttached({ timeout: 10000 });
      await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
      await humanPause(page, 800);

      await captureScreen(page, testInfo, {
        name: 'dashboard-hero-dark',
        purpose: 'Landing dashboard after login — hero shot in dark mode',
      });
    } finally {
      await setThemeMode(page, 'default');
    }
  });

  test('people-family-overview-dark', async ({ page }, testInfo) => {
    // Same Scott family as people-family.spec.ts's people-family-overview —
    // every member has a real demo photo and the address is geocoded, so
    // the dark variant shows the same fully-populated photos + map.
    await page.goto('/people/family');
    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });

    try {
      await setThemeMode(page, 'dark');
      await page.reload();
      await expect(page.locator('html[data-bs-theme="dark"]')).toBeAttached({ timeout: 10000 });
      await expect(rows.first()).toBeVisible({ timeout: 15000 });
      await humanPause(page, 500);

      // With 62 demo families, "Scott" isn't on the default first page —
      // search for it (same DataTables 2.x `.dt-search input`, not the
      // 1.x `#{table}_filter` wrapper, as people-family.spec.ts).
      await humanType(page.locator('.dt-search input'), 'Scott');
      await humanPause(page, 500);

      const scottRow = rows.filter({ hasText: 'Scott' }).first();
      await expect(scottRow).toBeVisible({ timeout: 15000 });
      await humanClick(scottRow.locator('td').first().locator('a').first());
      await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
      await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
      await humanPause(page, 1000);

      await captureScreen(page, testInfo, {
        name: 'people-family-overview-dark',
        purpose: 'Show family profile with member photos and geocoded map in dark mode',
      });
    } finally {
      await setThemeMode(page, 'default');
    }
  });
});
