import { expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanSelect, settle } from '../support/human';

// The demo data gives every child the grade for their age (DemoDataService::applyDemoGrade),
// so Kindergarten (level 5) through 5th (level 10) has children to show.
const KINDERGARTEN = '5';
const FIFTH_GRADE = '10';

async function waitForFloatingButtonsToHide(page: Page): Promise<void> {
  await expect(page.locator('#fab-container .fab-button:visible')).toHaveCount(0, { timeout: 15000 });
}

/** Scrolls an element into frame, again after each resize captureScreen() makes. */
async function keepInView(page: Page, selector: string): Promise<void> {
  await page.evaluate((sel) => {
    const align = () => document.querySelector(sel)?.scrollIntoView({ block: 'center', behavior: 'instant' });
    window.addEventListener('resize', align);
    align();
  }, selector);
}

test.describe('School Grades', () => {
  test('people-grades-editor', async ({ page }, testInfo) => {
    await page.goto('/admin/system/options?mode=grades');
    await expect(page.locator('#gradeNamesCard')).toBeVisible();
    await expect(page.locator('#optionsTable tbody tr')).toHaveCount(14);
    await waitForFloatingButtonsToHide(page);
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'people-grades-editor',
      title: 'School Grades',
      category: 'People & Families',
      purpose: 'Show the church-defined grade list, with grade names for the church’s country',
    });
  });

  test('people-grades-promotion', async ({ page }, testInfo) => {
    await page.goto('/admin/people');
    await expect(page.locator('#peopleGrades .settings-panel-fields')).toBeEnabled({ timeout: 15000 });
    await expect(page.locator('#gradePromotionCard')).toBeVisible();
    await keepInView(page, '#peopleGrades');
    await waitForFloatingButtonsToHide(page);
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'people-grades-promotion',
      title: 'Yearly Grade Promotion',
      category: 'People & Families',
      purpose: 'Show the date on which every child moves up one grade, and the Promote Now button',
    });
  });

  test('people-by-grade-report', async ({ page }, testInfo) => {
    await page.goto('/people/reports');
    await humanClick(page.locator('#report-people-by-grade'));
    await page.waitForURL(/\/people\/reports\/people-by-grade/, { timeout: 30000 });
    await humanSelect(page.locator('#gradeFrom'), KINDERGARTEN);
    await humanSelect(page.locator('#gradeTo'), FIFTH_GRADE);
    await humanClick(page.locator('#runReport'));
    await page.waitForURL(/gradeFrom=5&gradeTo=10/, { timeout: 30000 });
    await expect(page.locator('#reportResults tbody tr[data-person-id]').first()).toBeVisible({ timeout: 15000 });
    await waitForFloatingButtonsToHide(page);
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'people-by-grade-report',
      title: 'People by Grade Report',
      category: 'People & Families',
      purpose: 'Show Kindergarten through 5th in one list, ready to add to the cart and place in a Sunday School class',
    });
  });
});
