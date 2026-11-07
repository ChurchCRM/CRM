import { expect, type Page, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { humanPause, humanSelect, humanType, settle } from '../support/human';

// The photographed Baker family (src/admin/demo/people.json). Two families
// share that name; the head of household's email picks the one with photos.
const BAKER_EMAIL = 'andrew.baker@demo.churchcrm.io';

async function waitForFloatingButtonsToHide(page: Page): Promise<void> {
  await expect(page.locator('#fab-container .fab-button:visible')).toHaveCount(0, { timeout: 15000 });
}

/** Puts the Baker family in this session's cart and opens the Cart page. */
async function openCartWithBakerFamily(page: Page): Promise<void> {
  await page.goto('/people/family');
  const rows = page.locator('#families tbody tr');
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  await humanType(page.locator('.dt-search input'), BAKER_EMAIL);
  await humanPause(page, 400);
  const bakerRow = rows.filter({ hasText: BAKER_EMAIL }).first();
  await expect(bakerRow).toBeVisible({ timeout: 15000 });
  const href = await bakerRow.locator('td').first().locator('a').first().getAttribute('href');
  const familyId = href?.match(/\/people\/family\/(\d+)/)?.[1];
  if (!familyId) {
    throw new Error(`The Baker family link did not include an id (${href ?? 'no href'})`);
  }

  expect((await page.request.delete('/api/cart/')).ok()).toBeTruthy();
  expect((await page.request.post('/api/cart/', { data: { Family: Number(familyId) } })).ok()).toBeTruthy();

  await page.goto('/people/cart');
  await expect(page.locator('#cart-listing-table tbody tr').first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#cartLabels')).toBeVisible();
}

const cartFunctionsBar = defineCapture({
  name: 'cart-functions-bar',
  title: 'Cart Functions',
  category: 'People & Families',
  purpose: 'Show the Cart Functions bar: Empty, To Group, To Family, Check In, Map, Tags, Labels, Email and Directory',
  usedIn: ['docs'],
});

test('cart-functions-bar', cartFunctionsBar, async ({ page }, testInfo) => {
  await openCartWithBakerFamily(page);
  await waitForFloatingButtonsToHide(page);
  await settle(page, 800);
  await captureScreen(page, testInfo, cartFunctionsBar);
});

const cartGenerateLabels = defineCapture({
  name: 'cart-generate-labels',
  title: 'Generate Labels from the Cart',
  category: 'People & Families',
  purpose: 'Show the Generate Labels dialog opened from the Cart, with grouping, presort, label type, font, start row and column, and file type',
  usedIn: ['docs'],
});

test('cart-generate-labels', cartGenerateLabels, async ({ page }, testInfo) => {
  await openCartWithBakerFamily(page);
  await waitForFloatingButtonsToHide(page);
  await page.locator('#cartLabels').click();
  const dialog = page.locator('#labelsModal');
  await expect(dialog).toHaveClass(/show/, { timeout: 10000 });
  // The cart dialog offers grouping. The People Reports capture replaces that choice with its own intro.
  await expect(dialog.locator('input[name="groupbymode"]')).toHaveCount(2);
  await humanSelect(dialog.locator('#labeltype'), '5160');
  await settle(page, 800);
  await captureScreen(page, testInfo, cartGenerateLabels);
});
