import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, humanType } from '../support/human';

test.describe('People & Families', () => {
  test('people-family-overview', async ({ page }, testInfo) => {
    // A church user browsing People & Families: open the family list, then
    // drill into one family's profile. Deliberately the Scott family (see
    // src/admin/demo/people.json), not just the first active row — every
    // one of its 6 members has a real demo photo file, and its Raytown, MO
    // address has real lat/lng, so the profile shows a full set of member
    // photos and a properly geocoded map instead of placeholder avatars or
    // an empty/default map view.
    await page.goto('/people/family');

    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    // DataTables paginates (src/people/views/family-list.php) — with 62
    // demo families, "Scott" isn't on the default first page, so search
    // for it instead of filtering whatever rows happen to be rendered.
    // DataTables 2.x's search box has no #{table}_filter wrapper (that's
    // the 1.x id) — its input lives at `.dt-search input` (id `dt-search-N`).
    await humanType(page.locator('.dt-search input'), 'Scott');
    await humanPause(page, 500);
    const scottRow = rows.filter({ hasText: 'Scott' }).first();
    await expect(scottRow).toBeVisible({ timeout: 15000 });
    await humanClick(scottRow.locator('td').first().locator('a').first());
    await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
    await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
    // Let the map tiles and member photo thumbnails finish loading —
    // captureScreen's own networkidle wait covers in-flight XHRs, but
    // Leaflet's tile images and photo <img> tags aren't always caught by
    // that if they're still queued.
    await humanPause(page, 1000);

    await captureScreen(page, testInfo, {
      name: 'people-family-overview',
      purpose: 'Show how ChurchCRM organizes people and families, with member photos and a geocoded map',
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

  test('people-map-overview', async ({ page }, testInfo) => {
    // A church user viewing the family map: pins are plotted from each
    // family's geocoded address (see src/admin/demo/people.json), so this
    // relies on the same demo data that gives people-family-overview a
    // real, non-empty map — no address selection needed here, the page
    // just needs enough seeded families with valid lat/lng to render a
    // full map instead of a single dot or an empty view.
    await page.goto('/people/map');
    await expect(page.locator('#map')).toBeVisible({ timeout: 15000 });
    // Leaflet loads tiles and pins asynchronously after the container
    // itself is visible — give them time to paint before capturing.
    await humanPause(page, 1500);

    await captureScreen(page, testInfo, {
      name: 'people-map-overview',
      purpose: 'Show the family map with geocoded pins across the congregation',
    });
  });

  test('people-photo-gallery', async ({ page }, testInfo) => {
    // A church user browsing the photo directory: a grid of member photos
    // (route: src/people/routes/people.php's /photos, function
    // viewPeoplePhotoGallery). Defaults to "photos only" already
    // (showOnlyWithPhotos defaults true), so no extra filtering needed to
    // avoid a grid full of placeholder avatars.
    await page.goto('/people/photos');
    await expect(page.locator('#photo-grid')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'people-photo-gallery',
      purpose: 'Show the photo directory — a grid of congregation member photos',
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
