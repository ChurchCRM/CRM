import { expect, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { settle } from '../support/human';

test.describe('Dashboard', () => {
  const dashboardHeroCapture = defineCapture({
    name: 'dashboard-hero',
    locales: 'all',
    title: 'Dashboard',
    category: 'Dashboards',
    dark: 'dashboard-hero-dark',
    purpose: 'Landing dashboard after login — hero shot',
    usedIn: ['docs', 'website'],
  });
  test('dashboard-hero', dashboardHeroCapture, async ({ page }, testInfo) => {
    // The landing view after login — the marketing "hero" shot: nav +
    // summary widgets, populated by the seeded demo data.
    await page.goto('/v2/dashboard');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await settle(page, 800);

    await captureScreen(page, testInfo, dashboardHeroCapture);
  });
});
