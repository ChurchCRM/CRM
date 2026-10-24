import { type APIRequestContext, type BrowserContext, expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanSelect, humanType, settle } from '../support/human';
import {
  addVirtualAuthenticator,
  askForCode,
  type DemoPerson,
  findDemoPerson,
  forgetDevicesAndPasskeys,
  freshContext,
  GRANT_SUBJECT,
  LOGIN_AS_PERSON_SUBJECT,
  SIGN_IN_MEMBERS,
  signInAsStaffAdmin,
  signInFirstMember,
  useMemberSignIn,
} from '../support/member-sign-in';
import { adminRequest } from '../support/portal-member';
import { waitForFloatingButtonsToTuck } from '../support/volunteer';

let api: APIRequestContext;
let restoreSettings: (() => Promise<void>) | undefined;
let memberContext: BrowserContext | undefined;
let memberPage: Page;
let member: DemoPerson | undefined;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  api = await adminRequest();
  for (const email of SIGN_IN_MEMBERS) {
    await forgetDevicesAndPasskeys(api, (await findDemoPerson(api, email)).personId);
  }
  restoreSettings = await useMemberSignIn(api);
  memberContext = await freshContext(browser);
  memberPage = await memberContext.newPage();
  await addVirtualAuthenticator(memberPage);
});

test.afterAll(async () => {
  try {
    await memberContext?.close();
    if (member) {
      await forgetDevicesAndPasskeys(api, member.personId);
    }
  } finally {
    try {
      await restoreSettings?.();
    } finally {
      await api?.dispose();
    }
  }
});

/** Scrolls an element into frame, again after each resize captureScreen() makes. */
async function keepInView(page: Page, selector: string, block: 'start' | 'end' = 'end'): Promise<void> {
  await page.evaluate(
    ([sel, where]) => {
      const align = () => {
        const element = document.querySelector(sel);
        const box = element?.getBoundingClientRect();
        if (element && box && (box.top < 0 || box.bottom > window.innerHeight)) {
          element.scrollIntoView({ block: where as ScrollLogicalPosition, behavior: 'instant' });
        }
      };
      window.addEventListener('resize', align);
      align();
    },
    [selector, block],
  );
}

async function openMemberPortalTab(page: Page, tab: string): Promise<void> {
  await page.goto('/admin/member-portal');
  await humanClick(page.locator(`#portal-${tab}-tab`));
  await expect(page.locator(`#portal-${tab}`)).toBeVisible();
  await waitForFloatingButtonsToTuck(page);
}

test('portal-sign-in', async ({}, testInfo) => {
  await memberPage.goto('/portal/login');
  await expect(memberPage.locator('#portalSignInForm')).toBeVisible({ timeout: 15000 });
  if ((await memberPage.locator('#portalPasskeySignIn').count()) > 0) {
    await expect(memberPage.locator('#portalPasskeySignIn')).toBeVisible();
  }
  await settle(memberPage, 600);

  await captureScreen(memberPage, testInfo, {
    name: 'portal-sign-in',
    title: 'Member Sign-In Page',
    category: 'People & Families',
    purpose: 'Members sign in with their own email address, or a passkey: no password.',
  });
});

test('portal-sign-in-code', async ({}, testInfo) => {
  await askForCode(memberPage, SIGN_IN_MEMBERS[0]);
  await expect(memberPage.locator('#portalRememberDevice')).not.toBeChecked();
  await settle(memberPage, 600);

  await captureScreen(memberPage, testInfo, {
    name: 'portal-sign-in-code',
    title: 'Member Sign-In: Enter the Code',
    category: 'People & Families',
    purpose: 'The emailed code goes in here, and the member chooses whether to stay signed in.',
  });

  await memberPage.setViewportSize({ width: 1440, height: 900 });
  const email = await signInFirstMember(memberPage, SIGN_IN_MEMBERS[0]);
  member = await findDemoPerson(api, email);
});

test('portal-sign-in-devices', async ({}, testInfo) => {
  await memberPage.goto('/portal/devices');
  await expect(memberPage.locator('#portal-devices-table [data-this-device]')).toBeVisible();
  const addPasskey = memberPage.locator('#portal-passkey-add');
  if ((await addPasskey.count()) > 0) {
    await humanClick(addPasskey);
    await expect(memberPage.locator('#portal-passkeys-table [data-passkey-row]')).toHaveCount(1, { timeout: 15000 });
    await memberPage.reload();
  }
  await settle(memberPage, 600);

  await captureScreen(memberPage, testInfo, {
    name: 'portal-sign-in-devices',
    title: 'Member Sign-In and Devices',
    category: 'People & Families',
    purpose: 'Members see where they stay signed in and their passkeys, and sign any of them out.',
  });
});

test('admin-member-portal-sign-in', async ({ page }, testInfo) => {
  await openMemberPortalTab(page, 'signin');
  await expect(page.locator('#bMemberSignIn')).toBeChecked();
  await settle(page, 600);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-sign-in',
    title: 'Member Portal Sign-In Settings',
    category: 'Admin & Settings',
    purpose: 'Turn member sign-in on and choose who may sign in by family role, classification and age.',
  });
});

test('admin-member-portal-members', async ({ page }, testInfo) => {
  await openMemberPortalTab(page, 'members');
  await expect(page.locator('#memberListTable tbody tr').first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('[data-count-for="may-sign-in"]')).not.toHaveText('…');
  await settle(page, 800);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-members',
    title: 'Who May Sign In to the Portal',
    category: 'Admin & Settings',
    purpose: 'Lists of who may sign in, who has, who never has and whose email needs attention.',
  });
});

