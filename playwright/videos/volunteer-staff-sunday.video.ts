import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, settle } from '../support/human';
import { removeVolunteerSeed, seedVolunteerFixture, type VolunteerSeed } from '../support/volunteer';

/**
 * A coordinator fills a gap: from "Needs filling" on the Ministry Dashboard to the
 * Sunday's staffing page, then two volunteers from the picker until Cleanup Crew
 * reads Full. Seeded and cleaned up by support/volunteer.ts.
 */
let fixture: VolunteerSeed;

test.beforeAll(async ({ browser }) => {
  fixture = await seedVolunteerFixture(browser);
});

test.afterAll(async ({ browser }) => {
  await removeVolunteerSeed(browser);
});

test('volunteer-staff-sunday', async ({ page }, testInfo) => {
  const { coffeeOccurrenceId, positionIds, personIds } = fixture;

  await page.goto('/ministries/dashboard');
  await expect(page.locator('#volunteer-gaps-content')).toBeVisible({ timeout: 30000 });
  await humanPause(page, 2000);

  await humanClick(
    page.locator(
      `.volunteer-gap-link[data-occurrence-id="${coffeeOccurrenceId}"][data-position-id="${positionIds.cleanup}"]`
    )
  );
  await page.waitForURL(/\/ministries\/occurrences\/\d+/, { timeout: 30000 });
  const card = page.locator(`.volunteer-requirement[data-position-id="${positionIds.cleanup}"]`);
  await expect(card.locator('.requirement-counts')).toHaveText('Needs 2 more', { timeout: 30000 });
  await humanPause(page, 2000);

  for (const volunteer of [personIds.linda, personIds.edward]) {
    await humanClick(card.locator('.volunteer-assign-btn'));
    const control = page.locator('#volunteer-assign-modal .ts-control');
    await expect(control).toBeVisible({ timeout: 30000 });
    await humanPause(page, 600);
    const isOpen = await page.evaluate(
      () =>
        (document.getElementById('assign-person-select') as { tomselect?: { isOpen: boolean } } | null)?.tomselect
          ?.isOpen
    );
    if (!isOpen) {
      await humanClick(control);
    }
    await humanPause(page, 1200);
    await humanClick(page.locator(`body > .ts-dropdown .option[data-value="${volunteer}"]`));
    await humanPause(page, 700);
    await humanClick(page.locator('#assign-save'));
    await expect(page.locator('#volunteer-assign-modal')).toBeHidden({ timeout: 30000 });
    await expect(card.locator(`.volunteer-assignment-row[data-person-id="${volunteer}"]`)).toBeVisible({
      timeout: 30000,
    });
    await humanPause(page, 1800);
  }

  await expect(card.locator('.requirement-counts')).toHaveText('Full · 2 of 2');
  await settle(page, 2500);

  await captureScreen(page, testInfo, {
    name: 'volunteer-staff-sunday',
    title: 'Staff a Sunday Service',
    category: 'Recordings',
    purpose: 'Show a coordinator filling an open position from the Ministry Dashboard until it reads Full.',
  });
});
