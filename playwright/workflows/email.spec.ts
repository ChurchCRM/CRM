import { type APIRequestContext, expect, type Locator, type Page, request, test } from '@playwright/test';

import { captureScreen, defineCapture } from '../support/capture';
import { BASE_URL } from '../support/env';
import { humanClick, humanPause, settle } from '../support/human';
import { findDemoPerson } from '../support/member-sign-in';

const ANDREW_EMAIL = 'andrew.baker@demo.churchcrm.io';

const MAIL = { sSMTPHost: 'mailserver:1025', bEnabledEmail: '1' };

async function configValue(api: APIRequestContext, name: string): Promise<string> {
  const { value } = await (await api.get(`/admin/api/system/config/${name}`)).json();
  return String(value ?? '');
}

async function setConfig(api: APIRequestContext, name: string, value: string): Promise<void> {
  expect((await api.post(`/admin/api/system/config/${name}`, { data: { value } })).ok()).toBeTruthy();
}

/** Outgoing mail through the capture stack's Mailpit. Member sign-in stays off. */
async function enableOutgoingMail(api: APIRequestContext): Promise<() => Promise<void>> {
  const saved: Record<string, string> = {};
  for (const name of Object.keys(MAIL)) {
    saved[name] = await configValue(api, name);
    await setConfig(api, name, MAIL[name as keyof typeof MAIL]);
  }
  return async () => {
    const restoreApi = await request.newContext({
      baseURL: BASE_URL,
      storageState: 'playwright/.auth/admin.json',
    });
    try {
      for (const [name, value] of Object.entries(saved)) {
        await setConfig(restoreApi, name, value);
      }
    } finally {
      await restoreApi.dispose();
    }
  };
}

async function keepInFrame(page: Page, top: string, bottom: string): Promise<void> {
  await page.evaluate(
    ([topSelector, bottomSelector]) => {
      const frame = () => {
        const first = document.querySelector(topSelector);
        const last = document.querySelector(bottomSelector);
        if (!first || !last) return;
        const bar = document.querySelector('header.navbar.sticky-top')?.getBoundingClientRect().height ?? 0;
        const start = first.getBoundingClientRect().top + window.scrollY - bar - 16;
        const end = last.getBoundingClientRect().bottom + window.scrollY + 16;
        const target = end - start <= window.innerHeight - bar ? start : end - window.innerHeight;
        window.scrollTo({ top: target, behavior: 'instant' });
      };
      frame();
      window.addEventListener('resize', frame);
    },
    [top, bottom],
  );
}

async function hideFloatingButtons(page: Page): Promise<void> {
  await expect(page.locator('#fab-container .fab-button:visible')).toHaveCount(0, { timeout: 15000 });
}

async function openAndrew(page: Page): Promise<void> {
  await page.goto('/people/family');
  const rows = page.locator('#families tbody tr');
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  await page.locator('.dt-search input').fill(ANDREW_EMAIL);
  const row = rows.filter({ hasText: ANDREW_EMAIL }).first();
  await expect(row).toBeVisible({ timeout: 15000 });
  await humanClick(row.locator('a[href*="/people/family/"]').first());
  await page.waitForURL(/\/people\/family\/\d+/, { timeout: 15000 });
  await humanClick(page.getByRole('link', { name: 'Andrew Baker' }).first());
  await expect(page.locator('.card:has(h6 .fa-envelope) [data-email-composer]')).toBeVisible({ timeout: 15000 });
}

async function openComposerForm(page: Page): Promise<void> {
  await humanClick(page.locator('.card:has(h6 .fa-envelope) [data-email-composer]'));
  await expect(page.locator('#crm-email-composer-modal')).toBeVisible();
  await humanClick(page.locator('#crm-email-send-btn'));
  const body = page.locator('#crm-email-body');
  await expect(body).toBeVisible();
  await expect(body).not.toHaveValue('');
  await page.locator('#crm-email-subject').fill('A note from the office');
  await body.click();
  await body.press('Home');
  await body.pressSequentially('We are glad you are with us this week.');
  await humanPause(page, 300);
}

/** Saves the person editor with a new personal email, without leaving the open page. */
async function setPersonEmail(page: Page, personId: number, email: string): Promise<void> {
  const html = await (await page.request.get(`/PersonEditor.php?PersonID=${personId}`)).text();
  const status = await page.evaluate(
    async ({ html, email, personId }) => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const form = doc.querySelector<HTMLFormElement>('form:has([name="Email"])');
      if (!form) return 0;
      const data = new FormData(form);
      data.set('Email', email);
      data.set('PersonSubmit', 'Save');
      const res = await fetch(`/PersonEditor.php?PersonID=${personId}`, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
      });
      return res.status;
    },
    { html, email, personId },
  );
  expect(status).toBeGreaterThanOrEqual(200);
  expect(status).toBeLessThan(400);
}

