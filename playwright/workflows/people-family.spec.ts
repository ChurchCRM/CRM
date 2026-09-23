import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, humanType } from '../support/human';

test.describe('People & Families', () => {
  test('people-family-overview', async ({ page }, testInfo) => {
    await page.goto('/people/family');

    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    await humanType(page.locator('.dt-search input'), 'Scott');
    await humanPause(page, 500);
    const scottRow = rows.filter({ hasText: 'Scott' }).first();
    await expect(scottRow).toBeVisible({ timeout: 15000 });
    await humanClick(scottRow.locator('td').first().locator('a').first());
    await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
    await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
    await humanPause(page, 1000);

    await captureScreen(page, testInfo, {
      name: 'people-family-overview',
      purpose: 'Show how ChurchCRM organizes people and families, with member photos and a geocoded map',
    });
  });

  test('people-family-new-family', async ({ page }, testInfo) => {
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

  test('people-map-overview', async ({ page }, testInfo) => {
    await page.goto('/people/map');
    await expect(page.locator('#map')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 1500);

    await captureScreen(page, testInfo, {
      name: 'people-map-overview',
      purpose: 'Show the family map with geocoded pins across the congregation',
    });
  });

  test('people-photo-gallery', async ({ page }, testInfo) => {
    await page.goto('/people/photos');
    await expect(page.locator('#photo-grid')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'people-photo-gallery',
      purpose: 'Show the photo directory — a grid of congregation member photos',
    });
  });

  test('people-directory-list', async ({ page }, testInfo) => {
    await page.goto('/people/list');
    const rows = page.locator('#members tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: 'people-directory-list',
      purpose: 'Show the filtered person directory list',
    });
  });

  // "Charles Green" (src/admin/demo/people.json's Green family) — deliberately
  // not Joseph Hall, who mark-member-inactive.video.ts already marks inactive
  // earlier in this same pipeline run. Reusing that subject here means this
  // test's own search on the default (active-only) list finds no row, since
  // the video test already flipped it out of the active set.
  test('person-inactive-profile', async ({ page }, testInfo) => {
    await page.goto('/people/list');
    const rows = page.locator('#members tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });

    await humanType(page.locator('.dt-search input'), 'Charles Green');
    await humanPause(page, 500);
    const targetRow = rows.filter({ hasText: 'Charles Green' }).first();
    await expect(targetRow).toBeVisible({ timeout: 15000 });
    await humanClick(targetRow.locator('a').first());
    await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });

    await humanClick(page.locator('#person-actions-dropdown'));
    const setInactiveItem = page.locator('#activateDeactivatePerson');
    await expect(setInactiveItem).toBeVisible({ timeout: 5000 });
    await expect(setInactiveItem).toHaveText(/Set Inactive/);
    await humanClick(setInactiveItem);

    const confirmDialog = page.locator('.bootbox');
    await expect(confirmDialog).toBeVisible({ timeout: 5000 });
    await expect(confirmDialog).toContainText('Charles Green');
    await humanClick(page.locator('.bootbox-accept'));

    await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
    await expect(page.locator('#person-deactivated')).toBeVisible({ timeout: 10000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'person-inactive-profile',
      purpose: 'Show a person profile after the member has been marked inactive, including the inactive status banner',
    });
  });

  // "Timothy Torres" — deliberately not Matthew Davis, who
  // mark-member-deceased.video.ts already marks deceased earlier in this
  // same pipeline run; see the comment on person-inactive-profile above.
  test('person-deceased-profile', async ({ page }, testInfo) => {
    await page.goto('/people/list');
    const rows = page.locator('#members tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });

    await humanType(page.locator('.dt-search input'), 'Timothy Torres');
    await humanPause(page, 500);
    const targetRow = rows.filter({ hasText: 'Timothy Torres' }).first();
    await expect(targetRow).toBeVisible({ timeout: 15000 });
    await humanClick(targetRow.locator('a').first());
    await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });

    await humanClick(page.locator('a.btn[href*="PersonEditor.php"]').first());
    await page.waitForURL(/PersonEditor\.php/, { timeout: 15000 });
    await expect(page.locator('#IsDeceased')).toBeVisible({ timeout: 10000 });

    await humanClick(page.locator('#IsDeceased'));
    const dateField = page.locator('#DateDeceased');
    await expect(dateField).toBeVisible({ timeout: 5000 });

    const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
    const dateString = `${String(weekAgo.getMonth() + 1).padStart(2, '0')}/${String(weekAgo.getDate()).padStart(2, '0')}/${weekAgo.getFullYear()}`;
    await humanType(dateField, dateString);
    await humanClick(page.locator('button[name="PersonSubmit"]'));

    await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
    await expect(page.getByText('Deceased', { exact: false }).first()).toBeVisible({ timeout: 10000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'person-deceased-profile',
      purpose: 'Show a person profile after recording a deceased date, including the Deceased badge and date',
    });
  });
});
