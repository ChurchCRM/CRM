import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType } from '../support/human';

/**
 * Mark a member inactive via the profile's Actions menu and land on the
 * updated profile showing the "This Person is Inactive" banner. This is a
 * status toggle (per_DateDeactivated on the Person model), not an edit-form
 * field — see src/skin/js/PersonView.js's activateDeactivatePerson handler,
 * which POSTs to /person/{id}/activate/false behind a bootbox confirm.
 *
 * "Anthony Anderson" (src/admin/demo/people.json's Anderson family,
 * Overland Park) is used deliberately — not Scott/Garcia/Baker/Clark/
 * Whitfield, which other specs in this pipeline depend on staying in their
 * original demo state.
 */
test('mark-member-inactive', async ({ page }, testInfo) => {
  await page.goto('/people/list');
  const rows = page.locator('#members tbody tr');
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  await humanPause(page, 500);

  await humanType(page.locator('.dt-search input'), 'Anthony Anderson');
  await humanPause(page, 800);
  const targetRow = rows.filter({ hasText: 'Anthony Anderson' }).first();
  await expect(targetRow).toBeVisible({ timeout: 15000 });
  await humanClick(targetRow.locator('a').first());
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 1000);

  // Open the Actions dropdown (id="person-actions-dropdown") and choose
  // "Set Inactive" — a plain <a>, not a button.
  await humanClick(page.locator('#person-actions-dropdown'));
  const setInactiveItem = page.locator('#activateDeactivatePerson');
  await expect(setInactiveItem).toBeVisible({ timeout: 5000 });
  await expect(setInactiveItem).toHaveText(/Set Inactive/);
  await humanPause(page, 500);
  await humanClick(setInactiveItem);

  // Bootbox confirm dialog — .bootbox-accept is Bootbox's own class,
  // stable regardless of the "OK" label's locale.
  const confirmDialog = page.locator('.bootbox');
  await expect(confirmDialog).toBeVisible({ timeout: 5000 });
  await expect(confirmDialog).toContainText('Anthony Anderson');
  await humanPause(page, 1500);
  await humanClick(page.locator('.bootbox-accept'));

  // The handler reloads the page on success; the banner is server-driven
  // from window.CRM.currentPersonActive.
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('#person-deactivated')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 2000);

  await captureScreen(page, testInfo, {
    name: 'mark-member-inactive',
    purpose: 'Show marking a member inactive from the profile Actions menu, ending on the Inactive banner',
  });
});
