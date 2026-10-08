import { type Browser, expect, type Page } from '@playwright/test';
import path from 'node:path';

import { BASE_URL } from './env';
import { humanClick, humanSelect } from './human';

/**
 * Volunteer Management v2 fixture for the volunteer captures. V2 is off on a
 * fresh install, so the seed turns it on in Admin → Ministry Settings and
 * builds a "Hospitality" ministry through the volunteer API; removeVolunteerSeed()
 * takes both back out, because every workflow shares one database.
 *
 * Volunteers are demo people looked up by their demo email, from families no
 * other spec uses. Emily Evans gets a self-service login for the Member Portal.
 */

export const STORAGE_STATE_PATH = path.join(__dirname, '..', '.auth', 'admin.json');

const API = '/api/ministries';
const MINISTRY_NAME = 'Hospitality';
const EVENT_TITLE = 'Sunday Coffee Bar';

const MEMBER_USERNAME = 'emily.evans';
// Throwaway account on the pipeline's own fresh instance, deleted by removeVolunteerSeed().
const MEMBER_PASSWORD = 'PortalVolunteer!2026';

const PEOPLE = {
  emily: 'emily.evans@demo.churchcrm.io',
  sarah: 'sarah.carter@demo.churchcrm.io',
  paul: 'paul.mitchell@demo.churchcrm.io',
  kevin: 'kevin.thomas@demo.churchcrm.io',
  linda: 'linda.brown@demo.churchcrm.io',
  nancy: 'nancy.robinson@demo.churchcrm.io',
  laura: 'laura.lee@demo.churchcrm.io',
  ryan: 'ryan.nelson@demo.churchcrm.io',
  michelle: 'michelle.young@demo.churchcrm.io',
  edward: 'edward.lewis@demo.churchcrm.io',
} as const;
export type Volunteer = keyof typeof PEOPLE;

export type PositionKey = 'barista' | 'setup' | 'cleanup' | 'door' | 'desk';

const QUALIFICATIONS: Record<PositionKey, Volunteer[]> = {
  barista: ['sarah', 'paul', 'linda', 'michelle'],
  setup: ['kevin', 'paul', 'emily', 'ryan', 'laura'],
  cleanup: ['kevin', 'emily', 'linda', 'edward', 'nancy'],
  door: ['laura', 'ryan', 'michelle', 'edward'],
  desk: ['michelle', 'nancy', 'emily'],
};

export interface VolunteerSeed {
  ministryId: number;
  personIds: Record<Volunteer, number>;
  positionIds: Record<PositionKey, number>;
  eventIds: number[];
  /** The Coffee Bar on the first Sunday: Full, Covered and Needs 2 more side by side. */
  coffeeOccurrenceId: number;
}

let seed: VolunteerSeed | null = null;
let memberLoginCreated = false;

