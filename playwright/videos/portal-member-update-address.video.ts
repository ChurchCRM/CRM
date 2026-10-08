import { type APIRequestContext, expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';
import {
  adminRequest,
  ok,
  type PortalDemo,
  portalCsrfToken,
  setUpPortalDemo,
  signInAsPortalMember,
  waitForAvatars,
} from '../support/portal-member';

let api: APIRequestContext;
let demo: PortalDemo | undefined;

test.use({ storageState: { cookies: [], origins: [] } });

test.beforeAll(async () => {
  api = await adminRequest();
  demo = await setUpPortalDemo(api);
});

test.afterAll(async () => {
  try {
    await demo?.tearDown();
  } finally {
    await api?.dispose();
  }
});

test('portal-member-update-address', async ({ page, context }, testInfo) => {
  const familyLink = page.locator('#portal-nav a.portal-nav-link[href$="/portal/family"]');
  let original: Record<string, string> | null = null;

  try {
    await signInAsPortalMember(page);
    await expect(page.locator('.portal-upcoming-item').first()).toBeVisible();
    await humanPause(page, 2000);

    await humanClick(familyLink);
    await expect(page.locator('#portal-family-address')).toBeVisible();
    await waitForAvatars(page);
    original = (await (await ok(await context.request.get('/api/portal/family'), 'Read family')).json()).family;
    await humanPause(page, 1500);

    await humanClick(page.locator('#portal-family-edit-link'));
    await expect(page.locator('#portal-address1')).toBeVisible();
    await humanPause(page, 800);
    await humanType(page.locator('#portal-address1'), '2240 Prairie View Lane');
    await humanType(page.locator('#portal-city'), 'Overland Park');
    await humanType(page.locator('#portal-zip'), '66213');
    await humanPause(page, 600);
    await humanClick(page.locator('#portal-family-save'));
    await expect(page.locator('#portal-toasts')).toContainText(/\S/, { timeout: 15000 });
    await humanPause(page, 2000);

    await humanClick(familyLink);
    await expect(page.locator('#portal-family-address [data-field="address1"]')).toHaveText('2240 Prairie View Lane');
    await waitForAvatars(page);
    await settle(page, 2500);

    await captureScreen(page, testInfo, {
      name: 'portal-member-update-address',
      title: 'Member Updates Family Address',
      category: 'Recordings',
      purpose: 'A member signs in to the Member Portal and updates the family address.',
    });
  } finally {
    // Closing the page ends the recording, so restoring the address is not in it.
    await page.close();
    if (original) {
      const { address1, city, zip } = original;
      await ok(
        await context.request.post('/api/portal/family', {
          data: { address1, city, zip },
          headers: { 'X-CSRF-Token': await portalCsrfToken(context.request) },
        }),
        'Restore family address',
      );
    }
  }
});