test.describe('Email composer', () => {
  let restoreMail: (() => Promise<void>) | undefined;

  test.beforeAll(async ({ request }) => {
    restoreMail = await enableOutgoingMail(request);
  });

  test.afterAll(async () => {
    await restoreMail?.();
  });

  const personButton = defineCapture({
    name: 'email-person-view-send-button',
    title: 'Email a Person',
    category: 'Communication',
    purpose: 'Show the paper-plane button next to the email address on the Person View',
  });

  test('email-person-view-send-button', personButton, async ({ page }, testInfo) => {
    await openAndrew(page);
    await hideFloatingButtons(page);
    await keepInFrame(page, '.card:has(h6 .fa-envelope)', '.card:has(h6 .fa-envelope) [data-email-composer]');
    await settle(page, 600);
    await captureScreen(page, testInfo, personButton);
  });

  const familyButtons = defineCapture({
    name: 'email-person-view-family-send-buttons',
    title: 'Email a Family Member',
    category: 'Communication',
    purpose: 'Show the paper-plane buttons on the other family members who have an email address',
  });

  test('email-person-view-family-send-buttons', familyButtons, async ({ page }, testInfo) => {
    await openAndrew(page);
    const tableButton = page.locator('.card:has(.fa-people-roof) [data-email-composer]');
    await expect(tableButton.first()).toBeVisible();
    await hideFloatingButtons(page);
    await keepInFrame(page, '.card:has(.fa-people-roof) .card-header', '.card:has(.fa-people-roof) table');
    await settle(page, 600);
    await captureScreen(page, testInfo, familyButtons);
  });

  const composerForm = defineCapture({
    name: 'email-composer-form',
    title: 'Write an Email',
    category: 'Communication',
    purpose: 'Show the composer with a subject, the message, and the pre-filled closing',
  });

  test('email-composer-form', composerForm, async ({ page }, testInfo) => {
    await openAndrew(page);
    await openComposerForm(page);
    await hideFloatingButtons(page);
    await settle(page, 600);
    await captureScreen(page, testInfo, composerForm);
  });

  const preview = defineCapture({
    name: 'email-composer-preview',
    title: 'Preview an Email',
    category: 'Communication',
    purpose: 'Show the message as the recipient will receive it, with the church header',
    usedIn: ['docs'],
  });

  test('email-composer-preview', preview, async ({ page }, testInfo) => {
    await openAndrew(page);
    await openComposerForm(page);
    await humanClick(page.locator('#crm-email-preview-btn'));
    const dialog = page.locator('#crm-email-preview-modal');
    await expect(dialog).toHaveClass(/show/, { timeout: 15000 });
    await expect(dialog.locator('#crm-email-preview-frame')).toHaveAttribute('srcdoc', /.+/, { timeout: 15000 });
    await settle(page, 800);
    await captureScreen(page, testInfo, preview);
  });

  const notSent = defineCapture({
    name: 'email-send-result-not-sent',
    title: 'Someone Was Not Emailed',
    category: 'Communication',
    purpose: 'Show the result banner naming a recipient who was not emailed and why',
    usedIn: ['docs'],
  });

  test('email-send-result-not-sent', notSent, async ({ page }, testInfo) => {
    await page.goto('/FamilyEditor.php');
    await expect(page.locator('#FamilyName')).toBeVisible({ timeout: 15000 });
    await page.locator('#FamilyName').fill('Ellison');
    await page.locator('#Address1').fill('18 Oak Street');
    await page.locator('#City').fill('Springfield');
    await page.locator('#State').selectOption('IL');
    await page.locator('#Zip').fill('62704');
    await page.locator('input[name="FirstName1"]').fill('Lena');
    await page.locator('input[name="LastName1"]').fill('Ellison');
    await page.locator('select[name="Role1"]').selectOption('1');
    await humanClick(page.locator('button[name="FamilySubmit"]'));
    await page.waitForURL(/\/people\/family\/(\d+)/, { timeout: 15000 });
    const familyId = Number(page.url().match(/\/people\/family\/(\d+)/)?.[1]);
    const memberHref = await page.getByRole('link', { name: 'Lena Ellison' }).first().getAttribute('href');
    const personId = Number(memberHref?.match(/\/people\/view\/(\d+)/)?.[1]);
    expect(personId).toBeGreaterThan(0);
    // The new-family form has no email field. Give her one, then open the composer.
    await setPersonEmail(page, personId, 'lena.ellison@demo.churchcrm.io');
    await page.reload();
    const sendButton = page.locator('#family-email-card [data-email-person-id]');
    await expect(sendButton).toBeVisible({ timeout: 15000 });

    try {
      await humanClick(sendButton);
      await expect(page.locator('#crm-email-composer-modal')).toBeVisible();
      await humanClick(page.locator('#crm-email-send-btn'));
      await expect(page.locator('#crm-email-body')).toBeVisible();
      await page.locator('#crm-email-subject').fill('A note from the office');
      // The composer already holds her id. Clearing the address makes the send skip her.
      await setPersonEmail(page, personId, '');
      await humanClick(page.locator('#crm-email-send-submit'));
      await expect(page.locator('#crm-email-not-sent')).toBeVisible({ timeout: 20000 });
      await settle(page, 600);
      await captureScreen(page, testInfo, notSent);
    } finally {
      if (familyId) {
        await page.request.delete(`/api/family/${familyId}?deleteMembers=true`);
      }
    }
  });

  const recentSends = defineCapture({
    name: 'email-dashboard-recent-sends',
    title: 'Recent Email',
    category: 'Communication',
    purpose: 'Show the email history on the Email page after a message has been sent',
  });

  test('email-dashboard-recent-sends', recentSends, async ({ page }, testInfo) => {
    await openAndrew(page);
    await openComposerForm(page);
    await humanClick(page.locator('#crm-email-send-submit'));
    await expect(page.locator('#crm-email-send-submit')).toHaveText(/Sent|Queued/, { timeout: 20000 });

    await page.goto('/people/email');
    await expect(page.locator('#email-log-table tbody tr').first()).toBeVisible({ timeout: 15000 });
    await hideFloatingButtons(page);
    await keepInFrame(page, '#email-log-card .card-header', '#email-log-table');
    await settle(page, 800);
    await captureScreen(page, testInfo, recentSends);
  });
});

