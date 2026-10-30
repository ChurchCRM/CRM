import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';

test('ministry-group-setup', async ({ page }, testInfo) => {
  const groupName = 'Ministry Welcome Team';

  await page.goto('/groups/dashboard');
  await expect(page.locator('#groupName')).toBeVisible();
  await humanType(page.locator('#groupName'), groupName);
  await humanPause(page, 700);
  await humanClick(page.locator('#addNewGroup'));
  await page.waitForURL(/\/groups\/editor\/\d+/, { timeout: 15000 });

  await expect(page.locator('#groupEditForm')).toBeVisible();
  await humanType(page.locator('textarea[name="Description"]'), 'A practical team for welcoming people at church.');
  await humanPause(page, 500);
  await humanClick(page.locator('#saveGroup'));
  await page.waitForURL(/\/groups\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('#membersTable')).toBeVisible();
  await settle(page, 1200);

  const addMember = async (name: string) => {
    const control = page.locator('#addGroupMember').next('.ts-wrapper').locator('.ts-control');
    await humanClick(control);
    await humanType(page.locator('#addGroupMember + .ts-wrapper input'), name);
    await expect(page.locator('.ts-dropdown .option').filter({ hasText: name }).first()).toBeVisible();
    await humanClick(page.locator('.ts-dropdown .option').filter({ hasText: name }).first());
    await settle(page, 800);
  };

  await addMember('Mathew Campbell');
  await expect(page.locator('#membersTable tbody tr')).toHaveCount(1);
  const firstRow = page.locator('#membersTable tbody tr').first();
  await humanClick(firstRow.locator('[data-bs-toggle="dropdown"]'));
  await humanClick(firstRow.locator('.changeMembership'));
  await expect(page.locator('#groupViewModal')).toBeVisible();
  await page.locator('#gv-role-select').selectOption({ label: 'Leader' });
  await humanClick(page.locator('#gvModalConfirmBtn'));
  await settle(page, 1000);

  await addMember('Joseph Hall');
  await expect(page.locator('#membersTable tbody tr')).toHaveCount(2);
  await settle(page, 700);

  const memberToRemove = page.locator('#membersTable tbody tr').filter({ hasText: 'Joseph Hall' });
  await humanClick(memberToRemove.locator('[data-bs-toggle="dropdown"]'));
  await humanClick(memberToRemove.locator('.remove-member-btn'));
  await expect(page.locator('.bootbox')).toBeVisible();
  await humanClick(page.locator('.bootbox .btn-danger'));
  await expect(page.locator('#membersTable tbody tr')).toHaveCount(1);
  await settle(page, 1200);

  await captureScreen(page, testInfo, {
    name: 'ministry-group-setup',
    title: 'Set Up a Ministry Group',
    category: 'Groups & Events',
    purpose: 'Show a church admin creating a ministry team, assigning a leader, adding a member, and keeping the roster current',
  });
});
