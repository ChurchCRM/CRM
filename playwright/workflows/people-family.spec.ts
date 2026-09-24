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

    // Not "...and a geocoded map": family-view.php stacks the photo above
    // the Address card in a narrow right column, so the map itself renders
    // below the fold at this viewport regardless of family — the
    // "Geocoded" badge is what's actually visible in frame. See
    // marketing-visuals-pipeline.md's "Map visibility" note.
    await captureScreen(page, testInfo, {
      name: 'people-family-overview',
      purpose: 'Show how ChurchCRM organizes people and families, with member photos and a geocoded address',
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

  // Inactive and deceased subjects come from src/admin/demo/people.json
  // (Mark King: active false; Daniel Johnson: dateDeceased; Campbell:
  // inactive family) so screenshots never mutate data another spec reads.
  test('person-inactive-profile', async ({ page }, testInfo) => {
    await page.goto('/people/list?personActiveStatus=inactive');
    const rows = page.locator('#members tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });

    await humanType(page.locator('.dt-search input'), 'Mark King');
    await humanPause(page, 500);
    const targetRow = rows.filter({ hasText: 'Mark King' }).first();
    await expect(targetRow).toBeVisible({ timeout: 15000 });
    await humanClick(targetRow.locator('a').first());
    await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
    await expect(page.locator('#person-deactivated')).toBeVisible({ timeout: 10000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'person-inactive-profile',
      purpose: 'Show a person profile for an inactive member, including the inactive status banner',
    });
  });

  test('person-deceased-profile', async ({ page }, testInfo) => {
    // The people list always opens filtered to Living, so read the profile
    // link from the server-rendered rows instead.
    const listHtml = await (await page.request.get('/people/list')).text();
    const profilePath = listHtml.match(/href="([^"]*\/people\/view\/\d+)" class="fw-bold">Daniel Johnson</)?.[1];
    expect(profilePath).toBeTruthy();

    await page.goto(profilePath!);
    await expect(page.locator('.badge', { hasText: 'Deceased' })).toBeVisible({ timeout: 10000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'person-deceased-profile',
      purpose: 'Show a person profile for a deceased member, including the Deceased badge and date',
    });
  });

  test('family-inactive-profile', async ({ page }, testInfo) => {
    await page.goto('/people/family?familyActiveStatus=inactive');
    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });

    await humanType(page.locator('.dt-search input'), 'Campbell');
    await humanPause(page, 500);
    const targetRow = rows.filter({ hasText: 'Campbell' }).first();
    await expect(targetRow).toBeVisible({ timeout: 15000 });
    await humanClick(targetRow.locator('td').first().locator('a').first());
    await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
    await expect(page.locator('#family-deactivated')).toBeVisible({ timeout: 10000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'family-inactive-profile',
      purpose: 'Show a family profile for an inactive family, including the inactive status banner',
    });
  });
});
