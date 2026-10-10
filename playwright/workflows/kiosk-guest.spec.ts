import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanType, settle } from '../support/human';
import { type KioskDemo, KIOSK_GUEST, setUpKioskDemo } from '../support/kiosk-guest';

/**
 * Walk-in guest check-in on the kiosk and where the guest lands afterwards.
 * Serial: the form capture leaves the modal unsent, the next test submits it,
 * and the review capture needs that guest. Cleanup removes the guest, the
 * kiosk and the event so the other workflows' counters are unchanged.
 */
test.describe.configure({ mode: 'serial' });

let demo: KioskDemo;

test.beforeAll(async ({ browser }) => {
  demo = await setUpKioskDemo(browser);
});

test.afterAll(async () => {
  await demo?.tearDown();
});

test.describe('Kiosk walk-in guests', () => {
  test('kiosk-guest-form', async ({ page, context }, testInfo) => {
    await demo.attachKiosk(context);
    await page.goto('/kiosk/');
    await expect(page.locator('#notCheckedInList .kiosk-member').first()).toBeVisible({ timeout: 15000 });
    await humanClick(page.locator('#registerGuestBtn'));
    const modal = page.locator('#guestRegistrationModal');
    await expect(modal).toBeVisible();
    await humanType(modal.locator('#guestFirstName'), KIOSK_GUEST.firstName);
    await humanType(modal.locator('#guestLastName'), KIOSK_GUEST.lastName);
    await humanType(modal.locator('#guestPhone'), KIOSK_GUEST.phone);
    await humanType(modal.locator('#guestEmail'), KIOSK_GUEST.email);
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'kiosk-guest-form',
      title: 'Kiosk Walk-In Guest Form',
      category: 'Events & Attendance',
      purpose: 'Show the Register Walk-In Guest form on the kiosk: name plus a phone number or email, with optional birth date',
    });
  });

  test('kiosk-guest-checked-in', async ({ page, context }, testInfo) => {
    await demo.attachKiosk(context);
    await page.goto('/kiosk/');
    await expect(page.locator('#notCheckedInList .kiosk-member').first()).toBeVisible({ timeout: 15000 });
    await humanClick(page.locator('#registerGuestBtn'));
    const modal = page.locator('#guestRegistrationModal');
    await expect(modal).toBeVisible();
    await humanType(modal.locator('#guestFirstName'), KIOSK_GUEST.firstName);
    await humanType(modal.locator('#guestLastName'), KIOSK_GUEST.lastName);
    await humanType(modal.locator('#guestPhone'), KIOSK_GUEST.phone);
    await humanType(modal.locator('#guestEmail'), KIOSK_GUEST.email);
    await humanClick(modal.locator('#guestRegisterSubmitBtn'));

    const guestCard = page.locator('#checkedInList .kiosk-member-guest', { hasText: KIOSK_GUEST.lastName });
    await expect(guestCard.locator('.kiosk-guest-badge')).toBeVisible({ timeout: 15000 });
    await expect(modal).toBeHidden({ timeout: 15000 });
    // The success toast covers the event header; let it fade out first.
    await expect(page.locator('.kiosk-notification')).toHaveCount(0, { timeout: 10000 });
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'kiosk-guest-checked-in',
      title: 'Kiosk Guest Checked In',
      category: 'Events & Attendance',
      purpose: 'Show a walk-in guest checked in on the kiosk, marked with a Guest badge in the Checked In list',
    });
  });

  test('kiosk-guest-review', async ({ page }, testInfo) => {
    await page.goto('/people/self-register');
    const pendingRow = page.locator('#selfRegistrations tbody tr', { hasText: KIOSK_GUEST.lastName });
    await expect(pendingRow).toBeVisible({ timeout: 15000 });
    await settle(page, 1200);

    await captureScreen(page, testInfo, {
      name: 'kiosk-guest-review',
      title: 'Kiosk Guest Awaiting Review',
      category: 'People & Families',
      purpose: 'Show the walk-in guest registered at the kiosk waiting on People > Self Registrations for staff to review and approve',
    });
  });

  test('kiosk-guest-settings', async ({ page }, testInfo) => {
    await page.goto('/kiosk/admin');
    const card = page.locator('#kioskSettings');
    await expect(card.locator('select[name="iKioskGuestClassification"]')).toBeVisible({ timeout: 15000 });
    await card.scrollIntoViewIfNeeded();
    await settle(page, 1200);

    await captureScreen(page, testInfo, {
      name: 'kiosk-guest-settings',
      title: 'Kiosk Guest Classification Setting',
      category: 'Events & Attendance',
      purpose: 'Show the Kiosk Settings card on Kiosk Manager where an administrator chooses the classification given to walk-in guests',
    });
  });
});
