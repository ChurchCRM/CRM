import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';
import { type KioskDemo, KIOSK_GUEST, setUpKioskDemo } from '../support/kiosk-guest';

/**
 * Staff register a walk-in guest on the Sunday School kiosk, then the church
 * office finds the same person waiting on People > Self Registrations.
 * One continuous recording: the kiosk screen first, then the review page.
 */
let demo: KioskDemo;

test.beforeAll(async ({ browser }) => {
  demo = await setUpKioskDemo(browser);
});

test.afterAll(async () => {
  await demo?.tearDown();
});

test('kiosk-guest-register', async ({ page, context }, testInfo) => {
  try {
    await demo.attachKiosk(context);
    await page.goto('/kiosk/');
    await expect(page.locator('#eventTitle')).not.toBeEmpty({ timeout: 15000 });
    await expect(page.locator('#notCheckedInList .kiosk-member').first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 2500);

    await humanClick(page.locator('#registerGuestBtn'));
    const modal = page.locator('#guestRegistrationModal');
    await expect(modal).toBeVisible();
    await humanPause(page, 800);
    await humanType(modal.locator('#guestFirstName'), KIOSK_GUEST.firstName);
    await humanType(modal.locator('#guestLastName'), KIOSK_GUEST.lastName);
    await humanType(modal.locator('#guestPhone'), KIOSK_GUEST.phone);
    await humanType(modal.locator('#guestEmail'), KIOSK_GUEST.email);
    await humanPause(page, 1200);
    await humanClick(modal.locator('#guestRegisterSubmitBtn'));

    const guestCard = page.locator('#checkedInList .kiosk-member-guest', { hasText: KIOSK_GUEST.lastName });
    await expect(guestCard).toBeVisible({ timeout: 15000 });
    await expect(guestCard.locator('.kiosk-guest-badge')).toBeVisible();
    await settle(page, 3000);

    await page.goto('/people/self-register');
    const pendingRow = page.locator('#selfRegistrations tbody tr', { hasText: KIOSK_GUEST.lastName });
    await expect(pendingRow).toBeVisible({ timeout: 15000 });
    await humanPause(page, 1500);
    await pendingRow.hover();
    await settle(page, 3000);

    await captureScreen(page, testInfo, {
      name: 'kiosk-guest-register',
      title: 'Register a Walk-In Guest at the Kiosk',
      category: 'Recordings',
      purpose: 'A walk-in guest is registered and checked in on the Sunday School kiosk, then appears on People > Self Registrations waiting for staff review',
    });
  } finally {
    await page.close();
  }
});
