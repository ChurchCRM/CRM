import { type Browser, expect, type Page, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { BASE_URL } from '../support/env';
import { humanClick, settle } from '../support/human';
import {
  ensureVolunteerSeed,
  memberPortalPage,
  removeVolunteerSeed,
  STORAGE_STATE_PATH,
  setVolunteerVersion,
  waitForFloatingButtonsToTuck,
} from '../support/volunteer';

async function openStaffing(page: Page, occurrenceId: number): Promise<void> {
  await page.goto(`/ministries/occurrences/${occurrenceId}`);
  await expect(page.locator('#requirements-content .volunteer-requirement').first()).toBeVisible({ timeout: 30000 });
}


/** Paul Mitchell, a Hospitality volunteer, gets a staff login so he can coordinate the ministry. */
const COORDINATOR_PASSWORD = 'Coordinator!2026';
let coordinatorUserId = 0;

async function openUserEditor(page: Page, personId: number): Promise<void> {
  await page.goto(`/admin/system/users/${personId}/edit`);
  await expect(page.locator('#customPermissions')).toBeVisible({ timeout: 30000 });
}

/**
 * Creates Paul's login with only Manage My Ministries, sets a password he can sign in
 * with, and makes him coordinator of the seeded ministry.
 */
async function ensureCoordinator(page: Page): Promise<number> {
  const { ministryId, personIds } = await ensureVolunteerSeed(page);
  const personId = personIds.paul;
  if (coordinatorUserId === personId) {
    return personId;
  }

  await page.goto(`/admin/system/users/new?personId=${personId}`);
  await expect(page.locator('#UserName')).toBeVisible({ timeout: 30000 });
  await page.locator('input[name="accessMode"][value="custom"]').check({ force: true });
  await expect(page.locator('#customPermissions')).toBeVisible();
  await page.locator('#ManageMyMinistries').check({ force: true });
  await humanClick(page.locator('#SaveButton'));
  await page.waitForURL(/\/admin\/system\/users\/?$/, { timeout: 30000 });
  coordinatorUserId = personId;

  await page.goto(`/admin/system/user/${personId}/changePassword`);
  await page.locator('#NewPassword1').fill(COORDINATOR_PASSWORD);
  await page.locator('#NewPassword2').fill(COORDINATOR_PASSWORD);
  await page.locator('input[type="submit"][name="Submit"]').click();
  await page.waitForLoadState('load');

  const scope = await page.request.post('/api/ministries/scopes', {
    data: { personId, scopeType: 'ministry', scopeId: ministryId },
  });
  if (!scope.ok()) {
    throw new Error(`Coordinator scope failed: ${scope.status()} ${await scope.text()}`);
  }
  return personId;
}

async function removeCoordinator(browser: Browser): Promise<void> {
  if (!coordinatorUserId) {
    return;
  }
  const context = await browser.newContext({ baseURL: BASE_URL, storageState: STORAGE_STATE_PATH });
  try {
    await context.request.delete(`/admin/api/user/${coordinatorUserId}/`);
  } finally {
    coordinatorUserId = 0;
    await context.close();
  }
}

test.describe.configure({ mode: 'serial' });

test.describe('Volunteer Management v2', () => {
  test.afterAll(async ({ browser }) => {
    await removeCoordinator(browser);
    await removeVolunteerSeed(browser);
  });

  test('volunteer-ministry-settings', async ({ page }, testInfo) => {
    // Captured before the seed, so the delivery card has no queued messages to report.
    await setVolunteerVersion(page, 'v2');
    await page.goto('/admin/ministry-settings');
    await expect(page.locator('#ministrySettingsPanel')).toBeVisible({ timeout: 30000 });
    await expect(page.locator('#ministry-delivery-card')).toBeVisible();
    await waitForFloatingButtonsToTuck(page);
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'volunteer-ministry-settings',
      title: 'Volunteer Management Settings',
      category: 'Admin & Settings',
      purpose: 'Show the Ministry Settings page: reminder and alert timing, the scheduling horizon and the background-job and delivery status.',
      usedIn: ['docs'],
    });
  });

  test('volunteer-ministry-dashboard', async ({ page }, testInfo) => {
    await ensureVolunteerSeed(page);
    await page.goto('/ministries/dashboard');
    await expect(page.locator('#volunteer-gaps-content')).toBeVisible({ timeout: 30000 });
    await expect(page.locator('#volunteer-pending-content')).toBeVisible();
    await expect(page.locator('#volunteer-upcoming-content')).toBeVisible();
    await waitForFloatingButtonsToTuck(page);
    await settle(page, 1000);

    await captureScreen(page, testInfo, {
      name: 'volunteer-ministry-dashboard',
      title: 'Ministry Dashboard',
      category: 'Dashboards',
      purpose: 'Show the Ministry Dashboard: open spots to fill, replies still owed and upcoming staffed services.',
      usedIn: ['docs'],
    });
  });

  test('volunteer-ministry-volunteers', async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    await page.goto(`/ministries/${ministryId}`);
    await humanClick(page.locator('#nav-item-volunteers'));
    await expect(page.locator('#volunteerQualificationsTable tbody tr').first()).toBeVisible({ timeout: 30000 });
    await waitForFloatingButtonsToTuck(page);
    await settle(page, 1000);

    await captureScreen(page, testInfo, {
      name: 'volunteer-ministry-volunteers',
      title: 'Ministry Volunteers & Qualifications',
      category: 'Groups & Events',
      purpose: 'Show a ministry page with its volunteers and the positions each one is qualified for.',
      usedIn: ['docs'],
    });
  });

  const volunteerOccurrenceStaffingCapture = defineCapture({
    name: 'volunteer-occurrence-staffing',
    locales: 'all',
    title: 'Sunday Staffing at a Glance',
    category: 'Groups & Events',
    purpose: 'Show one Sunday staffed position by position, in plain words: Full, Covered, Needs 2 more.',
    usedIn: ['docs'],
  });
  test('volunteer-occurrence-staffing', volunteerOccurrenceStaffingCapture, async ({ page }, testInfo) => {
    const { coffeeOccurrenceId } = await ensureVolunteerSeed(page);
    await openStaffing(page, coffeeOccurrenceId);
    await waitForFloatingButtonsToTuck(page);
    await settle(page, 1000);

    await captureScreen(page, testInfo, volunteerOccurrenceStaffingCapture);
  });

  const volunteerAssignPickerCapture = defineCapture({
    name: 'volunteer-assign-picker',
    locales: 'all',
    title: 'Assign a Qualified Volunteer',
    category: 'Groups & Events',
    purpose: 'Show the volunteer picker, which offers only qualified people and who served least recently.',
    usedIn: ['docs'],
  });
  test('volunteer-assign-picker', volunteerAssignPickerCapture, async ({ page }, testInfo) => {
    const { coffeeOccurrenceId, positionIds } = await ensureVolunteerSeed(page);
    await openStaffing(page, coffeeOccurrenceId);
    await waitForFloatingButtonsToTuck(page);
    await humanClick(page.locator(`.volunteer-assign-btn[data-position-id="${positionIds.cleanup}"]`));
    const control = page.locator('#volunteer-assign-modal .ts-control');
    await expect(control).toBeVisible({ timeout: 30000 });
    await settle(page, 500);
    // The picker can open on its own as the modal finishes showing; a click would close it.
    const isOpen = await page.evaluate(
      () =>
        (document.getElementById('assign-person-select') as { tomselect?: { isOpen: boolean } } | null)?.tomselect
          ?.isOpen
    );
    if (!isOpen) {
      await humanClick(control);
    }
    await expect(page.locator('body > .ts-dropdown .option').first()).toBeVisible();
    await settle(page, 800);

    await captureScreen(page, testInfo, volunteerAssignPickerCapture);
  });

  const volunteerPortalScheduleCapture = defineCapture({
    name: 'volunteer-portal-schedule',
    locales: 'all',
    title: 'Member Portal: My Volunteer Schedule',
    category: 'Groups & Events',
    purpose: 'Show a volunteer answering a request in the Member Portal: accept, decline or find a sub.',
    usedIn: ['docs'],
  });
  test('volunteer-portal-schedule', volunteerPortalScheduleCapture, async ({ page, browser }, testInfo) => {
    const member = await memberPortalPage(page, browser);
    await member.goto('/portal/volunteer/schedule');
    await expect(member.locator('.volunteer-assignment-card').first()).toBeVisible({ timeout: 30000 });
    await settle(member, 1000);

    await captureScreen(member, testInfo, volunteerPortalScheduleCapture);
    await member.context().close();
  });

  const volunteerPortalOpportunitiesCapture = defineCapture({
    name: 'volunteer-portal-opportunities',
    locales: 'all',
    title: 'Member Portal: Volunteer Sign-Up',
    category: 'Groups & Events',
    purpose: 'Show a volunteer finding open spots they are qualified for and signing up in the Member Portal.',
    usedIn: ['docs'],
  });
  test('volunteer-portal-opportunities', volunteerPortalOpportunitiesCapture, async ({ page, browser }, testInfo) => {
    const member = await memberPortalPage(page, browser);
    await member.goto('/portal/volunteer/opportunities');
    await expect(member.locator('.volunteer-opportunity-card').first()).toBeVisible({ timeout: 30000 });
    await expect(member.locator('.volunteer-help-wanted-card').first()).toBeVisible();
    await settle(member, 1500);

    await captureScreen(member, testInfo, volunteerPortalOpportunitiesCapture);
    await member.context().close();
  });
  const personTabCapture = defineCapture({
    name: 'volunteer-person-tab',
    title: 'Person Volunteer Tab',
    category: 'Groups & Events',
    purpose: 'Show the Volunteer tab on a person record: the positions they are qualified for and the dates they serve next.',
    usedIn: ['docs'],
  });
  test('volunteer-person-tab', personTabCapture, async ({ page }, testInfo) => {
    const { personIds } = await ensureVolunteerSeed(page);
    await page.goto(`/people/view/${personIds.sarah}`);
    await humanClick(page.locator('#nav-item-volunteer-v2'));
    await expect(page.locator('#volunteer-v2 #person-volunteer-v2-qualifications')).toBeVisible({ timeout: 30000 });
    await expect(page.locator('#volunteer-v2 #person-volunteer-v2-assignments')).toBeVisible();
    await waitForFloatingButtonsToTuck(page);
    await page.locator('#nav-item-volunteer-v2').evaluate((el) => {
      const bar = document.querySelector('header.navbar.sticky-top')?.getBoundingClientRect().height ?? 0;
      window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - bar - 24, behavior: 'instant' });
    });
    await settle(page, 800);

    await captureScreen(page, testInfo, personTabCapture);
  });

  const userEditorCapture = defineCapture({
    name: 'admin-user-editor-ministry-permissions',
    title: 'Ministry Permissions in the User Editor',
    category: 'Admin & Settings',
    purpose: 'Show the Manage Ministries and Manage My Ministries switches in the user editor, with a coordinator given Manage My Ministries.',
    usedIn: ['docs'],
  });
  test('admin-user-editor-ministry-permissions', userEditorCapture, async ({ page }, testInfo) => {
    const personId = await ensureCoordinator(page);
    await openUserEditor(page, personId);
    await expect(page.locator('#ManageMyMinistries')).toBeChecked();
    await expect(page.locator('#ManageMinistries')).not.toBeChecked();
    await waitForFloatingButtonsToTuck(page);
    await page.locator('#customPermissions').evaluate((el) => {
      const bar = document.querySelector('header.navbar.sticky-top')?.getBoundingClientRect().height ?? 0;
      const box = el.getBoundingClientRect();
      const bottom = box.bottom + window.scrollY + 80;
      window.scrollTo({ top: Math.max(0, bottom - window.innerHeight), behavior: 'instant' });
      if (box.top - bar < 0) window.scrollTo({ top: box.top + window.scrollY - bar - 16, behavior: 'instant' });
    });
    await settle(page, 800);

    await captureScreen(page, testInfo, userEditorCapture);
  });

  const coordinatorDashboardCapture = defineCapture({
    name: 'volunteer-ministry-dashboard-coordinator',
    title: 'Ministry Dashboard for a Coordinator',
    category: 'Dashboards',
    purpose: 'Show the Ministry Dashboard as a ministry coordinator sees it: only their ministry, and no New ministry button.',
    usedIn: ['docs'],
  });
  test('volunteer-ministry-dashboard-coordinator', coordinatorDashboardCapture, async ({ page, browser }, testInfo) => {
    await ensureCoordinator(page);
    // A context of its own, so the admin session every later workflow shares stays signed in.
    const context = await browser.newContext({ baseURL: BASE_URL, viewport: page.viewportSize() ?? undefined });
    try {
      const coordinator = await context.newPage();
      await coordinator.goto('/session/begin');
      await coordinator.locator('input[name=User]').fill('paul.mitchell@demo.churchcrm.io');
      await coordinator.locator('input[name=Password]').fill(COORDINATOR_PASSWORD);
      await coordinator.locator('input[name=Password]').press('Enter');
      await expect(coordinator.locator('input[name=Password]')).toHaveCount(0, { timeout: 30000 });

      await coordinator.goto('/ministries/dashboard');
      await expect(coordinator.locator('#volunteer-gaps-content')).toBeVisible({ timeout: 30000 });
      await expect(coordinator.locator('#volunteer-upcoming-content')).toBeVisible();
      await expect(coordinator.locator('a, button', { hasText: 'New ministry' })).toHaveCount(0);
      await waitForFloatingButtonsToTuck(coordinator);
      await settle(coordinator, 1000);

      await captureScreen(coordinator, testInfo, coordinatorDashboardCapture);
    } finally {
      await context.close();
    }
  });
});
