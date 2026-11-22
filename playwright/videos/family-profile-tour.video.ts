import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';

// Adams (src/admin/demo/people.json): two parents, Lisa in the Youth Meeting class, and notes.
test('family-profile-tour', async ({ page }, testInfo) => {
  await page.goto('/people/dashboard');
  await expect(page.locator('h2').first()).toBeVisible({ timeout: 15000 });
  await humanPause(page, 2000);

  await page.goto('/people/family');
  const rows = page.locator('#families tbody tr');
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  await humanPause(page, 800);
  await humanType(page.locator('.dt-search input'), 'stephanie.adams@demo.churchcrm.io');
  const familyRow = rows.filter({ hasText: 'stephanie.adams@demo.churchcrm.io' }).first();
  await expect(familyRow).toBeVisible({ timeout: 15000 });
  await humanPause(page, 800);
  await humanClick(familyRow.locator('td').first().locator('a').first());
  await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
  await expect(page.locator('#family-profile-header')).toBeVisible({ timeout: 10000 });
  const familyUrl = page.url();
  await humanPause(page, 2000);

  // Members, then the address map, properties and the tabs below
  await page.locator('.member-table').first().scrollIntoViewIfNeeded();
  await expect(page.locator('.member-phones a[href^="tel:"]').first()).toBeVisible();
  await humanPause(page, 2500);
  await page.locator('#family-address-card').scrollIntoViewIfNeeded();
  await expect(page.locator('#map1 .leaflet-tile').first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#map1 .leaflet-tile:not(.leaflet-tile-loaded)')).toHaveCount(0, { timeout: 20000 });
  await humanPause(page, 2500);
  await page.locator('#nav-item-notes').scrollIntoViewIfNeeded();
  await humanPause(page, 2500);
  await humanClick(page.locator('#nav-item-giving'));
  await expect(page.locator('#giving-history')).toHaveClass(/active/);
  await humanPause(page, 2000);

  // Back to the top, then open a parent
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
  await humanPause(page, 1500);
  await humanClick(page.locator('.member-table a[href*="/people/view/"]').filter({ hasText: 'Stephanie' }).first());
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('#person-profile-header')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 2500);
  await page.locator('#person-address-card').scrollIntoViewIfNeeded();
  await humanPause(page, 2000);
  await humanClick(page.locator('#nav-item-notes'));
  await expect(page.locator('#notes')).toHaveClass(/active/);
  await humanPause(page, 2500);

  // Back to the family, then a child: Sunday school and notes
  await page.goto(familyUrl);
  await expect(page.locator('#family-profile-header')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 1200);
  await humanClick(page.locator('.member-table a[href*="/people/view/"]').filter({ hasText: 'Lisa' }).first());
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('#person-profile-header')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 2000);
  await page.locator('#nav-item-groups').scrollIntoViewIfNeeded();
  await humanClick(page.locator('#nav-item-groups'));
  await expect(page.locator('#groups')).toHaveClass(/active/);
  await expect(page.locator('#groups')).toContainText('Youth Meeting');
  await humanPause(page, 3000);
  await humanClick(page.locator('#nav-item-notes'));
  await humanPause(page, 2000);
  await humanClick(page.locator('#nav-item-timeline'));
  await settle(page, 2500);

  await captureScreen(page, testInfo, {
    name: 'family-profile-tour',
    title: 'A Whole Family on One Page',
    category: 'Recordings',
    purpose:
      'Show a church secretary how to go from the dashboard to a family, see who is in the household, how to reach each person, where they live, their giving, then open a parent and a child for groups, Sunday school and notes',
  });
});
