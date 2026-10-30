import { expect, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { humanPause, humanSelect, settle } from '../support/human';

test.describe('Events & Attendance', () => {
  test('events-calendar-overview', async ({ page }, testInfo) => {
    // A church user checking the event calendar.
    await page.goto('/event/calendars');
    // FullCalendar v7 hashes its class names, sets no grid role and translates its aria-labels.
    await expect(page.locator('#calendar button').first()).toBeVisible({ timeout: 20000 });
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'events-calendar-overview',
      title: 'Events Calendar',
      category: 'Groups & Events',
      purpose: 'Show the event calendar',
      usedIn: ['docs'],
    });
  });

  const eventsAttendanceOverviewCapture = defineCapture({
    name: 'events-attendance-overview',
    locales: 'all',
    title: 'Check-In & Attendance',
    category: 'Groups & Events',
    purpose: 'Show the event check-in / attendance workflow',
    usedIn: ['website'],
  });
  test('events-attendance-overview', eventsAttendanceOverviewCapture, async ({ page }, testInfo) => {
    // A church user reviewing attendance already recorded for a past event.
    // #EventSelector is sorted newest-first (see event/routes/checkin.php),
    // so index 1 is the furthest-future event — one with no check-ins yet,
    // which renders an empty "No data available" table. Demo data only
    // seeds real EventAttend rows for past events (see attended_fraction in
    // src/admin/demo/events.csv, applied by DemoDataService::seedEventAttendance),
    // so pick "Sunday Worship Service" specifically — a past, recurring
    // event guaranteed to have attendance recorded.
    await page.goto('/event/checkin');

    const eventSelector = page.locator('#EventSelector');
    await expect(eventSelector.locator('option')).not.toHaveCount(1, { timeout: 15000 });
    await humanPause(page, 500);

    const eventValue = await eventSelector
      .locator('option', { hasText: 'Sunday Worship Service' })
      .first()
      .getAttribute('value');
    if (!eventValue) {
      throw new Error('"Sunday Worship Service" not found in the demo data\'s event list.');
    }
    await humanSelect(eventSelector, { value: eventValue });

    await page.waitForURL(/\/event\/checkin\/\d+/, { timeout: 15000 });
    await expect(page.locator('.card', { has: page.locator('.fa-calendar-check') })).toBeVisible({ timeout: 10000 });
    await settle(page, 600);

    await captureScreen(page, testInfo, eventsAttendanceOverviewCapture);
  });
});
