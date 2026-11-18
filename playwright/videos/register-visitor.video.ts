import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, humanType, settle } from '../support/human';

test('register-first-time-visitor', async ({ page }, testInfo) => {
  await page.goto('/PersonEditor.php');
  await humanPause(page, 1000);
  await humanType(page.locator('#FirstName'), 'Avery');
  await humanType(page.locator('#LastName'), 'Morgan');
  await humanType(page.locator('#Email'), 'avery.morgan@demo.churchcrm.io');
  await humanSelect(page.locator('#Classification'), { label: 'Guest' });
  await humanClick(page.locator('button[name="PersonSubmit"]'));
  await page.waitForURL(/\/people\/view\/\d+/);
  await expect(page.locator('h2')).toContainText('Avery Morgan');
  await expect(page.getByText('Guest', { exact: true }).first()).toBeVisible();
  await expect(page.locator('#person-deactivated')).toBeHidden();
  await humanPause(page, 1500);

  await humanClick(page.getByRole('button', { name: 'Add Note' }));
  const followUp = 'Welcome team: call Avery this week to thank them for visiting and invite them to Sunday coffee.';
  await humanType(page.locator('#noteText'), followUp);
  await humanClick(page.locator('#noteWhyCameSave'));
  const savedNote = page.locator('#timeline .note-preview').filter({ hasText: followUp });
  await expect(savedNote).toBeVisible();
  await savedNote.scrollIntoViewIfNeeded();
  await settle(page, 2500);

  await captureScreen(page, testInfo, {
    name: 'register-first-time-visitor',
    title: 'Welcome a First-Time Visitor',
    category: 'Recordings',
    purpose: 'Help a front-desk volunteer register a visitor and save a welcome-team follow-up note on their record',
  });
});
