import { type APIRequestContext, type Browser, type BrowserContext, expect, type Page, request } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

import { ADMIN_USERNAME, ADMIN_WORKING_PASSWORD, BASE_URL, MAILPIT_URL } from './env';
import { humanClick, humanPause, humanType } from './human';
import { ok } from './portal-member';

/**
 * Demo adults who may sign in once the Member classification is ticked, each
 * with their own email, a photo, and a family no other capture uses. ChurchCRM
 * sends one person at most five sign-in emails a day, and one address five an
 * hour, so a run that captures several locales on one instance moves on to
 * the next person when no code arrives.
 */
export const SIGN_IN_MEMBERS = [
  'sarah.carter@demo.churchcrm.io',
  'laura.lee@demo.churchcrm.io',
  'susan.thomas@demo.churchcrm.io',
  'nancy.robinson@demo.churchcrm.io',
];

/** No user account, so Login as Person may open his portal. */
export const LOGIN_AS_PERSON_SUBJECT = 'george.carter@demo.churchcrm.io';

/** A 19-year-old son: the rules give him no family contacts, so an administrator might. */
export const GRANT_SUBJECT = 'george.scott1@demo.churchcrm.io';

export interface DemoPerson {
  personId: number;
  familyId: number;
  email: string;
}

async function configValue(api: APIRequestContext, name: string): Promise<string> {
  const { value } = await (await ok(await api.get(`/admin/api/system/config/${name}`), `Read ${name}`)).json();
  return String(value ?? '');
}

async function setConfig(api: APIRequestContext, name: string, value: string): Promise<void> {
  await ok(await api.post(`/admin/api/system/config/${name}`, { data: { value } }), `Set ${name}`);
}

/**
 * Email through the stack's Mailpit, and member sign-in on for the Member and
 * Regular Attender classifications, with Login as Person allowed. Returns the
 * step that puts every setting back, so later captures see the instance as it
 * was.
 */
export async function useMemberSignIn(api: APIRequestContext): Promise<() => Promise<void>> {
  const { classifications } = await (await ok(await api.get('/admin/api/member-portal/sign-in'), 'Read sign-in settings')).json();
  const ticked = (classifications as Array<{ id: number; name: string }>)
    .filter((c) => c.name === 'Member' || c.name === 'Regular Attender')
    .map((c) => c.id);
  if (ticked.length === 0) {
    throw new Error('The demo data has no Member classification');
  }

  // In this order: member sign-in can only be turned on once email works.
  const wanted: Record<string, string> = {
    sSMTPHost: 'mailserver:1025',
    bEnabledEmail: '1',
    sMemberSignInClassifications: ticked.join(','),
    iMemberSignInRequestsPerIP: '1000',
    bAllowLoginAsPerson: '1',
    bMemberSignIn: '1',
  };
  const saved: Record<string, string> = {};
  for (const name of Object.keys(wanted)) {
    saved[name] = await configValue(api, name);
  }
  for (const [name, value] of Object.entries(wanted)) {
    await setConfig(api, name, value);
  }

  return async () => {
    for (const name of Object.keys(wanted).reverse()) {
      await setConfig(api, name, saved[name]);
    }
  };
}

export async function findDemoPerson(api: APIRequestContext, email: string): Promise<DemoPerson> {
  const matches = await (await ok(await api.get(`/api/persons/search/${encodeURIComponent(email)}`), `Find ${email}`)).json();
  const personId = Number(matches[0]?.objid);
  expect(personId, `demo person ${email}`).toBeGreaterThan(0);
  const person = await (await ok(await api.get(`/api/person/${personId}`), `Read ${email}`)).json();
  return { personId, familyId: Number(person.FamId), email };
}

async function mailpit<T>(work: (mail: APIRequestContext) => Promise<T>): Promise<T> {
  const mail = await request.newContext({ baseURL: MAILPIT_URL });
  try {
    return await work(mail);
  } finally {
    await mail.dispose();
  }
}

function searchFor(email: string): string {
  return `/api/v1/search?query=${encodeURIComponent(`to:"${email}"`)}`;
}

export async function clearMailTo(email: string): Promise<void> {
  await mailpit(async (mail) => {
    await ok(await mail.delete(searchFor(email)), `Clear Mailpit for ${email}`);
  });
}

/** The code from the sign-in email to `email`, or null when none arrived. */
export async function signInCode(email: string, waitMs = 8000): Promise<string | null> {
  return mailpit(async (mail) => {
    const deadline = Date.now() + waitMs;
    do {
      const { messages } = await (await ok(await mail.get(searchFor(email)), 'Search Mailpit')).json();
      if (messages?.length) {
        const { Text } = await (await ok(await mail.get(`/api/v1/message/${messages[0].ID}`), 'Read the sign-in email')).json();
        const text = String(Text ?? '');
        const code = text.match(/Your code\s+(\d{6})/)?.[1] ?? text.match(/\b(\d{6})\b/)?.[1];
        if (code) {
          return code;
        }
      }
      await new Promise((resolve) => setTimeout(resolve, 400));
    } while (Date.now() < deadline);
    return null;
  });
}

