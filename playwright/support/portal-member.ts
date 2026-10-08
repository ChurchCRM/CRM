import { type APIRequestContext, type APIResponse, expect, type Page, request } from '@playwright/test';
import path from 'node:path';

import { BASE_URL, LOCALE, LOCALE_UI_VALUE } from './env';
import { humanPause, humanType } from './human';

/**
 * A self-service ("Edit Self" only) login for one demo member, created through
 * the admin user editor and deleted again afterwards, so the Member Portal
 * captures sign in as a real member rather than as the admin.
 *
 * Sarah Carter is an adult of the demo Carter family (src/admin/demo/people.json):
 * both parents have photos, and no other capture uses the family.
 */
export const PORTAL_MEMBER = {
  login: 'sarah.carter@demo.churchcrm.io',
  password: 'PortalDemo!2026',
};

const ADMIN_STATE_PATH = path.join(__dirname, '..', '.auth', 'admin.json');

export interface PortalMember {
  personId: number;
  familyId: number;
}

export function adminRequest(): Promise<APIRequestContext> {
  return request.newContext({ baseURL: BASE_URL, storageState: ADMIN_STATE_PATH, timeout: 60000 });
}

export async function ok(response: APIResponse, what: string): Promise<APIResponse> {
  if (!response.ok()) {
    throw new Error(`${what} failed: ${response.status()} ${(await response.text()).slice(0, 300)}`);
  }
  return response;
}

async function csrfToken(api: APIRequestContext, url: string): Promise<string> {
  const html = await (await ok(await api.get(url), `GET ${url}`)).text();
  const token = html.match(/name="csrf_token" value="([^"]+)"/)?.[1];
  if (!token) {
    throw new Error(`No CSRF token on ${url}`);
  }
  return token;
}

async function deletePendingFamilyMembers(api: APIRequestContext, familyId: number): Promise<void> {
  const { people } = await (await ok(await api.get('/api/persons/self-register'), 'List self-registrations')).json();
  for (const person of people as Array<{ Id: number; FamId: number }>) {
    if (Number(person.FamId) === familyId) {
      await ok(await api.delete(`/api/person/${person.Id}`), `Delete pending person ${person.Id}`);
    }
  }
}

async function createPortalMember(api: APIRequestContext): Promise<PortalMember> {
  const search = `/api/persons/search/${encodeURIComponent(PORTAL_MEMBER.login)}`;
  const matches = await (await ok(await api.get(search), 'Find member')).json();
  const personId = Number(matches[0]?.objid);
  expect(personId, `demo person ${PORTAL_MEMBER.login}`).toBeGreaterThan(0);
  const person = await (await ok(await api.get(`/api/person/${personId}`), 'Read member')).json();
  const member = { personId, familyId: Number(person.FamId) };

  // A run that stopped half way leaves its login and proposed family member behind.
  await api.delete(`/admin/api/user/${personId}/`);
  await deletePendingFamilyMembers(api, member.familyId);

  const editor = `/admin/system/users/new?personId=${personId}`;
  await ok(
    await api.post('/admin/system/users/new', {
      form: {
        csrf_token: await csrfToken(api, editor),
        PersonID: String(personId),
        UserName: PORTAL_MEMBER.login,
        accessMode: 'self',
      },
    }),
    'Create self-service user',
  );
  await ok(await api.get(`/admin/api/user/${personId}/permissions`), 'Read new user');

  const passwordForm = `/admin/system/user/${personId}/changePassword`;
  await ok(
    await api.post(passwordForm, {
      form: {
        csrf_token: await csrfToken(api, passwordForm),
        NewPassword1: PORTAL_MEMBER.password,
        NewPassword2: PORTAL_MEMBER.password,
        Submit: 'Save',
      },
    }),
    'Set member password',
  );

  if (LOCALE !== 'en' && LOCALE_UI_VALUE) {
    await ok(
      await api.post(`/api/user/${personId}/setting/ui.locale`, { data: { value: LOCALE_UI_VALUE } }),
      'Set member locale',
    );
  }

  return member;
}