/** Queues a composer message to one person and sends it now, as the composer does. */
async function sendComposerEmail(api: APIRequestContext, personId: number, subject: string, body: string): Promise<void> {
  const res = await api.post('/api/email/send', { data: { personIds: [personId], familyIds: [], subject, body } });
  expect(res.ok(), `queue "${subject}"`).toBeTruthy();
  const { batch } = await res.json();
  expect(batch).toBeTruthy();
  for (let step = 0; step < 5; step++) {
    const progress = await api.post(`/api/email/batches/${batch}/send`);
    if (!progress.ok()) return;
    const { done, due } = await progress.json();
    if (done || due === 0) return;
  }
}

async function openEmailHistoryModal(page: Page, row: Locator): Promise<void> {
  await humanClick(row.locator('.email-history-open'));
  const modal = page.locator('#email-history-modal');
  await expect(modal).toHaveClass(/show/, { timeout: 15000 });
  await expect(page.locator('#email-history-modal-title')).not.toHaveText('…', { timeout: 15000 });
}

/** Dismisses the header notices (for example a security notice from churchcrm.io) for this admin, then reloads. */
async function dismissHeaderNotices(page: Page): Promise<void> {
  const notices = page.locator('.crm-system-notification[data-dismiss-key]');
  if ((await notices.count()) === 0) return;
  const userId = await page.evaluate(() => window.CRM?.userId);
  const keys = await notices.evaluateAll((els) => els.map((el) => (el as HTMLElement).dataset.dismissKey ?? ''));
  for (const key of keys.filter(Boolean)) {
    await page.request.post(`/api/user/${userId}/setting/${encodeURIComponent(key)}`, { data: { value: 'true' } });
  }
  await page.reload();
}

async function openEmailHistoryPage(page: Page): Promise<void> {
  await openAndrew(page);
  await humanClick(page.locator('#email-history-show-all'));
  await page.waitForURL(/\/people\/view\/\d+\/emails/, { timeout: 15000 });
  await dismissHeaderNotices(page);
  await expect(page.locator('[data-email-history-table] [data-email-log-row]').first()).toBeVisible({ timeout: 15000 });
  await hideFloatingButtons(page);
}

