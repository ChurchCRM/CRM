import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, settle } from '../support/human';
import { type KioskDemo, setUpKioskDemo } from '../support/kiosk-guest';

/**
 * Parent-side kiosk path on a registered Sunday School device.
 * The 7.8.1 flow can ask which family member brings in / picks up the child.
 * The modal displays guardian photos (or initials fallback); the selected
 * person is recorded as check-in/check-out attribution.
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

test('kiosk-child-checkin-checkout', async ({ page, context }, testInfo) => {
  try {
    await demo.attachKiosk(context);
    await page.goto('/kiosk/');
    await expect(page.locator('#eventTitle')).not.toBeEmpty({ timeout: 15000 });
    const waiting = page.locator('#notCheckedInList .kiosk-member[data-familyId]');
    await expect(waiting.first()).toBeVisible({ timeout: 15000 });
    const child = waiting.first();
    const id = await child.getAttribute('id');
    if (!id) throw new Error('Kiosk roster has no child id.');
    await humanPause(page, 1400);

    await humanClick(child.locator('.checkinButton'));
    const modal = page.locator('#checkinByModal');
    if (await modal.isVisible()) {
      const guardians = modal.locator('.checkinByMemberBtn');
      await expect(guardians.first()).toBeVisible({ timeout: 15000 });
      await expect(guardians.first().locator('img.checkin-by-photo')).toBeVisible();
      await humanPause(page, 1100);
      await humanClick(guardians.first());
    }

    const checkedIn = page.locator(`#checkedInList #${id}`);
    await expect(checkedIn).toBeVisible({ timeout: 15000 });
    await expect(checkedIn.locator('.checkoutButton')).toBeVisible();
    await humanPause(page, 1800);

    await humanClick(checkedIn.locator('.checkoutButton'));
    if (await modal.isVisible()) {
      const guardians = modal.locator('.checkinByMemberBtn');
      await expect(guardians.first()).toBeVisible({ timeout: 15000 });
      await humanPause(page, 1000);
      await humanClick(guardians.first());
    }
    await expect(page.locator(`#notCheckedInList #${id}`)).toBeVisible({ timeout: 15000 });
    await settle(page, 2000);

    await captureScreen(page, testInfo, {
      name: 'kiosk-child-checkin-checkout',
      title: 'Check a Child In and Out',
      category: 'Recordings',
      purpose: 'A parent checks a child in at the Sunday School kiosk and later checks them out, selecting the responsible family member when attribution is enabled.',
    });
  } finally {
    await page.close();
  }
});