async function removePortalMember(api: APIRequestContext, member: PortalMember): Promise<void> {
  await ok(await api.delete(`/admin/api/user/${member.personId}/`), 'Delete self-service user');
  await deletePendingFamilyMembers(api, member.familyId);
}

interface PortalCalendarChoice {
  type: string;
  id: number;
  visible: boolean;
}

export interface PortalDemo {
  member: PortalMember;
  tearDown(): Promise<void>;
}

/**
 * The member login plus a calendar worth looking at. The demo events are on no
 * calendar and the portal shows only the calendars an administrator shares, so
 * the events are pinned to the church calendar, which is shared together with
 * the Holidays plugin calendar. tearDown() puts every piece back.
 */
export async function setUpPortalDemo(api: APIRequestContext): Promise<PortalDemo> {
  const undo: Array<() => Promise<unknown>> = [];
  const tearDown = async () => {
    const failures: unknown[] = [];
    for (const step of undo.reverse()) {
      await step().catch((error) => failures.push(error));
    }
    if (failures.length > 0) {
      throw failures[0];
    }
  };

  try {
    const member = await createPortalMember(api);
    undo.push(() => removePortalMember(api, member));

    const { calendars } = await (
      await ok(await api.get('/admin/api/member-portal/calendars'), 'List portal calendars')
    ).json();
    const choices = calendars as PortalCalendarChoice[];
    const shared = choices.filter((c) => c.visible).map(({ type, id }) => ({ type, id }));
    const church =
      choices.find((c) => c.type === 'calendar' && c.visible) ?? choices.find((c) => c.type === 'calendar');
    const holidays = choices.find((c) => c.type === 'system' && c.id >= 10000);
    if (!church || !holidays) {
      throw new Error('Expected a church calendar and the Holidays plugin calendar');
    }

    undo.push(async () =>
      ok(
        await api.post('/admin/api/member-portal/calendars', { data: { visible: shared } }),
        'Restore portal calendars',
      ),
    );
    await ok(
      await api.post('/admin/api/member-portal/calendars', {
        data: { visible: [church, holidays].map(({ type, id }) => ({ type, id })) },
      }),
      'Share portal calendars',
    );

    const { Events } = await (await ok(await api.get('/api/events'), 'List events')).json();
    for (const event of Events as Array<{ Id: number; PinnedCalendars: number[] }>) {
      undo.push(async () =>
        ok(
          await api.post(`/api/events/${event.Id}`, { data: { PinnedCalendars: event.PinnedCalendars } }),
          `Restore event ${event.Id}`,
        ),
      );
      await ok(
        await api.post(`/api/events/${event.Id}`, { data: { PinnedCalendars: [church.id] } }),
        `Pin event ${event.Id}`,
      );
    }

    return { member, tearDown };
  } catch (error) {
    await tearDown().catch(() => undefined);
    throw error;
  }
}

export async function signInAsPortalMember(page: Page): Promise<void> {
  await page.goto('/login');
  await humanType(page.locator('input[name=User]'), PORTAL_MEMBER.login);
  await humanType(page.locator('input[name=Password]'), PORTAL_MEMBER.password);
  await humanPause(page, 400);
  await page.locator('input[name=Password]').press('Enter', { noWaitAfter: true });
  await page.waitForURL(/\/portal\/?$/, { timeout: 60000 });
  await expect(page.locator('.portal-shell')).toBeVisible({ timeout: 15000 });
}

/** Member photos are lazy-loaded, and the capture must not catch one half drawn. */
export async function waitForAvatars(page: Page): Promise<void> {
  await page.waitForFunction(
    () =>
      Array.from(document.querySelectorAll<HTMLImageElement>('img.portal-avatar')).every(
        (img) => img.complete && img.naturalWidth > 0,
      ),
    undefined,
    { timeout: 30000 },
  );
}

/** The portal's session-wide CSRF token, for calling /api/portal/* as the member. */
export function portalCsrfToken(memberRequest: APIRequestContext): Promise<string> {
  return csrfToken(memberRequest, '/portal/family/edit');
}
