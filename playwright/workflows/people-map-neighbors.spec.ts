import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType } from '../support/human';

test.describe('Maps', () => {
  test('people-map-find-neighbors', async ({ page }, testInfo) => {
    // "Find Neighbors" (src/people/routes/map.php's getMapNeighborsView) —
    // nearest-family search from a given family, with a distance-banded
    // Leaflet map and results table. Opened via the Scott family's profile
    // link (same family as people-family-overview.spec.ts — real demo photo
    // + geocoded address, see src/admin/demo/people.json) rather than
    // building the URL directly, since the deep link's familyId is only
    // known after the demo import assigns it. That query param also makes
    // webpack/people/map-neighbors.js auto-run the search on load (see its
    // "Auto-run when the page is opened with ?familyId=N" comment) — no
    // manual form interaction needed for a clean, populated results shot.
    await page.goto('/people/family');
    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    await humanType(page.locator('.dt-search input'), 'Scott');
    await humanPause(page, 500);
    const scottRow = rows.filter({ hasText: 'Scott' }).first();
    await expect(scottRow).toBeVisible({ timeout: 15000 });
    await humanClick(scottRow.locator('td').first().locator('a').first());
    await page.waitForURL(/\/people\/family\/(\d+)/, { timeout: 15000 });
    await humanPause(page, 800);

    // A screenshot only needs the landing view, not a demonstrated click
    // path (that's what find-neighbors.video.ts is for) — so navigate
    // straight to the deep link once the family's id is known, same as
    // cypress/e2e/ui/people/standard.map.spec.js does
    // (cy.visit("people/map/neighbors?familyId=1")), rather than clicking
    // through family-view.php's "Find Neighbors" link.
    const familyId = page.url().match(/\/people\/family\/(\d+)/)?.[1];
    await page.goto(`/people/map/neighbors?familyId=${familyId}`);
    await expect(page.locator('#neighborsMap')).toBeVisible({ timeout: 15000 });

    // Auto-run search populates the table and unhides it from its initial
    // d-none state — wait for that rather than a fixed timer.
    await expect(page.locator('#neighborsTable')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 1500);

    await captureScreen(page, testInfo, {
      name: 'people-map-find-neighbors',
      purpose: 'Show Find Neighbors — nearest families to a selected family, plotted by distance on the map',
    });
  });

  test('people-map-group-view', async ({ page }, testInfo) => {
    // Group-filtered congregation map (src/people/routes/map.php's
    // getMapView with ?groupId=N) — same page as people-map-overview.spec's
    // full congregation map, but scoped to one group with a role-based
    // legend (Leader/Member) instead of the classification-based legend.
    // "Worship Service" (src/admin/demo/groups.json) is used deliberately —
    // not a Sunday School class already implied elsewhere, and not a group
    // whose members overlap with the inactive/deceased demo videos.
    await page.goto('/groups/dashboard');
    const rows = page.locator('#groupsTable tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    // #groupsTable paginates (10/page, default sort alphabetical by name)
    // and "Worship Service" is 11th of 12 demo groups alphabetically — off
    // the default first page. Search for it instead of filtering whatever
    // rows happen to be rendered (same pattern as the family/member search
    // above and in people-family.spec.ts).
    await humanType(page.locator('.dt-search input'), 'Worship Service');
    await humanPause(page, 500);
    const worshipRow = rows.filter({ hasText: 'Worship Service' }).first();
    await expect(worshipRow).toBeVisible({ timeout: 15000 });
    await humanClick(worshipRow.locator('a').first());
    await page.waitForURL(/\/groups\/view\/(\d+)/, { timeout: 15000 });
    await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
    await humanPause(page, 500);

    // A screenshot only needs the landing view — navigate straight to the
    // deep link once the group's id is known, rather than clicking
    // group-view.php's "Map" button (which shares its href pattern with
    // the global cart widget's hidden "Map cart items" link).
    const groupId = page.url().match(/\/groups\/view\/(\d+)/)?.[1];
    await page.goto(`/people/map?groupId=${groupId}`);
    await expect(page.locator('#map')).toBeVisible({ timeout: 15000 });
    // getByText('Worship Service') alone would also match the hidden
    // sidebar nav link for this group (<span class="nav-link-title">) —
    // scope to the page subtitle ("Showing members of: ...", set by
    // map.php's sPageSubtitle and rendered in Header.php) to check the
    // actual visible page content instead.
    await expect(page.locator('.text-body-secondary').getByText('Worship Service', { exact: false })).toBeVisible({
      timeout: 10000,
    });
    // Leaflet tiles/pins and the role-based legend load asynchronously
    // after the container itself is visible.
    await humanPause(page, 2000);

    await captureScreen(page, testInfo, {
      name: 'people-map-group-view',
      purpose: 'Show the congregation map filtered to one group, with a role-based legend',
    });
  });
});
