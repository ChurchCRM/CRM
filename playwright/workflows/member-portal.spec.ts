import { type APIRequestContext, expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';
import {
  adminRequest,
  type PortalDemo,
  setUpPortalDemo,
  signInAsPortalMember,
  waitForAvatars,
} from '../support/portal-member';

let api: APIRequestContext;
let demo: PortalDemo | undefined;

test.describe.configure({ mode: 'serial' });

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

interface PortalWindow {
  CRM: { fullcalendar: { getEvents(): unknown[]; changeView(view: string): void } };
}

async function waitForCalendarEvents(page: Page): Promise<void> {
  await page.waitForFunction(
    () => ((window as unknown as Partial<PortalWindow>).CRM?.fullcalendar?.getEvents().length ?? 0) > 0,
    undefined,
    {
      timeout: 15000,
    },
  );
}

/** A phone opens the calendar as a list; captureScreen() resizes the page instead of reloading it. */
async function listViewWhenNarrow(page: Page): Promise<void> {
  await page.evaluate(() => {
    window.matchMedia('(max-width: 767.98px)').addEventListener('change', (query) => {
      if (query.matches) {
        (window as unknown as PortalWindow).CRM.fullcalendar.changeView('listMonth');
      }
    });
  });
}

/** Scrolls an element's bottom into frame, again after each resize captureScreen() makes. */
async function keepInView(page: Page, selector: string): Promise<void> {
  await page.evaluate((sel) => {
    const align = () => {
      const element = document.querySelector(sel);
      const box = element?.getBoundingClientRect();
      if (element && box && (box.top < 0 || box.bottom > window.innerHeight)) {
        element.scrollIntoView({ block: 'end', behavior: 'instant' });
      }
    };
    window.addEventListener('resize', align);
    align();
  }, selector);
}

test('admin-member-portal-calendars', async ({ page }, testInfo) => {
  await page.goto('/admin/member-portal');
  await humanClick(page.locator('#portal-calendars-tab'));
  await expect(page.locator('#portal-calendars')).toBeVisible();
  await expect(page.locator('.portal-calendar-switch:checked')).toHaveCount(2);
  await settle(page, 600);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-calendars',
    title: 'Member Portal Calendars',
    category: 'Admin & Settings',
    purpose: 'Choose which church and holiday calendars members see in the Member Portal.',
  });
});

test.describe('signed in as a member', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('portal-member-home', async ({ page }, testInfo) => {
    await signInAsPortalMember(page);
    await expect(page.locator('.portal-upcoming-item').first()).toBeVisible();
    await expect(page.locator('#portal-home-family-card')).toBeVisible();
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'portal-member-home',
      title: 'Member Portal Home Page',
      category: 'People & Families',
      purpose: 'A member signs in to their own portal: upcoming events, family and profile.',
    });
  });

  test('portal-member-add-family-member', async ({ page }, testInfo) => {
    await signInAsPortalMember(page);
    await page.goto('/portal/family');
    await humanClick(page.locator('#portal-add-member-open'));
    const dialog = page.locator('#portal-add-member-dialog');
    await expect(dialog).toBeVisible();
    await humanType(page.locator('#portal-new-firstName'), 'Emma');
    await page.locator('#portal-new-birthday').fill('2024-03-14');
    await page.locator('#portal-new-birthday').blur();
    await page.evaluate(() => window.scrollTo(0, 0));
    await waitForAvatars(page);
    await settle(page, 500);

    await captureScreen(page, testInfo, {
      name: 'portal-member-add-family-member',
      title: 'Member Adds a Family Member',
      category: 'People & Families',
      purpose: 'A member proposes a new family member, and the church office reviews it first.',
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await humanClick(page.locator('#portal-add-member-submit'));
    await expect(dialog).toBeHidden({ timeout: 15000 });
  });

  test('portal-member-family', async ({ page }, testInfo) => {
    await signInAsPortalMember(page);
    await page.goto('/portal/family');
    await expect(page.locator('#portal-family-members .portal-member-badge-pending')).toBeVisible();
    await keepInView(page, '#portal-family-members');
    await waitForAvatars(page);
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'portal-member-family',
      title: 'Member Portal: My Family',
      category: 'People & Families',
      purpose: 'Members see their household and address, with a new member awaiting review.',
    });
  });

  test('portal-member-calendar', async ({ page }, testInfo) => {
    await signInAsPortalMember(page);
    await page.goto('/portal/calendar');
    await expect(page.locator('.portal-calendar-legend-item')).toHaveCount(2);
    await waitForCalendarEvents(page);
    await listViewWhenNarrow(page);
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'portal-member-calendar',
      title: 'Member Portal Calendar',
      category: 'Groups & Events',
      purpose: 'Members browse the calendars the church shares, color-coded with a legend.',
    });
  });

  test('portal-member-calendar-subscribe', async ({ page }, testInfo) => {
    await signInAsPortalMember(page);
    await page.goto('/portal/calendar');
    await waitForCalendarEvents(page);
    await listViewWhenNarrow(page);
    await humanClick(page.locator('#portal-calendar-subscribe'));
    const dialog = page.locator('#portal-calendar-subscribe-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#portal-calendar-subscribe-choices input[type=checkbox]')).toHaveCount(2);
    await humanPause(page, 400);
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'portal-member-calendar-subscribe',
      title: 'Subscribe to the Church Calendar',
      category: 'Groups & Events',
      purpose: 'Members subscribe to the shared church calendars from their own calendar app.',
    });
  });
});

test('people-self-register-portal-member', async ({ page }, testInfo) => {
  await page.goto('/people/self-register');
  const row = page.locator('#selfRegistrations tbody tr').filter({ hasText: 'Emma Carter' });
  await expect(row).toBeVisible({ timeout: 15000 });
  await keepInView(page, '#selfRegistrations tbody tr.month-group + tr');
  await settle(page, 1000);

  await captureScreen(page, testInfo, {
    name: 'people-self-register-portal-member',
    title: 'Family Member Awaiting Review',
    category: 'People & Families',
    purpose: 'A family member proposed in the portal waits for staff on Self Registrations.',
  });
});
