import { expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';

// A geocoded couple from src/admin/demo/people.json that no other capture
// uses. The form is captured unsaved: saving a demo family through the editor
// re-geocodes it and rewrites its country (the import stores the country name,
// the editor posts the code), which would change the shared demo data.
const FAMILY_EMAIL = 'family.robinson23@demo.churchcrm.io';

// captureScreen() resizes the viewport for each device, so scroll the section
// between `top` and `bottom` into view again after every resize, below the
// sticky top bar. A section taller than the viewport keeps its bottom in view.
async function keepInFrame(page: Page, top: string, bottom: string): Promise<void> {
  await page.evaluate(
    ([topSelector, bottomSelector]) => {
      const frame = () => {
        const first = document.querySelector(topSelector);
        const last = document.querySelector(bottomSelector);
        if (!first || !last) {
          return;
        }
        const bar = document.querySelector('header.navbar.sticky-top')?.getBoundingClientRect().height ?? 0;
        const start = first.getBoundingClientRect().top + window.scrollY - bar - 16;
        const end = last.getBoundingClientRect().bottom + window.scrollY + 16;
        const target = end - start <= window.innerHeight - bar ? start : end - window.innerHeight;
        window.scrollTo({ top: target, behavior: 'instant' });
      };
      frame();
      window.addEventListener('resize', frame);
    },
    [top, bottom]
  );
}

test.describe('Family mailing address', () => {
  test('family-mailing-address-editor', async ({ page }, testInfo) => {
    await page.goto('/people/family');
    const rows = page.locator('#families tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 15000 });
    await humanType(page.locator('.dt-search input'), FAMILY_EMAIL);
    const row = rows.filter({ hasText: FAMILY_EMAIL }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    const href = await row.locator('a[href*="/people/family/"]').first().getAttribute('href');
    const familyId = Number(href?.match(/\/people\/family\/(\d+)/)?.[1]);

    await page.goto(`/FamilyEditor.php?FamilyID=${familyId}`);
    await expect(page.locator('#FamilyName')).toHaveValue('Robinson', { timeout: 15000 });
    await expect(page.locator('#Latitude')).not.toHaveValue('');

    const section = page.locator('#secondAddressSection');
    if (!(await section.isVisible())) {
      await humanClick(page.locator('#secondAddressToggle'));
    }
    await expect(section).toBeVisible();
    await humanType(page.locator('#SecondAddress1'), 'PO Box 4127');
    await humanType(page.locator('#SecondCity'), 'Prairie Village');
    const state = page.locator('#SecondState + .ts-wrapper');
    await expect(state).toBeVisible({ timeout: 60000 });
    await humanClick(state.locator('.ts-control'));
    await page.keyboard.type('Kansas');
    await humanClick(page.locator('.ts-dropdown .option:visible', { hasText: /^Kansas$/ }));
    await humanType(page.locator('#SecondZip'), '66208');
    await humanClick(page.locator('#SecondIsMailing'));
    await expect(page.locator('#SecondIsMailing')).toBeChecked();
    await humanPause(page, 400);

    // The Add Person / Add Family buttons (Footer.js initializeFAB) show for
    // the first five seconds after a page load.
    await expect(page.locator('#fab-container .fab-button:visible')).toHaveCount(0, { timeout: 15000 });
    await keepInFrame(page, '.card:has(#Address1) .card-header', '#secondAddressSection');
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'family-mailing-address-editor',
      title: 'Family Mailing Address',
      category: 'People & Families',
      purpose: 'Show marking a PO Box as the family mailing address while the home address stays on the map',
    });
  });
});