test('admin-member-portal-audit', async ({ page }, testInfo) => {
  await openMemberPortalTab(page, 'audit');
  await expect(page.locator('#portalAuditTable tbody tr').first()).toBeVisible({ timeout: 15000 });
  await settle(page, 800);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-audit',
    title: 'Member Sign-In Audit',
    category: 'Admin & Settings',
    purpose: 'Every member sign-in, refusal and change to sign-in, in plain words, newest first.',
  });
});

test('admin-member-portal-permissions', async ({ page }, testInfo) => {
  await openMemberPortalTab(page, 'permissions');
  await expect(page.locator('#portalPermissionsTable tbody tr').nth(1)).toBeVisible({ timeout: 15000 });
  await settle(page, 800);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-permissions',
    title: 'Member Portal Permissions',
    category: 'Admin & Settings',
    purpose: 'What members can see and do in the portal, where it comes from and who holds it.',
  });
});

test('admin-member-portal-statistics', async ({ page }, testInfo) => {
  await openMemberPortalTab(page, 'statistics');
  await settle(page, 800);

  await captureScreen(page, testInfo, {
    name: 'admin-member-portal-statistics',
    title: 'Member Portal Statistics',
    category: 'Admin & Settings',
    purpose: 'How many members may sign in, how many have, and the most recent sign-ins.',
  });
});

test('person-portal-sign-in-card', async ({ page }, testInfo) => {
  expect(member, 'a member signed in earlier in this file').toBeDefined();
  await page.goto(`/people/view/${member?.personId}`);
  await expect(page.locator('#portal-signin-card [data-cy=portal-device]')).toHaveCount(1);
  await keepInView(page, '#portal-signin-card');
  await waitForFloatingButtonsToTuck(page);
  await settle(page, 800);

  await captureScreen(page, testInfo, {
    name: 'person-portal-sign-in-card',
    title: 'Portal Sign-In on the Person Page',
    category: 'People & Families',
    purpose: "A person's sign-in status, devices, passkeys and portal permissions, for administrators.",
  });
});

test('person-portal-give-permission', async ({ page }, testInfo) => {
  const son = await findDemoPerson(api, GRANT_SUBJECT);
  await page.goto(`/people/view/${son.personId}`);
  await waitForFloatingButtonsToTuck(page);
  await humanClick(page.locator('#portal-grant-add'));
  const dialog = page.locator('#portal-grant-modal');
  await expect(dialog).toBeVisible();
  await humanSelect(page.locator('#portal-grant-capability'), 'portal.family.contacts.view');
  await expect(page.locator('#portal-grant-scope-group')).toBeVisible();
  const scope = dialog.locator('#portal-grant-scope-group .ts-control input');
  await humanType(scope, 'Scott');
  await humanClick(page.locator('.ts-dropdown .option:visible').filter({ hasText: 'Scott' }).first());
  await humanType(page.locator('#portal-grant-reason'), 'Away at college and helps his parents keep in touch.');
  await settle(page, 600);

  await captureScreen(page, testInfo, {
    name: 'person-portal-give-permission',
    title: 'Give a Portal Permission',
    category: 'People & Families',
    purpose: 'An administrator gives one person a portal permission, with a scope, end date and reason.',
  });

  await page.setViewportSize({ width: 1440, height: 900 });
  await humanClick(dialog.locator('[data-bs-dismiss="modal"]').last());
  await expect(dialog).toBeHidden();
});

test('people-family-email-card', async ({ page }, testInfo) => {
  const carter = await findDemoPerson(api, LOGIN_AS_PERSON_SUBJECT);
  await page.goto(`/people/family/${carter.familyId}`);
  await expect(page.locator('#family-email-card [data-family-adult-email]')).toHaveCount(2);
  await keepInView(page, '#family-email-card');
  await waitForFloatingButtonsToTuck(page);
  await settle(page, 800);

  await captureScreen(page, testInfo, {
    name: 'people-family-email-card',
    title: 'Family Email: Each Adult',
    category: 'People & Families',
    purpose: "A family page lists each adult's own email; family mail goes to the head of household.",
  });
});

test.describe('in a browser of its own', () => {
  let context: BrowserContext | undefined;

  test.afterEach(async () => {
    await context?.close();
    context = undefined;
  });

  test('staff-sign-in', async ({ browser }, testInfo) => {
    context = await freshContext(browser);
    const page = await context.newPage();
    await page.goto('/admin/');
    await expect(page.locator('input[name=User]')).toBeVisible({ timeout: 15000 });
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'staff-sign-in',
      title: 'Staff Sign-In Page',
      category: 'Admin & Settings',
      purpose: 'Staff sign in at /admin/ with their username, password and two-factor code.',
    });
  });

  test('portal-login-as-person', async ({ browser }, testInfo) => {
    const george = await findDemoPerson(api, LOGIN_AS_PERSON_SUBJECT);
    context = await freshContext(browser);
    const page = await context.newPage();
    await signInAsStaffAdmin(page);
    await page.goto(`/people/view/${george.personId}`);
    await humanClick(page.locator('#person-actions-dropdown'));
    await humanClick(page.locator('a#loginAsPerson'));
    await humanClick(page.locator('.bootbox .btn-warning'));
    await page.waitForURL(/\/portal\/$/, { timeout: 30000 });
    await expect(page.locator('#login-as-person-banner')).toBeVisible();
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'portal-login-as-person',
      title: 'Login as Person in the Member Portal',
      category: 'People & Families',
      purpose: "An administrator sees a member's portal as they do, read-only, with Stop always in view.",
    });

    await humanClick(page.locator('#login-as-person-stop'));
    await page.waitForURL(new RegExp(`/people/view/${george.personId}`), { timeout: 30000 });
  });
});
