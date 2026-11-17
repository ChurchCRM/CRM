import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';

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

  await humanClick(page.locator('a.btn[href*="map/neighbors"]').first());
  await page.waitForURL(/\/people\/map\/neighbors/, { timeout: 15000 });
  await expect(page.locator('#neighborsMap')).toBeVisible({ timeout: 15000 });

  await expect(page.locator('#neighborsTable')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#neighborsMap .leaflet-tile').first()).toBeVisible();
  await expect(page.locator('#neighborsMap .leaflet-tile:not(.leaflet-tile-loaded)')).toHaveCount(0, {
    timeout: 20000,
  });
  await settle(page, 2500);

  await expect(page.locator('#neighborsTableBody tr').first()).toBeVisible();
  const addToCart = page.waitForResponse(
    (response) => response.url().endsWith('/api/cart/') && response.request().method() === 'POST'
  );
  await humanClick(page.locator('#addAllToCart'));
  const response = await addToCart;
  expect(response.ok()).toBeTruthy();
  const result = await response.json();
  const people = response.request().postDataJSON().Persons;
  expect(people.length).toBeGreaterThan(0);
  expect([...result.added, ...result.duplicate].sort()).toEqual([...people].sort());
  await humanPause(page, 1500);
  await humanClick(page.locator('a[data-bs-toggle="dropdown"]').filter({ has: page.locator('.fa-cart-shopping') }));
  await humanClick(page.locator('#cart-dropdown-menu a[href$="/people/cart"]'));
  await page.waitForURL(/\/people\/cart$/);
  await expect(page.locator('#cart-listing-table tbody tr').first()).toBeVisible();
  await settle(page, 2000);

  await captureScreen(page, testInfo, {
    name: 'find-neighbors-search',
    title: 'Plan a Visit to Nearby Families',
    category: 'Recordings',
    purpose: 'Plan a visit by finding nearby families from a member’s family profile and adding the neighbors to the cart',
  });
});
