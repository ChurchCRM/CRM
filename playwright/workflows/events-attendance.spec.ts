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
    // A church user checking people in to a real, seeded event.
    await page.goto('/event/checkin');

    const eventSelector = page.locator('#EventSelector');
    await expect(eventSelector.locator('option')).not.toHaveCount(1, { timeout: 15000 });
    await humanPause(page, 500);
    await humanSelect(eventSelector, { index: 1 });

    await page.waitForURL(/\/event\/checkin\/\d+/, { timeout: 15000 });
    await expect(page.getByText('Event:', { exact: false })).toBeVisible({ timeout: 10000 });
    await humanPause(page, 600);

    await captureScreen(page, testInfo, {
      name: 'events-attendance-overview',
      purpose: 'Show the event check-in / attendance workflow',
    });
  });
});
