import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';

/**
 * Front-desk "find someone in seconds": type a name in the global search
 * and open the matching person record. Uses a unique demo subject
 * (Joseph Hall — same person as mark-member-inactive, real photo) so the
 * result is unambiguous and marketing-clean.
 */
test('find-someone', async ({ page }, testInfo) => {
  await page.goto('/v2/dashboard');
  await expect(page.locator('#globalSearch')).toBeVisible({ timeout: 15000 });
  await humanPause(page, 800);

  await humanType(page.locator('#globalSearch'), 'Joseph Hall');
  await humanPause(page, 600);

  // Global search results render as .dropdown-item links inside #globalSearchDropdown.
  const result = page.locator('#globalSearchDropdown .dropdown-item', { hasText: 'Joseph Hall' }).first();
  await expect(result).toBeVisible({ timeout: 10000 });
  await humanClick(result);

  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('h2')).toContainText('Joseph Hall', { timeout: 10000 });
  await settle(page, 1500);

  await captureScreen(page, testInfo, {
    name: 'find-someone',
    title: 'Find Someone in Seconds',
    category: 'Recordings',
    purpose: 'Show a front-desk volunteer searching by name and opening the person record immediately',
  });
});
