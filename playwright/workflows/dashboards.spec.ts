import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanPause } from '../support/human';

/**
 * One shot per module dashboard, all populated by the demo data import —
 * distinct from dashboard-hero (`/v2/dashboard`, the main landing page).
 * `/v2/email/dashboard` and `/v2/text/dashboard` are deliberately excluded:
 * both only ever show a "disabled" warning banner on this demo instance
 * (no real SMTP/SMS server configured — see communication.spec.ts's
 * comment), which isn't a usable dashboard shot.
 */
test.describe('Module Dashboards', () => {
  const dashboards: Array<{ name: string; path: string; purpose: string }> = [
    { name: 'dashboard-people', path: '/people/dashboard', purpose: 'Show the People module dashboard' },
    { name: 'dashboard-groups', path: '/groups/dashboard', purpose: 'Show the Groups module dashboard' },
    {
      name: 'dashboard-sundayschool',
      path: '/groups/sundayschool/dashboard',
      purpose: 'Show the Sunday School dashboard — classes, teachers, students, attendance',
    },
    { name: 'dashboard-events', path: '/event/dashboard', purpose: 'Show the Events module dashboard' },
    { name: 'dashboard-finance', path: '/finance/', purpose: 'Show the Finance dashboard — totals and quick actions' },
    { name: 'dashboard-fundraiser', path: '/fundraiser/', purpose: 'Show the Fundraiser module dashboard' },
    {
      name: 'dashboard-admin',
      path: '/admin/',
      purpose: 'Show the Admin dashboard — setup wizard, onboarding checklist',
    },
  ];

  for (const { name, path, purpose } of dashboards) {
    test(name, async ({ page }, testInfo) => {
      await page.goto(path);
      await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
      await humanPause(page, 700);

      await captureScreen(page, testInfo, { name, purpose });
    });
  }
});
