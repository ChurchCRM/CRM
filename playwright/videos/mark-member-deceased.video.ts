import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType } from '../support/human';

/**
 * Record a member as deceased (date = one week before the run) via the
 * Person Editor, ending on the profile showing the Deceased badge and date.
 * Fields: input#IsDeceased (checkbox) reveals input#DateDeceased
 * (src/PersonEditor.php) — DateTimeUtils::parseAndValidate(..., 'past')
 * rejects a future date, so "one week ago" is both a realistic scenario and
 * safely inside the accepted range.
 *
 * "Matthew Davis" (src/admin/demo/people.json's Davis family, Lenexa) is
 * used deliberately — not Scott/Garcia/Baker/Clark/Whitfield/Anderson,
 * which other specs in this pipeline depend on staying in their original
 * demo state.
 */
test('mark-member-deceased', async ({ page }, testInfo) => {
  await page.goto('/people/list');
  const rows = page.locator('#members tbody tr');
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  await humanPause(page, 500);

  await humanType(page.locator('.dt-search input'), 'Matthew Davis');
  await humanPause(page, 800);
  const targetRow = rows.filter({ hasText: 'Matthew Davis' }).first();
  await expect(targetRow).toBeVisible({ timeout: 15000 });
  await humanClick(targetRow.locator('a').first());
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('h2')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 800);

  // person-view.php has several links matching this href: a hidden "Add
  // New Person" FAB (bare /PersonEditor.php, no PersonID) that renders
  // earlier in the DOM than this person's own toolbar Edit link, plus
  // hidden dropdown-item Edit links for other family members. Scoping to
  // .btn (the toolbar button's class) excludes all of those — verified
  // live that this resolves to exactly one match, the visible Edit link.
  await humanClick(page.locator('a.btn[href*="PersonEditor.php"]').first());
  await page.waitForURL(/PersonEditor\.php/, { timeout: 15000 });
  await expect(page.locator('#IsDeceased')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 800);

  await humanClick(page.locator('#IsDeceased'));
  const dateField = page.locator('#DateDeceased');
  await expect(dateField).toBeVisible({ timeout: 5000 });
  await humanPause(page, 500);

  // One week before the run — a realistic recent date, and safely in the
  // past for the server's date-not-in-the-future validation.
  const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
  const dateString = `${String(weekAgo.getMonth() + 1).padStart(2, '0')}/${String(weekAgo.getDate()).padStart(2, '0')}/${weekAgo.getFullYear()}`;
  await humanType(dateField, dateString);
  await humanPause(page, 800);

  await humanClick(page.locator('button[name="PersonSubmit"]'));

  // PersonEditor.php redirects to Person::getViewURIForId() on success.
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.getByText('Deceased', { exact: false }).first()).toBeVisible({ timeout: 10000 });
  await humanPause(page, 2000);

  await captureScreen(page, testInfo, {
    name: 'mark-member-deceased',
    purpose: 'Show recording a deceased date in the Person Editor, ending on the profile with the Deceased badge',
  });
});
