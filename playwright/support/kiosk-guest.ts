import type { APIRequestContext, Browser, BrowserContext, Cookie } from '@playwright/test';

import { BASE_URL } from './env';
import { adminRequest, ok } from './portal-member';

/** Walk-in guest used by the kiosk captures. Not in src/admin/demo/people.json. */
export const KIOSK_GUEST = {
  firstName: 'Naomi',
  lastName: 'Fairchild',
  phone: '(555) 010-2200',
  email: 'naomi.fairchild@demo.churchcrm.io',
};

const DEMO_CLASS = 'Class 1-3';
const EVENT_ASSIGNMENT = 1;
// Matches playwright.config.ts timezoneId and the demo data's sTimeZone.
const CHURCH_TIME_ZONE = 'America/Chicago';

export interface KioskDemo {
  api: APIRequestContext;
  /** Puts the registered kiosk's cookie into a browser context so its pages open the kiosk screen. */
  attachKiosk: (context: BrowserContext) => Promise<void>;
  /** Deletes the guests, the kiosk and the event, and restores the registration window. */
  tearDown: () => Promise<void>;
}

/**
 * Registers a kiosk and assigns it to a demo Sunday School event, the way
 * Kiosk Manager does: open the registration window, let a separate (unrecorded)
 * browser register itself, accept it, assign the event.
 */
export async function setUpKioskDemo(browser: Browser): Promise<KioskDemo> {
  const api = await adminRequest();

  const groups = await (await ok(await api.get('/api/groups/'), 'List groups')).json();
  const group = (Array.isArray(groups) ? groups : groups.groups).find(
    (g: { Name?: string; name?: string }) => (g.Name ?? g.name) === DEMO_CLASS
  );
  if (!group) {
    throw new Error(`Demo group "${DEMO_CLASS}" not found.`);
  }

  const quickCreate = await (
    await ok(await api.post('/api/events/quick-create', { data: { groupId: Number(group.Id ?? group.id) } }), 'Create event')
  ).json();
  const eventId = Number(quickCreate.eventId);

  // quick-create uses the event type's fixed hours, which may already be over; the kiosk
  // shows "Event Has Ended" then. Run it from an hour ago to two hours ahead, church time.
  const churchClock = (offsetHours: number): string =>
    new Date(Date.now() + offsetHours * 3_600_000)
      .toLocaleString('sv-SE', { timeZone: CHURCH_TIME_ZONE })
      .replace(/:\d\d:\d\d$/, ':00:00');
  await ok(await api.post(`/api/events/${eventId}/time`, { data: { startTime: churchClock(-1), endTime: churchClock(2) } }), 'Set event time');

  const originalWindow = await (await ok(await api.get('/admin/api/system/config/sKioskVisibilityTimestamp'), 'Read window')).json();
  const knownIds = new Set(
    ((await (await ok(await api.get('/kiosk/api/devices'), 'List kiosks')).json()).KioskDevices ?? []).map((k: { Id: number }) => k.Id)
  );

  await ok(await api.post('/kiosk/api/allowRegistration'), 'Open kiosk registration');
  const registering = await browser.newContext({ baseURL: BASE_URL });
  await (await registering.newPage()).goto('/kiosk/');
  const kioskCookies: Cookie[] = (await registering.cookies()).filter((c) => c.name === 'kioskCookie');
  await registering.close();
  if (kioskCookies.length === 0) {
    throw new Error('Kiosk did not receive a kioskCookie — was the registration window open?');
  }

  const devices = (await (await ok(await api.get('/kiosk/api/devices'), 'List kiosks')).json()).KioskDevices ?? [];
  const device = devices.find((k: { Id: number }) => !knownIds.has(k.Id));
  if (!device) {
    throw new Error('The new kiosk is not in the device list.');
  }
  await ok(await api.post(`/kiosk/api/devices/${device.Id}/accept`), 'Accept kiosk');
  await ok(
    await api.post(`/kiosk/api/devices/${device.Id}/assignment`, { data: { assignmentType: EVENT_ASSIGNMENT, eventId } }),
    'Assign event'
  );

  return {
    api,
    attachKiosk: (context) => context.addCookies(kioskCookies),
    tearDown: async () => {
      try {
        const { people } = await (await ok(await api.get('/api/persons/self-register'), 'List self-registrations')).json();
        for (const person of people as Array<{ Id: number; LastName: string }>) {
          if (person.LastName === KIOSK_GUEST.lastName) {
            await api.delete(`/api/person/${person.Id}`);
          }
        }
        await api.delete(`/kiosk/api/devices/${device.Id}`);
        await api.post(`/api/events/${eventId}/status`, { data: { active: false } });
        await api.delete(`/api/events/${eventId}`);
        await api.post('/admin/api/system/config/sKioskVisibilityTimestamp', { data: { value: originalWindow.value ?? '' } });
      } finally {
        await api.dispose();
      }
    },
  };
}
