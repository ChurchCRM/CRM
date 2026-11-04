import { type APIRequestContext, type BrowserContext, expect, type Page, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { humanClick, humanPause, humanType, settle } from '../support/human';
import { rememberedMemberContext, useMemberSignIn } from '../support/member-sign-in';
import { adminRequest, PORTAL_MEMBER, type PortalDemo, setUpPortalDemo, waitForAvatars } from '../support/portal-member';

let api: APIRequestContext;
let demo: PortalDemo | undefined;
let restoreSignIn: (() => Promise<void>) | undefined;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async () => {
  api = await adminRequest();
  demo = await setUpPortalDemo(api);
  restoreSignIn = await useMemberSignIn(api);
});

test.afterAll(async () => {
  try {
    await restoreSignIn?.();
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
  await expect(page.locator('.portal-calendar-switch:checked')).toHaveCount(demo?.sharedCalendarCount ?? 0);
  await settle(page, 600);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-calendars',
    title: 'Member Portal Calendars',
    category: 'Admin & Settings',
    purpose: 'Choose which church and holiday calendars members see in the Member Portal.',
    usedIn: ['docs'],
  });
});

test.describe('signed in as a member', () => {
  let member: BrowserContext | undefined;

  test.beforeAll(async ({ browser }) => {
    member = await rememberedMemberContext(browser, PORTAL_MEMBER.email, 'portal');
  });

  test.afterAll(async () => {
    await member?.close();
  });

  /** A new tab in the member's signed-in browser, on the portal home page. */
  async function memberPage(): Promise<Page> {
    const page = await (member as BrowserContext).newPage();
    await page.goto('/portal/');
    await expect(page.locator('.portal-shell')).toBeVisible({ timeout: 15000 });
    return page;
  }

  const portalMemberHomeCapture = defineCapture({
    name: 'portal-member-home',
    locales: 'all',
    title: 'Member Portal Home Page',
    category: 'People & Families',
    purpose: 'A member signs in to their own portal: upcoming events, family and profile.',
    usedIn: ['docs'],
  });
  test('portal-member-home', portalMemberHomeCapture, async ({}, testInfo) => {
    const page = await memberPage();
    await expect(page.locator('.portal-upcoming-item').first()).toBeVisible();
    await expect(page.locator('#portal-home-family-card')).toBeVisible();
    await settle(page, 600);

    await captureScreen(page, testInfo, portalMemberHomeCapture);
  });

  const portalMemberAddFamilyMemberCapture = defineCapture({
    name: 'portal-member-add-family-member',
    locales: 'all',
    title: 'Member Adds a Family Member',
    category: 'People & Families',
    purpose: 'A member proposes a new family member, and the church office reviews it first.',
  });
  test('portal-member-add-family-member', portalMemberAddFamilyMemberCapture, async ({}, testInfo) => {
    const page = await memberPage();
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

    await captureScreen(page, testInfo, portalMemberAddFamilyMemberCapture);

    await page.setViewportSize({ width: 1440, height: 900 });
    await humanClick(page.locator('#portal-add-member-submit'));
    await expect(dialog).toBeHidden({ timeout: 15000 });
  });

  const portalMemberFamilyCapture = defineCapture({
    name: 'portal-member-family',
    locales: 'all',
    title: 'Member Portal: My Family',
    category: 'People & Families',
    purpose: 'Members see their household and address, with a new member awaiting review.',
    usedIn: ['docs'],
  });
  test('portal-member-family', portalMemberFamilyCapture, async ({}, testInfo) => {
    const page = await memberPage();
    await page.goto('/portal/family');
    await expect(page.locator('#portal-family-members .portal-member-badge-pending')).toBeVisible();
    await keepInView(page, '#portal-family-members');
    await waitForAvatars(page);
    await settle(page, 600);

    await captureScreen(page, testInfo, portalMemberFamilyCapture);
  });

  const portalMemberCalendarCapture = defineCapture({
    name: 'portal-member-calendar',
    locales: 'all',
    title: 'Member Portal Calendar',
    category: 'Groups & Events',
    purpose: 'Members browse the calendars the church shares, color-coded with a legend.',
    usedIn: ['docs'],
  });
  test('portal-member-calendar', portalMemberCalendarCapture, async ({}, testInfo) => {
    const page = await memberPage();
    await page.goto('/portal/calendar');
    await expect(page.locator('.portal-calendar-legend-item')).toHaveCount(demo?.sharedCalendarCount ?? 0);
    await waitForCalendarEvents(page);
    await listViewWhenNarrow(page);
    await settle(page, 800);

    await captureScreen(page, testInfo, portalMemberCalendarCapture);
  });

  const portalMemberCalendarSubscribeCapture = defineCapture({
    name: 'portal-member-calendar-subscribe',
    locales: 'all',
    title: 'Subscribe to the Church Calendar',
    category: 'Groups & Events',
    purpose: 'Members subscribe to the shared church calendars from their own calendar app.',
    usedIn: ['docs'],
  });
  test('portal-member-calendar-subscribe', portalMemberCalendarSubscribeCapture, async ({}, testInfo) => {
    const page = await memberPage();
    await page.goto('/portal/calendar');
    await waitForCalendarEvents(page);
    await listViewWhenNarrow(page);
    await humanClick(page.locator('#portal-calendar-subscribe'));
    const dialog = page.locator('#portal-calendar-subscribe-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#portal-calendar-subscribe-choices input[type=checkbox]')).toHaveCount(
      demo?.sharedCalendarCount ?? 0,
    );
    await humanPause(page, 400);
    await settle(page, 600);

    await captureScreen(page, testInfo, portalMemberCalendarSubscribeCapture);
  });
});

const peopleSelfRegisterPortalMemberCapture = defineCapture({
  name: 'people-self-register-portal-member',
  locales: 'all',
  title: 'Family Member Awaiting Review',
  category: 'People & Families',
  purpose: 'A family member proposed in the portal waits for staff on Self Registrations.',
});
test('people-self-register-portal-member', peopleSelfRegisterPortalMemberCapture, async ({ page }, testInfo) => {
  await page.goto('/people/self-register');
  const row = page.locator('#selfRegistrations tbody tr').filter({ hasText: 'Emma Carter' });
  await expect(row).toBeVisible({ timeout: 15000 });
  await keepInView(page, '#selfRegistrations tbody tr.month-group + tr');
  await settle(page, 1000);

  await captureScreen(page, testInfo, peopleSelfRegisterPortalMemberCapture);
});
