import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, humanType } from '../support/human';

/**
 * A visitor self-registering their family — no login, no staff involvement.
 * Runs unauthenticated (this 'videos' project has no storageState), against
 * the public /external/register/ wizard (src/external/routes/register.php,
 * gated behind bEnableSelfRegistration — enabled in the demo config).
 * "Whitfield", matching people-family.spec.ts's people-family-new-family,
 * so a self-registered family is never confused with a seeded demo one.
 */
test('family-self-register', async ({ page }, testInfo) => {
  await page.goto('/external/register/');
  await expect(page.locator('#registration-stepper')).toBeVisible({ timeout: 15000 });
  await humanPause(page, 1200);

  // Step 1: Family Info
  await humanType(page.locator('#familyName'), 'Whitfield');
  await humanType(page.locator('#familyAddress1'), '245 Willow Creek Rd');
  await humanType(page.locator('#familyCity'), 'Springfield');
  // DropdownManager.initializeFamilyRegisterCountryState() (FamilyRegister.js)
  // replaces this input with a TomSelect-enhanced <select> of state codes at
  // runtime — humanType's click is blocked by TomSelect's overlay, same as
  // #State/#sChurchState elsewhere in this pipeline.
  await humanSelect(page.locator('#familyState'), 'IL');
  await humanType(page.locator('#familyZip'), '62704');
  await humanType(page.locator('#familyHomePhone'), '(555) 010-1000');
  await humanPause(page, 600);
  await humanClick(page.locator('#family-info-next'));

  // Step 2: Members — the first card is auto-added and starts expanded
  // (see src/skin/js/FamilyRegister.js's addMember()), no click needed.
  await expect(page.locator('#step-members')).toHaveClass(/active/, { timeout: 10000 });
  const memberCard = page.locator('#members-container .member-card').first();
  await humanType(memberCard.locator('.member-first-name'), 'Michael');
  await humanType(memberCard.locator('.member-last-name'), 'Whitfield');
  await humanType(memberCard.locator('.member-email'), 'family.whitfield@demo.churchcrm.io');
  await humanPause(page, 600);
  await humanClick(page.locator('#members-next'));

  // Step 3: Review, then submit
  await expect(page.locator('#step-review')).toHaveClass(/active/, { timeout: 10000 });
  await humanPause(page, 1200);
  await humanClick(page.locator('#submit-registration'));

  await page.getByText('Welcome to the Family!').first().waitFor({ state: 'visible', timeout: 10000 });
  // Let the confirmation dialog sit on screen long enough for a viewer to
  // actually read it before the video ends.
  await humanPause(page, 2500);

  await captureScreen(page, testInfo, {
    name: 'family-self-register',
    purpose: 'Show a visitor self-registering their family without staff involvement',
  });
});