/** Types an address on /portal/login and waits for the answer page with its code box. */
export async function askForCode(page: Page, email: string): Promise<void> {
  await clearMailTo(email);
  await page.goto('/portal/login');
  await humanType(page.locator('#portalSignInEmail'), email);
  await humanPause(page, 300);
  await humanClick(page.locator('#portalSignInSubmit'));
  await page.waitForURL(/\/portal\/login\/code/, { timeout: 30000 });
  await expect(page.locator('#portalSignInCode')).toBeVisible();
}

/** Ticks "This is my own device", types the code and lands on the portal home page. */
export async function enterCode(page: Page, code: string): Promise<void> {
  await page.locator('#portalRememberDevice').check();
  await humanType(page.locator('#portalSignInCode'), code);
  await humanClick(page.locator('#portalSignInCodeSubmit'));
  await page.waitForURL(/\/portal\/$/, { timeout: 30000 });
  await expect(page.locator('.portal-shell')).toBeVisible({ timeout: 15000 });
}

/**
 * Signs the first member who gets a code in; the answer page has already been
 * reached for `alreadyAsked` when the caller captured it.
 */
export async function signInFirstMember(page: Page, alreadyAsked?: string): Promise<string> {
  for (const email of SIGN_IN_MEMBERS) {
    if (email !== alreadyAsked) {
      await askForCode(page, email);
    }
    const code = await signInCode(email);
    if (code) {
      await enterCode(page, code);
      return email;
    }
  }
  throw new Error('No demo member got a sign-in code. Is Mailpit reachable at CHURCHCRM_MAILPIT_URL?');
}

const MEMBER_CONTEXT = {
  baseURL: BASE_URL,
  viewport: { width: 1440, height: 900 },
  deviceScaleFactor: 2,
  timezoneId: 'America/Chicago',
};

/** A browser context of its own, so a sign-in there never touches the admin session the other captures share. */
export function freshContext(browser: Browser): Promise<BrowserContext> {
  return browser.newContext({ ...MEMBER_CONTEXT, storageState: { cookies: [], origins: [] } });
}

/**
 * A browser context signed in to the Member Portal as one fixed person, for the
 * captures that need that person's own family or schedule. The person ticks
 * "This is my own device", and the context's cookies are saved to
 * playwright/.auth/member-<name>.json each time the device signs them in again,
 * because its token changes at every use. So only the first run on an instance
 * waits for an emailed code, and the locale passes never reach the limit of
 * five sign-in emails a day for one person.
 */
export async function rememberedMemberContext(browser: Browser, email: string, name: string): Promise<BrowserContext> {
  const statePath = path.join(__dirname, '..', '.auth', `member-${name}.json`);
  if (fs.existsSync(statePath)) {
    const context = await browser.newContext({ ...MEMBER_CONTEXT, storageState: statePath });
    const page = await context.newPage();
    await page.goto('/portal/');
    if (new URL(page.url()).pathname.endsWith('/portal/') && (await page.locator('.portal-shell').count()) > 0) {
      await context.storageState({ path: statePath });
      await page.close();
      return context;
    }
    await context.close();
  }

  const context = await freshContext(browser);
  const page = await context.newPage();
  await askForCode(page, email);
  const code = await signInCode(email);
  if (!code) {
    throw new Error(`No sign-in code reached ${email}. Is Mailpit reachable at CHURCHCRM_MAILPIT_URL?`);
  }
  await enterCode(page, code);
  fs.mkdirSync(path.dirname(statePath), { recursive: true });
  await context.storageState({ path: statePath });
  await page.close();
  return context;
}

export async function signInAsStaffAdmin(page: Page): Promise<void> {
  await page.goto('/admin/');
  await humanType(page.locator('input[name=User]'), ADMIN_USERNAME);
  await humanType(page.locator('input[name=Password]'), ADMIN_WORKING_PASSWORD);
  await page.locator('input[name=Password]').press('Enter');
  await expect(page.locator('input[name=Password]')).toHaveCount(0, { timeout: 30000 });
}

/** Chrome's virtual authenticator, standing in for Face ID or a fingerprint on this device. */
export async function addVirtualAuthenticator(page: Page): Promise<void> {
  const devtools = await page.context().newCDPSession(page);
  await devtools.send('WebAuthn.enable', { enableUI: false });
  await devtools.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });
}

/** Signs the member's devices out and removes their passkeys, so the next run starts clean. */
export async function forgetDevicesAndPasskeys(api: APIRequestContext, personId: number): Promise<void> {
  const html = await (await ok(await api.get(`/people/view/${personId}`), 'Open the person page')).text();
  const idsOf = (attribute: string) => new Set(Array.from(html.matchAll(new RegExp(`${attribute}="(\\d+)"`, 'g')), ([, id]) => id));
  const passkeyIds = idsOf('data-passkey-id');
  if (passkeyIds.size === 0 && idsOf('data-device-id').size === 0) {
    return;
  }
  const token = html.match(/id="portal-signin-card"[^>]*data-csrf-token="([^"]+)"/)?.[1];
  if (!token) {
    throw new Error('No CSRF token on the Portal sign-in card');
  }
  const headers = { 'X-CSRF-Token': token };
  for (const passkeyId of passkeyIds) {
    await ok(
      await api.post(`/admin/api/person/${personId}/sign-in/passkeys/${passkeyId}/remove`, { headers, data: {} }),
      'Remove a passkey',
    );
  }
  await ok(await api.post(`/admin/api/person/${personId}/sign-in/sign-out-everywhere`, { headers, data: {} }), 'Sign out everywhere');
}
