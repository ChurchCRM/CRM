import { expect, test, type Page } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';
import { type KioskDemo, KIOSK_GUEST, setUpKioskDemo } from '../support/kiosk-guest';

/**
 * The full Sunday-morning kiosk story on a registered Sunday School device,
 * as one continuous recording for the website's check-in workflow page:
 *
 *  1. The kiosk opens on today's event, church branding on the device screen.
 *  2. A walk-in guest family registers and lands in Checked In with a Guest badge.
 *  3. A parent checks their child in, choosing who brought them in the
 *     Check-in By modal (guardian photos, or initials fallback) — the 7.8.1
 *     attribution flow; the selection is recorded on the attendance row.
 *  4. Both children sit in Checked In.
 *  5. The parent checks the child back out, choosing who is collecting them.
 *  6. Closing scene: the same morning on the staff side — the event's
 *     roster check-in / attendance page, with the guest's check-in on record.
 *
 * This replaces the earlier cut, which raced check-in -> checkout in about
 * seven seconds and showed only a slice of the kiosk workflow.
 *
 * Requires demo group "Class 1-3" with a family-linked child. The kiosk
 * fixture registers a device and creates an active linked event.
 */
let demo: KioskDemo;

test.beforeAll(async ({ browser }) => {
  demo = await setUpKioskDemo(browser);
});

test.afterAll(async () => {
  await demo?.tearDown();
});

/**
 * The Check-in By modal only appears when attribution is enabled for the
 * device, so wait briefly for it instead of assuming. When it shows, hold
 * on the guardian choices (photos visible) before picking the first one.
 */
async function chooseCheckinBy(page: Page): Promise<void> {
  const modal = page.locator('#checkinByModal');
  const shown = await modal
    .waitFor({ state: 'visible', timeout: 5000 })
    .then(() => true)
    .catch(() => false);
  if (!shown) {
    return;
  }
  const guardians = modal.locator('.checkinByMemberBtn');
  await expect(guardians.first()).toBeVisible({ timeout: 15000 });
  await expect(guardians.first().locator('img.checkin-by-photo')).toBeVisible();
  await humanPause(page, 1800);
  await humanClick(guardians.first());
  await expect(modal).toBeHidden({ timeout: 15000 });
}

test('kiosk-child-checkin-checkout', async ({ page, context }, testInfo) => {
  try {
    await demo.attachKiosk(context);
    await page.goto('/kiosk/');

    // Scene 1 — the kiosk opens on today's event, branded for this church.
    await expect(page.locator('#eventTitle')).not.toBeEmpty({ timeout: 15000 });
    await expect(page.locator('.kiosk-church-name')).not.toBeEmpty();
    const waiting = page.locator('#notCheckedInList .kiosk-member[data-familyId]');
    await expect(waiting.first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 3000);

    // Scene 2 — a walk-in guest family registers and is checked in at once,
    // Guest badge showing (same form as kiosk-guest-register, kept here so
    // this recording tells the whole morning on its own).
    await humanClick(page.locator('#registerGuestBtn'));
    const guestModal = page.locator('#guestRegistrationModal');
    await expect(guestModal).toBeVisible();
    await humanPause(page, 800);
    await humanType(guestModal.locator('#guestFirstName'), KIOSK_GUEST.firstName);
    await humanType(guestModal.locator('#guestLastName'), KIOSK_GUEST.lastName);
    await humanType(guestModal.locator('#guestPhone'), KIOSK_GUEST.phone);
    await humanType(guestModal.locator('#guestEmail'), KIOSK_GUEST.email);
    await humanPause(page, 1200);
    await humanClick(guestModal.locator('#guestRegisterSubmitBtn'));

    const guestCard = page.locator('#checkedInList .kiosk-member-guest', { hasText: KIOSK_GUEST.lastName });
    await expect(guestCard).toBeVisible({ timeout: 15000 });
    await expect(guestCard.locator('.kiosk-guest-badge')).toBeVisible();
    await humanPause(page, 2500);

    // Scene 3 — a parent finds their child on the roster and checks them in,
    // choosing who brought them in the Check-in By modal.
    const child = waiting.first();
    const id = await child.getAttribute('id');
    if (!id) throw new Error('Kiosk roster has no child id.');
    await child.hover();
    await humanPause(page, 1500);
    await humanClick(child.locator('.checkinButton'));
    await chooseCheckinBy(page);

    const checkedIn = page.locator(`#checkedInList #${id}`);
    await expect(checkedIn).toBeVisible({ timeout: 15000 });
    await expect(checkedIn.locator('.checkoutButton')).toBeVisible();

    // Scene 4 — both children are in Checked In; hold on the full list.
    await expect(page.locator('#checkedInCount')).toHaveText('2', { timeout: 15000 });
    await settle(page, 3000);

    // Scene 5 — checkout: the parent collects the child, choosing who is
    // collecting them; the child returns to the waiting list.
    await humanClick(checkedIn.locator('.checkoutButton'));
    await chooseCheckinBy(page);
    await expect(page.locator(`#notCheckedInList #${id}`)).toBeVisible({ timeout: 15000 });
    await humanPause(page, 2500);

    // Scene 6 — the morning on record: the staff-side roster check-in page
    // for this event, with the guest's check-in still on the attendance list.
    await page.goto(`/event/checkin/${demo.eventId}`);
    await expect(page.locator('#rosterCheckin')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#rosterGroupName')).not.toBeEmpty();
    await expect(page.locator('#checkedInList', { hasText: KIOSK_GUEST.lastName })).toBeVisible({ timeout: 15000 });
    await humanPause(page, 2000);
    await settle(page, 3000);

    await captureScreen(page, testInfo, {
      name: 'kiosk-child-checkin-checkout',
      title: 'Check a Child In and Out',
      category: 'Events & Attendance',
      purpose:
        'The full Sunday check-in morning at the Sunday School kiosk: the branded device opens on the day\'s event, a walk-in guest registers and is checked in with a Guest badge, a parent checks their child in and out choosing who brought and collected them in the Check-in By modal, and the morning lands on the event\'s attendance record.',
    });
  } finally {
    await page.close();
  }
});