test.describe('Email history', () => {
  let restoreMail: (() => Promise<void>) | undefined;

  // Andrew gets three composer messages and a family verification email. The
  // verification email is one ChurchCRM writes itself, so its content is not stored.
  test.beforeAll(async ({ request }) => {
    restoreMail = await enableOutgoingMail(request);
    const andrew = await findDemoPerson(request, ANDREW_EMAIL);
    expect((await request.post(`/api/family/${andrew.familyId}/verify`)).ok(), 'family verification email').toBeTruthy();
    await sendComposerEmail(
      request,
      andrew.personId,
      'Volunteers needed for the fall festival',
      'We are looking for a few more hands at the fall festival on Saturday. Reply to this email if you can help set up or serve.',
    );
    await sendComposerEmail(
      request,
      andrew.personId,
      'Small group starts Tuesday',
      'Your small group meets Tuesday at 7 pm in the fellowship hall. Bring a friend.',
    );
    await sendComposerEmail(
      request,
      andrew.personId,
      'Thank you for serving',
      'Thank you for helping with the welcome team this month. It made a real difference to our visitors.',
    );
  });

  test.afterAll(async () => {
    await restoreMail?.();
  });

  const recentCard = defineCapture({
    name: 'email-history-recent-emails-card',
    title: 'Recent Emails',
    category: 'Communication',
    purpose: 'Show the Recent Emails card on the Person View with the latest messages sent to this person',
  });

  test('email-history-recent-emails-card', recentCard, async ({ page }, testInfo) => {
    await openAndrew(page);
    await expect(page.locator('#email-history-card [data-email-log-row]').first()).toBeVisible({ timeout: 15000 });
    await hideFloatingButtons(page);
    await keepInFrame(page, '#email-history-card', '#email-history-card');
    await settle(page, 600);
    await captureScreen(page, testInfo, recentCard);
  });

  const showAll = defineCapture({
    name: 'email-history-show-all',
    title: 'Email History',
    category: 'Communication',
    purpose: 'Show the full Email History page for a person, newest first',
  });

  test('email-history-show-all', showAll, async ({ page }, testInfo) => {
    await openEmailHistoryPage(page);
    await settle(page, 600);
    await captureScreen(page, testInfo, showAll);
  });

  const timelineFilter = defineCapture({
    name: 'email-history-timeline-filter',
    title: 'Timeline Filters',
    category: 'Communication',
    purpose: 'Show the Notes, Events, Emails and System filter chips on the person timeline, with Emails selected',
  });

  test('email-history-timeline-filter', timelineFilter, async ({ page }, testInfo) => {
    await openAndrew(page);
    const chips = page.locator('#timeline .timeline-filters');
    await expect(chips.locator('[data-filter="email"]')).toBeVisible({ timeout: 15000 });
    await humanClick(chips.locator('[data-filter="email"]'));
    await humanClick(chips.locator('[data-filter="notes"]'));
    await expect(chips.locator('[data-filter="email"]')).toHaveClass(/active/);
    await expect(page.locator('#timeline .timeline-event[data-timeline-category="email"]').first()).toBeVisible();
    await hideFloatingButtons(page);
    await keepInFrame(page, '#nav-item-timeline', '#timeline .timeline-filters');
    await settle(page, 600);
    await captureScreen(page, testInfo, timelineFilter);
  });

  const modal = defineCapture({
    name: 'email-history-modal',
    title: 'View a Sent Email',
    category: 'Communication',
    purpose: 'Show an email opened from the history, as it was sent',
    usedIn: ['docs'],
  });

  test('email-history-modal', modal, async ({ page }, testInfo) => {
    await openAndrew(page);
    const row = page.locator('#email-history-card [data-email-log-row]').filter({ hasText: 'Volunteers needed for the fall festival' });
    await expect(row.first()).toBeVisible({ timeout: 15000 });
    await openEmailHistoryModal(page, row.first());
    await expect(page.locator('#email-history-modal-body')).toHaveAttribute('srcdoc', /.+/, { timeout: 15000 });
    await expect(page.locator('#email-history-modal-body')).toBeVisible();
    await settle(page, 800);
    await captureScreen(page, testInfo, modal);
  });

  const notStored = defineCapture({
    name: 'email-history-modal-not-stored',
    title: 'Content Not Stored',
    category: 'Communication',
    purpose: 'Show an email ChurchCRM sent on its own, whose content the history does not keep',
    usedIn: ['docs'],
  });

  test('email-history-modal-not-stored', notStored, async ({ page }, testInfo) => {
    await openEmailHistoryPage(page);
    const row = page.locator('[data-email-history-table] [data-email-log-row]').filter({ hasText: 'Family verification' });
    await expect(row.first()).toBeVisible({ timeout: 15000 });
    await openEmailHistoryModal(page, row.first());
    await expect(page.locator('#email-history-modal-nobody')).toBeVisible({ timeout: 15000 });
    await settle(page, 800);
    await captureScreen(page, testInfo, notStored);
  });
});
