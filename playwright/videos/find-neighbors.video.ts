import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType } from '../support/human';

/**
 * A church user looking up who lives near one family: open the Scott
 * family's profile (real demo photo + geocoded address, see
 * src/admin/demo/people.json — same family as people-family-overview),
 * click "Find Neighbors", and watch the distance-banded results populate
 * on the map. webpack/people/map-neighbors.js auto-runs the search when the
 * page loads with ?familyId=N (see its "Auto-run" comment), so this is a
 * real navigation + async render, not a scripted form fill — the #familySelect
 * field is TomSelect-enhanced (new TomSelect(this) in that same file),
 * which is unreliable to drive by simulated typing/click in an automated
 * recording, so this video shows the deep-link entry point instead: the
 * same "Find Neighbors" button a user clicks from a family's own profile.
 */
test('find-neighbors-search', async ({ page }, testInfo) => {
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

  await humanClick(page.locator('a[href*="map/neighbors"]').first());
  await page.waitForURL(/\/people\/map\/neighbors/, { timeout: 15000 });
  await expect(page.locator('#neighborsMap')).toBeVisible({ timeout: 15000 });

  // Auto-run search populates the table and unhides it from its initial
  // d-none state.
  await expect(page.locator('#neighborsTable')).toBeVisible({ timeout: 15000 });
  await humanPause(page, 2500);

  await captureScreen(page, testInfo, {
    name: 'find-neighbors-search',
    purpose: 'Show finding nearby families from a family profile, with results plotted by distance on the map',
  });
});
