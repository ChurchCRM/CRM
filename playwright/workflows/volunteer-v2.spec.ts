import { expect, type Page, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { humanClick, settle } from '../support/human';
import {
  ensureVolunteerSeed,
  memberPortalPage,
  removeVolunteerSeed,
  setVolunteerVersion,
  waitForFloatingButtonsToTuck,
} from '../support/volunteer';

async function openStaffing(page: Page, occurrenceId: number): Promise<void> {
  await page.goto(`/ministries/occurrences/${occurrenceId}`);
  await expect(page.locator('#requirements-content .volunteer-requirement').first()).toBeVisible({ timeout: 30000 });
}

test.describe.configure({ mode: 'serial' });

test.describe('Volunteer Management v2', () => {
  test.afterAll(async ({ browser }) => {
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
});
