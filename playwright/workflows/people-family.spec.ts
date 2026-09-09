import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, humanType } from '../support/human';

test.describe('People & Families', () => {
  test('people-family-overview', async ({ page }, testInfo) => {
    // A church user browsing People & Families: open the family list, then
    // drill into one family's profile. Demo data includes inactive families
    // too (shown with an "Inactive" badge right in the row) — skip those,
    // since that's not a good look for a marketing screenshot.
    await page.goto('/people/family');

    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    const activeRow = rows.filter({ hasNotText: 'Inactive' }).first();
    await expect(activeRow).toBeVisible({ timeout: 15000 });
    await humanClick(activeRow.locator('td').first().locator('a').first());
    await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
    await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: 'people-family-overview',
      purpose: 'Show how ChurchCRM organizes people and families',
    });
  });

  test('people-family-new-family', async ({ page }, testInfo) => {
    // A church user registering a brand-new family: fill out the family
    // form for the Whitfields, save, and land on their new profile page.
    // "Whitfield" (not "Johnson" or any other seed surname — see
    // src/admin/demo/people.json's lastName values) so this created family
    // is never confused with, or accidentally aliased to, a seeded one in
    // other screenshots (e.g. the pledge report already has a real,
    // separate "Johnson" family from the demo data).
    await page.goto('/FamilyEditor.php');
    await humanPause(page, 500);

    await humanType(page.locator('#FamilyName'), 'Whitfield');
    await humanType(page.locator('#Address1'), '245 Willow Creek Rd');
    await humanType(page.locator('#City'), 'Springfield');
    await humanSelect(page.locator('#State'), 'IL');
    await humanType(page.locator('#Zip'), '62704');
    await humanType(page.locator('#HomePhone'), '(555) 010-1000');
    await humanType(page.locator('#Email'), 'family.whitfield@demo.churchcrm.io');

    await humanType(page.locator('input[name="FirstName1"]'), 'Michael');
    await humanType(page.locator('input[name="LastName1"]'), 'Whitfield');
    await humanSelect(page.locator('select[name="Gender1"]'), '1');

    await humanPause(page, 500);
    await humanClick(page.locator('button[name="FamilySubmit"]'));
    await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
    await expect(page.getByText('Whitfield', { exact: false }).first()).toBeVisible({ timeout: 10000 });
    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: 'people-family-new-family',
      purpose: 'Show creating a new family and its resulting profile page',
    });
  });

  test('people-directory-list', async ({ page }, testInfo) => {
    // Secondary/optional shot (shot list): a filtered person directory, in
    // case the family record alone feels thin on its own.
    await page.goto('/people/list');
    const rows = page.locator('#members tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: 'people-directory-list',
      purpose: 'Show the filtered person directory list',
    });
  });
});
