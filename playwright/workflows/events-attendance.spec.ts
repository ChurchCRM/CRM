import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanPause, humanSelect } from '../support/human';

test.describe('Events & Attendance', () => {
  test('events-calendar-overview', async ({ page }, testInfo) => {
    // A church user checking the event calendar.
    await page.goto('/event/calendars');
    await expect(page.getByRole('grid')).toBeVisible({ timeout: 20000 });
    await humanPause(page, 800);

    await captureScreen(page, testInfo, {
      name: 'events-calendar-overview',
      purpose: 'Show the event calendar',
    });
  });

  test('events-attendance-overview', async ({ page }, testInfo) => {
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
    await expect(page.getByText('Event:', { exact: false })).toBeVisible({ timeout: 10000 });
    await humanPause(page, 600);

    await captureScreen(page, testInfo, {
      name: 'events-attendance-overview',
      purpose: 'Show the event check-in / attendance workflow',
    });
  });
});