function isoDate(offsetDays: number): string {
  const d = new Date();
  d.setHours(12, 0, 0, 0);
  d.setDate(d.getDate() + offsetDays);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function daysToNextSunday(): number {
  return (7 - new Date().getDay()) % 7 || 7;
}

async function api<T = Record<string, unknown>>(page: Page, method: string, url: string, data?: unknown): Promise<T> {
  const response = await page.request.fetch(url, { method, data });
  if (!response.ok()) {
    throw new Error(`${method} ${url} failed: ${response.status()} ${await response.text()}`);
  }
  return (await response.json()) as T;
}

/**
 * The Add Person / Add Family buttons float over the bottom-right corner and tuck
 * themselves away five seconds after the page loads (Footer.js initializeFAB).
 * Waiting for that keeps them off the staffing badges at tablet width.
 */
export async function waitForFloatingButtonsToTuck(page: Page): Promise<void> {
  const fab = page.locator('#fab-container .fab-button');
  if ((await fab.count()) > 0) {
    await expect(fab.first()).toHaveClass(/fab-hidden/, { timeout: 15000 });
  }
}

/** Ministry Settings is the one home of the V1 / V2 / Both switch. */
export async function setVolunteerVersion(page: Page, version: 'v1' | 'v2'): Promise<void> {
  await page.goto('/admin/ministry-settings');
  const select = page.locator('#ministrySettingsPanel select[name="sVolunteerVersion"]');
  await expect(select).toBeEnabled({ timeout: 30000 });
  if ((await select.inputValue()) === version) {
    return;
  }
  await humanSelect(select, version);
  const saved = page.waitForResponse(
    (r) => r.url().includes('/admin/api/system/config/') && r.request().method() === 'POST'
  );
  await humanClick(page.locator('#ministrySettingsPanel .settings-panel-save'));
  await saved;
  // onSave reloads the page so the menu follows the new rollout state.
  await page.waitForEvent('load', { timeout: 30000 });
  await expect(page.locator('#ministrySettingsPanel select[name="sVolunteerVersion"]')).toHaveValue(version, {
    timeout: 30000,
  });
}

async function personId(page: Page, email: string): Promise<number> {
  const matches = await api<Array<{ objid: number }>>(page, 'GET', `/api/persons/search/${encodeURIComponent(email)}`);
  if (matches.length !== 1) {
    throw new Error(`Expected one demo person for ${email}, found ${matches.length}`);
  }
  return matches[0].objid;
}

async function deleteMinistry(page: Page, ministryId: number): Promise<void> {
  const { events } = await api<{ events: Array<{ id: number }> }>(page, 'GET', `${API}/ministries/${ministryId}/events`);
  if (events.length > 0) {
    await api(page, 'DELETE', `${API}/ministries/${ministryId}/events`, { eventIds: events.map((e) => e.id) });
  }
  await api(page, 'POST', `${API}/ministries/${ministryId}`, { active: false });
  await api(page, 'DELETE', `${API}/ministries/${ministryId}`);
}

async function deleteMemberLogin(page: Page): Promise<void> {
  const emilyId = await personId(page, PEOPLE.emily);
  if ((await page.request.get(`/admin/api/user/${emilyId}/permissions`)).ok()) {
    await api(page, 'DELETE', `/admin/api/user/${emilyId}/`);
  }
  memberLoginCreated = false;
}

async function deleteHospitality(page: Page): Promise<void> {
  const { ministries } = await api<{ ministries: Array<{ id: number; name: string }> }>(
    page,
    'GET',
    `${API}/ministries`
  );
  for (const ministry of ministries.filter((m) => m.name === MINISTRY_NAME)) {
    await deleteMinistry(page, ministry.id);
  }
}

async function assign(page: Page, occurrenceId: number, positionId: number, volunteerId: number, accepted: boolean) {
  const { assignment } = await api<{ assignment: { id: number } }>(
    page,
    'POST',
    `${API}/occurrences/${occurrenceId}/assignments`,
    { positionId, personId: volunteerId }
  );
  if (accepted) {
    await api(page, 'POST', `${API}/assignments/${assignment.id}/status`, { status: 'accepted' });
  }
}

/**
 * Hospitality: a Coffee Bar team and a Greeters team, both serving a weekly
 * "Sunday Coffee Bar" on the ministry's own calendar for the next three
 * Sundays, partly staffed so every status word has somewhere to show.
 */
export async function ensureVolunteerSeed(page: Page): Promise<VolunteerSeed> {
  if (seed) {
    return seed;
  }
  await setVolunteerVersion(page, 'v2');
  await deleteHospitality(page);

  const personIds = {} as Record<Volunteer, number>;
  for (const key of Object.keys(PEOPLE) as Volunteer[]) {
    personIds[key] = await personId(page, PEOPLE[key]);
  }

  const { ministry } = await api<{ ministry: { id: number } }>(page, 'POST', `${API}/ministries`, {
    name: MINISTRY_NAME,
    description: 'Every guest is welcomed at the door and offered a warm cup of coffee before the service.',
  });
  const ministryId = ministry.id;
  await api(page, 'POST', `${API}/ministries/${ministryId}`, {
    helpWanted: true,
    helpWantedText: 'We would love a few more hands at the coffee bar on Sunday mornings.',
  });

  const detail = await api<{ teams: Array<{ id: number }> }>(page, 'GET', `${API}/ministries/${ministryId}`);
  const coffeeTeamId = detail.teams[0].id;
  await api(page, 'POST', `${API}/teams/${coffeeTeamId}`, {
    name: 'Coffee Bar',
    description: 'Brews, serves and tidies up the Sunday coffee bar.',
  });
  const { team: greeters } = await api<{ team: { id: number } }>(page, 'POST', `${API}/ministries/${ministryId}/teams`, {
    name: 'Greeters',
    description: 'Welcomes people at the doors and the welcome desk.',
  });

  const positionPlan: Array<[PositionKey, string, number]> = [
    ['barista', 'Barista', coffeeTeamId],
    ['setup', 'Setup Crew', coffeeTeamId],
    ['cleanup', 'Cleanup Crew', coffeeTeamId],
    ['door', 'Door Greeter', greeters.id],
    ['desk', 'Welcome Desk', greeters.id],
  ];
  const positionIds = {} as Record<PositionKey, number>;
  for (const [order, [key, name, teamId]] of positionPlan.entries()) {
    const { position } = await api<{ position: { id: number } }>(
      page,
      'POST',
      `${API}/ministries/${ministryId}/positions`,
      { name, teamId, order: order + 1 }
    );
    positionIds[key] = position.id;
  }

  for (const key of Object.keys(PEOPLE) as Volunteer[]) {
    await api(page, 'POST', `${API}/ministries/${ministryId}/pool/${personIds[key]}`);
  }
  for (const [key, volunteers] of Object.entries(QUALIFICATIONS) as Array<[PositionKey, Volunteer[]]>) {
    for (const volunteer of volunteers) {
      await api(page, 'POST', `${API}/positions/${positionIds[key]}/qualifications`, {
        personId: personIds[volunteer],
        notes: '',
      });
    }
  }

  const { eventTypes } = await api<{ eventTypes: Array<{ id: number; name: string }> }>(
    page,
    'GET',
    `${API}/event-types`
  );
  const serviceType = eventTypes.find((t) => t.name === 'Church Service') ?? eventTypes[0];
  const firstSunday = daysToNextSunday();
  const rangeStart = isoDate(firstSunday);
  const rangeEnd = isoDate(firstSunday + 14);
  const { events } = await api<{ events: Array<{ id: number }> }>(page, 'POST', `${API}/ministries/${ministryId}/events`, {
    title: EVENT_TITLE,
    eventTypeId: serviceType.id,
    description: 'Coffee and pastries in the fellowship hall before worship.',
    startTime: '09:15',
    endTime: '10:30',
    recurrence: { type: 'weekly', dow: 'Sunday' },
    rangeStart,
    rangeEnd,
  });

  const schedule = (name: string, teamId: number, requirements: unknown[]) =>
    api(page, 'POST', `${API}/ministries/${ministryId}/schedules`, {
      name,
      linkMode: 'ministry',
      titleFilter: EVENT_TITLE,
      teamId,
      windowStart: rangeStart,
      requirements,
    });
  await schedule('Coffee Bar — Sundays', coffeeTeamId, [
    {
      positionId: positionIds.barista,
      minCount: 2,
      maxCount: 2,
      defaults: [
        { personId: personIds.sarah, accepted: true },
        { personId: personIds.paul, accepted: true },
      ],
    },
    { positionId: positionIds.setup, minCount: 1, maxCount: 2 },
    { positionId: positionIds.cleanup, minCount: 2, maxCount: 2 },
  ]);
  await schedule('Greeters — Sundays', greeters.id, [
    { positionId: positionIds.door, minCount: 2, maxCount: 3 },
    { positionId: positionIds.desk, minCount: 1, maxCount: 1 },
  ]);

  const { occurrences } = await api<{ occurrences: Array<{ id: number; teamId: number; occurrenceDate: string }> }>(
    page,
    'GET',
    `${API}/occurrences?from=${rangeStart}&to=${rangeEnd}&ministryId=${ministryId}`
  );
  const on = (teamId: number, week: number): number => {
    const date = isoDate(firstSunday + week * 7);
    const match = occurrences.find((o) => o.teamId === teamId && o.occurrenceDate === date);
    if (!match) {
      throw new Error(`No occurrence for team ${teamId} on ${date}`);
    }
    return match.id;
  };

  const coffeeOccurrenceId = on(coffeeTeamId, 0);
  const plan: Array<[number, PositionKey, Volunteer, boolean]> = [
    [coffeeOccurrenceId, 'setup', 'kevin', true],
    [on(greeters.id, 0), 'door', 'laura', true],
    [on(greeters.id, 0), 'door', 'ryan', true],
    [on(greeters.id, 0), 'desk', 'emily', false],
    [on(coffeeTeamId, 1), 'setup', 'ryan', true],
    [on(coffeeTeamId, 1), 'cleanup', 'linda', true],
    [on(coffeeTeamId, 1), 'cleanup', 'kevin', true],
    [on(greeters.id, 1), 'door', 'michelle', true],
    [on(greeters.id, 1), 'door', 'edward', false],
    [on(greeters.id, 1), 'desk', 'nancy', true],
    [on(coffeeTeamId, 2), 'setup', 'emily', true],
    [on(coffeeTeamId, 2), 'cleanup', 'nancy', true],
    [on(greeters.id, 2), 'door', 'laura', true],
    [on(greeters.id, 2), 'door', 'michelle', true],
  ];
  for (const [occurrenceId, position, volunteer, accepted] of plan) {
    await assign(page, occurrenceId, positionIds[position], personIds[volunteer], accepted);
  }

  seed = { ministryId, personIds, positionIds, eventIds: events.map((e) => e.id), coffeeOccurrenceId };
  return seed;
}

/** Seeds from a context of its own, so a recording starts on the first real step. */
export async function seedVolunteerFixture(browser: Browser): Promise<VolunteerSeed> {
  const context = await browser.newContext({ baseURL: BASE_URL, storageState: STORAGE_STATE_PATH });
  try {
    return await ensureVolunteerSeed(await context.newPage());
  } finally {
    await context.close();
  }
}

/** Removes the ministry, its events and Emily's login, and turns V2 back off. */
export async function removeVolunteerSeed(browser: Browser): Promise<void> {
  const context = await browser.newContext({ baseURL: BASE_URL, storageState: STORAGE_STATE_PATH });
  const page = await context.newPage();
  try {
    await setVolunteerVersion(page, 'v2');
    await deleteMemberLogin(page);
    await deleteHospitality(page);
    await setVolunteerVersion(page, 'v1');
  } finally {
    seed = null;
    memberLoginCreated = false;
    await context.close();
  }
}

/** A self-service login for Emily, made the way an administrator makes one. */
async function ensureMemberLogin(page: Page, emilyId: number): Promise<void> {
  if (memberLoginCreated) {
    return;
  }
  await deleteMemberLogin(page);

  await page.goto(`/admin/system/users/new?personId=${emilyId}`);
  await page.locator('#UserName').fill(MEMBER_USERNAME);
  await page.locator('input[name="accessMode"][value="self"]').check({ force: true });
  await page.locator('#SaveButton').click();
  await page.waitForURL(/\/admin\/system\/users$/, { timeout: 30000 });

  await page.goto(`/admin/system/user/${emilyId}/changePassword`);
  await page.locator('#NewPassword1').fill(MEMBER_PASSWORD);
  await page.locator('#NewPassword2').fill(MEMBER_PASSWORD);
  await page.locator('input[type="submit"][name="Submit"]').click();
  await page.waitForLoadState('load');
  memberLoginCreated = true;
}

/** Emily, signed in to the Member Portal in a browser context of her own. */
export async function memberPortalPage(admin: Page, browser: Browser): Promise<Page> {
  const { personIds } = await ensureVolunteerSeed(admin);
  await ensureMemberLogin(admin, personIds.emily);

  // Playwright Test hands the project's storageState to every new context. Signing in
  // on top of the admin cookie would regenerate that session id and end the admin
  // session that every later workflow shares.
  const context = await browser.newContext({
    storageState: { cookies: [], origins: [] },
    baseURL: BASE_URL,
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
    timezoneId: 'America/Chicago',
  });
  const page = await context.newPage();
  await page.goto('/session/begin');
  await page.locator('input[name=User]').fill(MEMBER_USERNAME);
  await page.locator('input[name=Password]').fill(MEMBER_PASSWORD);
  await page.locator('input[name=Password]').press('Enter');
  await page.waitForURL(/\/portal/, { timeout: 30000 });
  return page;
}
