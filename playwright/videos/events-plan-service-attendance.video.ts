import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, settle } from '../support/human';

/**
 * Staff-side Sunday School workflow: create today's class event from the
 * class page (which links the group automatically), then mark one member
 * present in the event's roster. Uses imported demo class 1.
 *
 * Desktop verification is intentionally deferred; this spec must not be
 * treated as a generated video until it has been run against a fresh demo DB.
 */
test('events-plan-service-attendance', async ({ page }, testInfo) => {
  await page.goto('/groups/sundayschool/class/1');
  const create = page.locator('#quickCreateTodaysEventBtn');
  await expect(create).toBeVisible({ timeout: 15000 });
  await humanPause(page, 1200);

  // The shipped class action creates a group-linked event and takes staff
  // directly to the roster check-in screen.
  await humanClick(create);
  await page.waitForURL(/\/event\/checkin\/\d+/, { timeout: 20000 });
  await expect(page.locator('#rosterCheckin')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#rosterGroupName')).not.toBeEmpty();
  const waiting = page.locator('#notCheckedInList .roster-member');
  await expect(waiting.first()).toBeVisible({ timeout: 15000 });
  await humanPause(page, 1800);

  const personId = await waiting.first().getAttribute('data-person-id');
  if (!personId) throw new Error('Demo roster has no person id to record attendance.');
  await humanClick(waiting.first().locator('.roster-action-btn[data-action="checkin"]'));
  await expect(page.locator(`#checkedInList .roster-member[data-person-id="${personId}"]`)).toBeVisible({
    timeout: 15000,
  });
  await settle(page, 2000);

  // The existing events-attendance-overview screenshot remains the result
  // shot; this is the staff click-through, not a duplicate screenshot spec.
  await captureScreen(page, testInfo, {
    name: 'events-plan-service-attendance',
    title: 'Plan a Class Event and Record Attendance',
    category: 'Recordings',
    purpose: 'A Sunday School coordinator creates a group-linked event and records a class member as present.',
  });
});
