import { type APIRequestContext, type APIResponse, expect, type Page, request } from '@playwright/test';
import path from 'node:path';

import { BASE_URL, LOCALE, LOCALE_UI_VALUE } from './env';

/**
 * The demo member the Member Portal captures sign in as, with their own email
 * (members have no user account since the 7.8.3 cutover).
 *
 * George Carter is the head of the demo Carter family (src/admin/demo/people.json):
 * both parents have photos, and no other capture signs in as anyone in the
 * family. He is not one of member-sign-in.ts's SIGN_IN_MEMBERS, whose devices
 * that workflow signs out, so his remembered device lasts across locale passes.
 */
export const PORTAL_MEMBER = {
  email: 'george.carter@demo.churchcrm.io',
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

async function findPortalMember(api: APIRequestContext): Promise<PortalMember> {
  const search = `/api/persons/search/${encodeURIComponent(PORTAL_MEMBER.email)}`;
  const matches = await (await ok(await api.get(search), 'Find member')).json();
  const personId = Number(matches[0]?.objid);
  expect(personId, `demo person ${PORTAL_MEMBER.email}`).toBeGreaterThan(0);
  const person = await (await ok(await api.get(`/api/person/${personId}`), 'Read member')).json();
  const member = { personId, familyId: Number(person.FamId) };

  // A run that stopped half way leaves its proposed family member behind.
  await deletePendingFamilyMembers(api, member.familyId);

  return member;
}

async function setConfig(api: APIRequestContext, name: string, value: string): Promise<void> {
  await ok(await api.post(`/admin/api/system/config/${name}`, { data: { value } }), `Set ${name}`);
}

/**
 * A member has no account to keep a language on, so the portal speaks the
 * church's language: for another locale pass it is set to that locale, and
 * the returned step puts it back.
 */
async function useLocaleForMembers(api: APIRequestContext): Promise<() => Promise<void>> {
  if (LOCALE === 'en' || !LOCALE_UI_VALUE) {
    return async () => undefined;
  }
  const { value } = await (await ok(await api.get('/admin/api/system/config/sLanguage'), 'Read sLanguage')).json();
  await setConfig(api, 'sLanguage', LOCALE_UI_VALUE);
  return () => setConfig(api, 'sLanguage', String(value ?? ''));
}

interface PortalCalendarChoice {
  type: string;
  id: number;
  visible: boolean;
}

export interface PortalDemo {
  member: PortalMember;
  /** How many calendars the portal shares while the captures run. */
  sharedCalendarCount: number;
  tearDown(): Promise<void>;
}

/**
 * The member, in the church's language for this pass, plus a calendar worth looking at:
 * the calendars the demo import shares, with the Holidays plugin calendar beside them.
 * tearDown() puts every piece back.
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
    const member = await findPortalMember(api);
    undo.push(() => deletePendingFamilyMembers(api, member.familyId));
    undo.push(await useLocaleForMembers(api));

    const { calendars } = await (
      await ok(await api.get('/admin/api/member-portal/calendars'), 'List portal calendars')
    ).json();
    const choices = calendars as PortalCalendarChoice[];
    const shared = choices.filter((c) => c.visible).map(({ type, id }) => ({ type, id }));
    const holidays = choices.find((c) => c.type === 'system' && c.id >= 10000);
    if (!shared.some((c) => c.type === 'calendar') || !holidays) {
      throw new Error('Expected the demo church calendars and the Holidays plugin calendar');
    }

    undo.push(async () =>
      ok(
        await api.post('/admin/api/member-portal/calendars', { data: { visible: shared } }),
        'Restore portal calendars',
      ),
    );
    const visible = shared.some((c) => c.type === 'system' && c.id === holidays.id)
      ? shared
      : [...shared, { type: holidays.type, id: holidays.id }];
    await ok(
      await api.post('/admin/api/member-portal/calendars', { data: { visible } }),
      'Share portal calendars',
    );

    return { member, sharedCalendarCount: visible.length, tearDown };
  } catch (error) {
    await tearDown().catch(() => undefined);
    throw error;
  }
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
