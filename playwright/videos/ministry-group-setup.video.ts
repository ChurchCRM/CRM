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
  const groupId = page.url().match(/\/groups\/editor\/(\d+)/)?.[1];

  await expect(page.locator('#groupEditForm')).toBeVisible();
  await humanType(page.locator('textarea[name="Description"]'), 'A practical team for welcoming people at church.');
  await humanPause(page, 500);
  // A new group has only Member. Change Role opens a modal only when there
  // is more than one role, so add Leader before leaving the editor.
  await humanClick(page.locator('#addNewRoleBtn'));
  await expect(page.locator('#addRoleModal')).toBeVisible();
  await humanType(page.locator('#newRole'), 'Leader');
  await humanClick(page.locator('#submitNewRole'));
  await expect(page.locator('#addRoleModal')).toBeHidden();
  await humanClick(page.locator('#saveGroup'));
  // Save returns to the groups dashboard (GroupEditor.js), then the view shows the roster.
  await page.waitForURL(/\/groups\/dashboard/, { timeout: 15000 });
  await page.goto(`/groups/view/${groupId}`);
  await expect(page.locator('#membersTable')).toBeVisible();
  await settle(page, 1200);

  const addMember = async (name: string, role: string) => {
    const [first, last] = name.split(' ');
    // The search matches one field and returns at most 15 people. "Campbell"
    // fills that list, and Mathew Campbell is not in this demo set. A first
    // name stays inside the limit, then the option is matched on both names.
    const control = page.locator('#addGroupMember + .ts-wrapper .ts-control');
    await humanClick(control);
    await humanType(page.locator('#addGroupMember + .ts-wrapper input'), first);
    const option = page.locator('body > .ts-dropdown .option').filter({ hasText: first }).filter({ hasText: last }).first();
    await expect(option).toBeVisible({ timeout: 15000 });
    await humanClick(option);
    // Adding a person asks for a role once the group has more than one.
    await expect(page.locator('#groupViewModal')).toBeVisible({ timeout: 15000 });
    await page.locator('#gv-role-select').selectOption({ label: role });
    await humanClick(page.locator('#gvModalConfirmBtn'));
    await expect(page.locator('#groupViewModal')).toBeHidden({ timeout: 15000 });
    await expect(page.locator('#membersTable tbody tr').filter({ hasText: name })).toBeVisible({ timeout: 15000 });
    await settle(page, 800);
  };

  await addMember('Joseph Hall', 'Leader');
  await addMember('Christopher Hall', 'Member');

  const memberToRemove = page.locator('#membersTable tbody tr').filter({ hasText: 'Christopher Hall' });
  await humanClick(memberToRemove.locator('[data-bs-toggle="dropdown"]'));
  await humanClick(memberToRemove.locator('.remove-member-btn'));
  await expect(page.locator('.bootbox')).toBeVisible();
  await humanClick(page.locator('.bootbox .btn-danger'));
  await expect(page.locator('#membersTable tbody tr').filter({ hasText: 'Joseph Hall' })).toBeVisible();
  await expect(page.locator('#membersTable tbody tr').filter({ hasText: 'Christopher Hall' })).toHaveCount(0);
  await settle(page, 1200);

  await captureScreen(page, testInfo, {
    name: 'ministry-group-setup',
    title: 'Set Up a Ministry Group',
    category: 'Groups & Events',
    purpose: 'Show a church admin creating a ministry team, assigning a leader, adding a member, and keeping the roster current',
  });
});
