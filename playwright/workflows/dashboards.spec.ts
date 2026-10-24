import { expect, test } from '@playwright/test';

import { type CaptureOptions, captureScreen, defineCapture } from '../support/capture';
import { settle } from '../support/human';

/**
 * One shot per module dashboard, all populated by the demo data import —
 * distinct from dashboard-hero (`/v2/dashboard`, the main landing page).
 * `/people/email` and `/people/text` are deliberately excluded:
 * both only ever show a "disabled" warning banner on this demo instance
 * (no real SMTP/SMS server configured — see communication.spec.ts's
 * comment), which isn't a usable dashboard shot.
 */
test.describe('Module Dashboards', () => {
  const dashboards: Array<Omit<CaptureOptions, 'category'> & { path: string }> = [
    {
      name: 'dashboard-people',
      locales: 'all',
      title: 'People Dashboard',
      path: '/people/dashboard',
      purpose: 'Show the People module dashboard',
      usedIn: ['website'],
    },
    { name: 'dashboard-groups', title: 'Groups Dashboard', path: '/groups/dashboard', purpose: 'Show the Groups module dashboard' },
    {
      name: 'dashboard-sundayschool',
      locales: 'all',
      title: 'Sunday School Dashboard',
      path: '/groups/sundayschool/dashboard',
      purpose: 'Show the Sunday School dashboard — classes, teachers, students, attendance',
      usedIn: ['website'],
    },
    { name: 'dashboard-events', title: 'Events Dashboard', path: '/event/dashboard', purpose: 'Show the Events module dashboard' },
    {
      name: 'dashboard-finance',
      locales: 'all',
      title: 'Finance Dashboard',
      path: '/finance/',
      purpose: 'Show the Finance dashboard — totals and quick actions',
      usedIn: ['website'],
    },
    {
      name: 'dashboard-fundraiser',
      title: 'Fundraiser Dashboard',
      path: '/fundraiser/',
      purpose: 'Show the Fundraiser module dashboard',
    },
    {
      name: 'dashboard-admin',
      locales: 'all',
      title: 'Admin Dashboard',
      path: '/admin/',
      purpose: 'Show the Admin dashboard — setup wizard, onboarding checklist',
      usedIn: ['website'],
    },
  ];

  for (const { name, title, path, purpose, usedIn, locales } of dashboards) {
    const capture = defineCapture({ name, title, category: 'Dashboards', purpose, usedIn, locales });
    test(name, capture, async ({ page }, testInfo) => {
      await page.goto(path);
      await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
      await settle(page, 700);

      await captureScreen(page, testInfo, capture);
    });
  }
});
