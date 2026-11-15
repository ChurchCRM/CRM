import { type Browser, expect, type Locator, type Page, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { BASE_URL } from '../support/env';
import { humanClick, humanType, settle } from '../support/human';
import {
  createUnstaffedEvent,
  deleteMinistryEventsTitled,
  ensureVolunteerSeed,
  findDemoTeam,
  isoDate,
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

type MinistryTab = 'overview' | 'positions' | 'schedules' | 'occurrences' | 'calendar';

/** The ministry page on one of its tabs, once that tab has something to show. */
async function openMinistry(page: Page, ministryId: number, tab: MinistryTab): Promise<void> {
  await page.goto(`/ministries/${ministryId}`);
  await expect(page.locator('#overview-content')).toBeVisible({ timeout: 30000 });
  if (tab !== 'overview') {
    await humanClick(page.locator(`#nav-item-${tab}`));
  }
  const table = {
    overview: '#volunteerTeamsTable',
    positions: '#volunteerPositionsTable',
    schedules: '#volunteerSchedulesTable',
    occurrences: '#volunteerOccurrencesTable',
    calendar: '#volunteerMinistryEventsTable',
  }[tab];
  await expect(page.locator(`${table} tbody tr`).first()).toBeVisible({ timeout: 30000 });
  await waitForFloatingButtonsToTuck(page);
}

/** Opens a row's action menu (the ⋮ button) and picks one of its items. */
async function pickRowAction(row: Locator, item: string): Promise<void> {
  await humanClick(row.locator('[data-bs-toggle="dropdown"]'));
  await humanClick(row.locator(item));
}

/** Paul Mitchell, a Hospitality volunteer, gets a staff login so he can coordinate the ministry. */
const COORDINATOR_PASSWORD = 'Coordinator!2026';
let coordinatorUserId = 0;


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

  const volunteerMinistryOverviewCapture = defineCapture({
    name: 'volunteer-ministry-overview',
    locales: 'all',
    title: 'Ministry Overview',
    category: 'Groups & Events',
    purpose: 'Show a ministry at a glance: its teams, the volunteers who serve and the spots still open.',
    usedIn: ['docs'],
  });
  test('volunteer-ministry-overview', volunteerMinistryOverviewCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    await openMinistry(page, ministryId, 'overview');
    await settle(page, 1000);

    await captureScreen(page, testInfo, volunteerMinistryOverviewCapture);
  });

  const volunteerTeamSundaySchoolClassCapture = defineCapture({
    name: 'volunteer-team-sunday-school-class',
    locales: 'all',
    title: 'Team Linked to a Sunday School Class',
    category: 'Groups & Events',
    purpose: 'Show a team linked to a Sunday School class, so the people qualified on the team become the class teachers.',
    usedIn: ['docs'],
  });
  test('volunteer-team-sunday-school-class', volunteerTeamSundaySchoolClassCapture, async ({ page }, testInfo) => {
    await ensureVolunteerSeed(page);
    const { ministryId, teamId, classGroupId } = await findDemoTeam(page.request, "Children's Ministry", 'Early Elementary');
    expect(classGroupId, 'the demo team is linked to a class').not.toBeNull();
    await openMinistry(page, ministryId, 'overview');
    await pickRowAction(page.locator(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"]`), '.volunteer-team-edit');
    await expect(page.locator('#teamModal')).toBeVisible();
    await expect(page.locator('#team-form-class')).toHaveValue(String(classGroupId), { timeout: 15000 });
    await settle(page, 800);

    await captureScreen(page, testInfo, volunteerTeamSundaySchoolClassCapture);
  });

  const volunteerMinistryCalendarCapture = defineCapture({
    name: 'volunteer-ministry-calendar',
    locales: 'all',
    title: 'Ministry Calendar',
    category: 'Groups & Events',
    purpose: 'Show the events a ministry has on its own calendar, each with how well its teams are staffed.',
    usedIn: ['docs'],
  });
  test('volunteer-ministry-calendar', volunteerMinistryCalendarCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    await openMinistry(page, ministryId, 'calendar');
    await settle(page, 1000);

    await captureScreen(page, testInfo, volunteerMinistryCalendarCapture);
  });

  /** Opens "New recurring event" on the Calendar tab and fills in a short weekly series. */
  async function fillWeeklySeries(page: Page, ministryId: number, title: string): Promise<void> {
    await openMinistry(page, ministryId, 'calendar');
    await humanClick(page.locator('#ministry-event-add-series-btn'));
    await expect(page.locator('#ministryEventModal')).toBeVisible();
    await expect(page.locator('#ministry-event-form-series')).toBeChecked();
    const type = page.locator('#ministry-event-form-type');
    await expect(type.locator('option')).not.toHaveCount(1, { timeout: 15000 });
    await humanType(page.locator('#ministry-event-form-title'), title);
    if ((await type.inputValue()) === '') {
      await type.selectOption({ index: 1 });
    }
    await page.locator('#ministry-event-form-dow').selectOption('Wednesday');
    await page.locator('#ministry-event-form-range-end').fill(isoDate(35));
    await page.locator('#ministry-event-form-start-time').fill('19:00');
    await page.locator('#ministry-event-form-end-time').fill('20:00');
    await page.locator('#ministry-event-form-end-time').blur();
    await expect(page.locator('#ministry-event-form-preview')).toBeVisible();
  }

  const WEEKLY_SERIES_TITLE = 'Wednesday Prayer Circle';

  const volunteerEventStaffPromptCapture = defineCapture({
    name: 'volunteer-event-staff-prompt',
    locales: 'all',
    title: 'Staff the New Events Now?',
    category: 'Groups & Events',
    purpose: 'Show the question that follows a new recurring event: staff its dates now, or leave that for later.',
    usedIn: ['docs'],
  });
  test('volunteer-event-staff-prompt', volunteerEventStaffPromptCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    try {
      await fillWeeklySeries(page, ministryId, WEEKLY_SERIES_TITLE);
      await humanClick(page.locator('#ministry-event-form-save'));
      const prompt = page.locator('.ministry-event-staff-prompt');
      await expect(prompt).toBeVisible({ timeout: 30000 });
      await expect(prompt.locator('.bootbox-accept')).toBeVisible();
      await settle(page, 800);

      await captureScreen(page, testInfo, volunteerEventStaffPromptCapture);
      await page.setViewportSize({ width: 1440, height: 900 });
      await humanClick(prompt.locator('.bootbox-cancel'));
      await expect(prompt).toBeHidden();
    } finally {
      await deleteMinistryEventsTitled(page, ministryId, WEEKLY_SERIES_TITLE);
    }
  });

  const volunteerEventStaffCapture = defineCapture({
    name: 'volunteer-event-staff',
    locales: 'all',
    title: 'Staff an Event',
    category: 'Groups & Events',
    purpose: 'Show a coordinator staffing a calendar event: the team and the positions it needs filled.',
    usedIn: ['docs'],
  });
  test('volunteer-event-staff', volunteerEventStaffCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    const title = 'Volunteer Orientation Evening';
    const eventId = await createUnstaffedEvent(page, ministryId, title, 9);
    try {
      await openMinistry(page, ministryId, 'calendar');
      const row = page.locator('#volunteerMinistryEventsTable tbody tr').filter({ hasText: title });
      await pickRowAction(row, '.ministry-event-staff');
      await expect(page.locator('#staffEventModal')).toBeVisible();
      await expect(page.locator('#staff-event-form-event')).toHaveValue(String(eventId), { timeout: 15000 });
      await expect(page.locator('#staff-event-form-needs .volunteer-need-row').first()).toBeVisible();
      await settle(page, 800);

      await captureScreen(page, testInfo, volunteerEventStaffCapture);
    } finally {
      await deleteMinistryEventsTitled(page, ministryId, title);
    }
  });

  const volunteerMinistrySchedulesCapture = defineCapture({
    name: 'volunteer-ministry-schedules',
    locales: 'all',
    title: 'Ministry Schedules',
    category: 'Groups & Events',
    purpose: 'Show the schedules that say which events a team staffs and how many people each position needs.',
    usedIn: ['docs'],
  });
  test('volunteer-ministry-schedules', volunteerMinistrySchedulesCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    await openMinistry(page, ministryId, 'schedules');
    await settle(page, 1000);

    await captureScreen(page, testInfo, volunteerMinistrySchedulesCapture);
  });

  const volunteerOccurrencesGenerateCapture = defineCapture({
    name: 'volunteer-occurrences-generate',
    locales: 'all',
    title: 'Generate Occurrences',
    category: 'Groups & Events',
    purpose: 'Show a coordinator making the dated occurrences of a schedule, with the default volunteers it assigns.',
    usedIn: ['docs'],
  });
  test('volunteer-occurrences-generate', volunteerOccurrencesGenerateCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    await openMinistry(page, ministryId, 'schedules');
    const row = page.locator('#volunteerSchedulesTable tbody tr').filter({ hasText: 'Coffee Bar' }).first();
    await expect(row).toBeVisible();
    await pickRowAction(row, '.volunteer-schedule-generate');
    const modal = page.locator('#generateOccurrencesModal');
    await expect(modal).toBeVisible();
    await expect(page.locator('#generate-form-loading')).toBeHidden({ timeout: 15000 });
    await expect(page.locator('#generate-form-rows .generate-default-row').first()).toBeVisible({ timeout: 15000 });
    await settle(page, 800);

    await captureScreen(page, testInfo, volunteerOccurrencesGenerateCapture);
    await page.setViewportSize({ width: 1440, height: 900 });
    await humanClick(modal.locator('.btn-close'));
    await expect(modal).toBeHidden();
  });

  const volunteerMinistryOccurrencesCapture = defineCapture({
    name: 'volunteer-ministry-occurrences',
    locales: 'all',
    title: 'Ministry Occurrences',
    category: 'Groups & Events',
    purpose: 'Show every dated occurrence of a ministry with who is signed up and what is still open.',
    usedIn: ['docs'],
  });
  test('volunteer-ministry-occurrences', volunteerMinistryOccurrencesCapture, async ({ page }, testInfo) => {
    const { ministryId } = await ensureVolunteerSeed(page);
    await openMinistry(page, ministryId, 'occurrences');
    await settle(page, 1000);

    await captureScreen(page, testInfo, volunteerMinistryOccurrencesCapture);
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
    // Empty storage state: a new context would otherwise inherit the admin sign-in from the config.
    const context = await browser.newContext({
      baseURL: BASE_URL,
      viewport: page.viewportSize() ?? undefined,
      storageState: { cookies: [], origins: [] },
    });
    try {
      const coordinator = await context.newPage();
      await coordinator.goto('/session/begin');
      await expect(coordinator.locator('input[name=User]')).toBeVisible({ timeout: 30000 });
      await coordinator.locator('input[name=User]').fill('paul.mitchell@demo.churchcrm.io');
      await coordinator.locator('input[name=Password]').fill(COORDINATOR_PASSWORD);
      await coordinator.locator('input[name=Password]').press('Enter');
      await expect(coordinator.locator('input[name=Password]')).toHaveCount(0, { timeout: 30000 });

      await coordinator.goto('/ministries/dashboard');
      await expect(coordinator.locator('#volunteer-gaps-content')).toBeVisible({ timeout: 30000 });
      await expect(coordinator.locator('#volunteer-upcoming-content')).toBeVisible();
      await expect(coordinator.locator('header').getByText('Paul Mitchell').first()).toBeVisible();
      await expect(coordinator.locator('a, button', { hasText: 'New ministry' })).toHaveCount(0);
      await waitForFloatingButtonsToTuck(coordinator);
      await settle(coordinator, 1000);

      await captureScreen(coordinator, testInfo, coordinatorDashboardCapture);
    } finally {
      await context.close();
    }
  });
});
