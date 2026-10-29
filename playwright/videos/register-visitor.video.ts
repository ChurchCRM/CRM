import { expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, humanSelect, humanType, settle } from '../support/human';

/**
 * Register a first-time visitor and add a follow-up note.
 * Creates a new person (not in demo seed) with Classification = Guest,
 * then adds a short note via NoteEditor. Uses a unique name so it never
 * collides with existing demo subjects or other specs.
 */
test('register-visitor', async ({ page }, testInfo) => {
  await page.goto('/PersonEditor.php');
  await expect(page.locator('#LastName')).toBeVisible({ timeout: 15000 });
  await humanPause(page, 600);

  await humanType(page.locator('#FirstName'), 'Alex');
  await humanType(page.locator('#LastName'), 'Visitor');
  await humanSelect(page.locator('#Gender'), '1');
  // Guest classification exists in demo lists; select by visible text.
  await humanSelect(page.locator('#Classification'), { label: 'Guest' });
  await humanType(page.locator('#Email'), 'alex.visitor@demo.churchcrm.io');
  await humanPause(page, 500);

  await humanClick(page.locator('button[name="PersonSubmit"], input[name="PersonSubmit"]').first());
  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('h2')).toContainText('Alex Visitor', { timeout: 10000 });
  await humanPause(page, 800);

  // Follow-up: add a note from the person profile.
  await humanClick(page.locator('a[href*="NoteEditor.php?PersonID="]').first());
  await page.waitForURL(/NoteEditor\.php/, { timeout: 15000 });
  await expect(page.locator('.ql-editor, #NoteText')).toBeVisible({ timeout: 10000 });

  // Quill contenteditable — type into the editor surface.
  const editor = page.locator('.ql-editor').first();
  await editor.click();
  await editor.pressSequentially('First-time visitor. Follow up this week about next steps and connect with a greeter.', { delay: 40 });
  await humanPause(page, 600);
  await humanClick(page.locator('input[name="Submit"], button[name="Submit"]').first());

  await page.waitForURL(/\/people\/view\/\d+/, { timeout: 15000 });
  await expect(page.locator('h2')).toContainText('Alex Visitor', { timeout: 10000 });
  await settle(page, 2000);

  await captureScreen(page, testInfo, {
    name: 'register-visitor',
    title: 'Register a First-Time Visitor',
    category: 'Recordings',
    purpose: 'Show adding a first-time visitor and recording a follow-up note so the front desk can welcome them',
  });
});
