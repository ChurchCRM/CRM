import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { settle } from '../support/human';

test.describe('Self Registrations', () => {
  test('people-self-register-dashboard', async ({ page }, testInfo) => {
    // Self Registrations review dashboard (src/people/routes/self-register.php):
    // status banner, pending/approved/total counters and the month-grouped
    // pending list. Uses the self-registered demo families in
    // src/admin/demo/people.json (Bennett, Okafor, Nguyen pending; Castillo
    // approved), so the counters and the month groups are both populated.
    await page.goto('/people/self-register');
    await expect(page.locator('#selfRegistrations tbody tr').first()).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#selfRegistrations tr.month-group').first()).toBeVisible();
    await settle(page, 1200);

    await captureScreen(page, testInfo, {
      name: 'people-self-register-dashboard',
      title: 'Self Registrations Review Dashboard',
      category: 'People & Families',
      purpose: 'Show the Self Registrations dashboard — registration status, pending and approved counters, and the month-grouped list of new sign-ups to review and approve',
    });
  });
});
