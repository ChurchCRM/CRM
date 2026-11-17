import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';

test('find-person-by-name', async ({ page }, testInfo) => {
  await page.goto('/people/list');
  await expect(page.locator('#members tbody tr').first()).toBeVisible();
  await humanPause(page, 1000);
  await humanType(page.locator('.dt-search input'), 'Richard Scott');
  const person = page.locator('#members tbody tr').filter({ hasText: 'Richard Scott' });
  await expect(person).toHaveCount(1);
  await humanPause(page, 1000);
  await humanClick(person.locator('a').first());
  await page.waitForURL(/\/people\/view\/\d+/);
  await expect(page.locator('h2')).toContainText('Richard Scott');
  await expect(page.locator('#person-deactivated')).toBeHidden();
  await settle(page, 2000);

  await captureScreen(page, testInfo, {
    name: 'find-person-by-name',
    title: 'Find Someone in Seconds',
    category: 'Recordings',
    purpose: 'Help a front-desk volunteer find a person by name and open their record to welcome them',
  });
});
