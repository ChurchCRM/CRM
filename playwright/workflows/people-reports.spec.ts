import { expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, settle } from '../support/human';

// May of the demo data (src/admin/demo/people.json) mixes member birthdays with
// three couples' anniversaries near the top of the list, and avoids the
// subjects other captures use for status shots (Campbell, Joseph Hall).
const MAY = '5';

// The Add Person / Add Family buttons (Footer.js initializeFAB) show for the
// first five seconds after a page load.
async function waitForFloatingButtonsToHide(page: Page): Promise<void> {
  await expect(page.locator('#fab-container .fab-button:visible')).toHaveCount(0, { timeout: 15000 });
}

async function runBirthdaysAndAnniversaries(page: Page): Promise<void> {
  await page.goto('/people/reports');
  await humanClick(page.locator('#report-birthdays-anniversaries'));
  await page.waitForURL(/\/people\/reports\/birthdays-anniversaries/, { timeout: 30000 });

  await humanSelect(page.locator('#month'), MAY);
  const classification = page.locator('#classification + .ts-wrapper');
  await humanClick(classification.locator('.ts-control'));
  await page.keyboard.type('Member');
  await humanClick(classification.locator('.ts-dropdown .option', { hasText: /^Member$/ }));
  await page.keyboard.press('Escape');
  await expect(page.locator('#classification')).toHaveValues([/\d+/]);
  await humanPause(page, 400);
  await humanClick(page.locator('#runReport'));

  await page.waitForURL(/month=5&classification/, { timeout: 30000 });
  await expect(page.locator('#reportResults tbody tr[data-person-id]').first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#reportResults tbody')).toContainText('Anniversary');
  await expect(classification.locator('.item')).toHaveCount(1);
  await expect(classification.locator('.item')).toContainText('Member');
}

test.describe('People Reports', () => {
  test('people-reports-list', async ({ page }, testInfo) => {
    await page.goto('/people/reports');
    await expect(page.locator('#peopleReports a[id^="report-"]')).toHaveCount(9);
    await waitForFloatingButtonsToHide(page);
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'people-reports-list',
      title: 'People Reports',
      category: 'People & Families',
      purpose: 'Show the ready-made people reports: birthdays, anniversaries, volunteers and more',
    });
  });

  test('people-reports-birthdays-anniversaries', async ({ page }, testInfo) => {
    await runBirthdaysAndAnniversaries(page);
    await waitForFloatingButtonsToHide(page);
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'people-reports-birthdays-anniversaries',
      title: 'Birthdays & Anniversaries Report',
      category: 'People & Families',
      purpose: 'Show a month of member birthdays and anniversaries, ready to add to the cart, download or print as labels',
    });
  });

  test('people-reports-print-labels', async ({ page }, testInfo) => {
    await runBirthdaysAndAnniversaries(page);
    await waitForFloatingButtonsToHide(page);
    await humanClick(page.locator('#printLabels'));
    const dialog = page.locator('#labelsModal');
    await expect(dialog).toHaveClass(/show/, { timeout: 10000 });
    await expect(dialog.locator('#labelsGrouping')).toContainText('anniversaries to the couple');
    await humanSelect(dialog.locator('#labeltype'), '5160');
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'people-reports-print-labels',
      title: 'Print Labels from a Report',
      category: 'People & Families',
      purpose: 'Show printing mailing labels from a report: birthdays to the person, anniversaries to the couple',
    });
  });
});
