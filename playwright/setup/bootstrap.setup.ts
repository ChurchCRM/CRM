import { expect, test as setup } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

import { captureScreen } from '../support/capture';
import { ADMIN_INITIAL_PASSWORD, ADMIN_USERNAME, ADMIN_WORKING_PASSWORD, CHURCH_NAME, DB } from '../support/env';
import { humanClick, humanPause, humanSelect, humanType, settle } from '../support/human';
import { applyLocale } from '../support/locale-session';
import { dismissSystemNotifications } from '../support/marketing-clean';
import { uploadDemoPhoto } from '../support/photo';

const STORAGE_STATE_PATH = path.join(__dirname, '..', '.auth', 'admin.json');

// The very first admin account, created by the setup wizard itself before
// the demo importer ever runs — it's always Person id 1 on the fresh,
// empty database this pipeline seeds (docker:ci:new-system). It has no
// entry in src/admin/demo/people.json to pull a photo from like everyone
// else, so every screenshot's top-right avatar would otherwise show
// initials — upload one directly via the same API the app's own
// photo-uploader widget calls.
const ADMIN_PERSON_ID = 1;
const ADMIN_PHOTO = path.join(__dirname, '..', '..', 'src', 'admin', 'demo', 'images', 'people', 'andrew.adams.jpg');

/**
 * One recorded workflow: install, first sign-in, church info, demo import,
 * then the module dashboards with that data on screen. It runs once (the
 * "setup" project), before every other project, and saves the storage state
 * those projects reuse.
 *
 * The summed waits (prerequisites, migration, first sign-in, import) can
 * exceed the suite's 300s test timeout, so this test sets its own.
 */
setup('setup-demo-dashboards', async ({ page }, testInfo) => {
  setup.setTimeout(600000);
  await page.goto('/');
  await expect(page).toHaveURL(/\/setup/);
  await expect(page.locator('.setup-logo')).toBeVisible();
  await humanPause(page, 1500);

  // The server check can take 2+ minutes on slower systems.
  await expect(page.locator('#status-banner')).not.toHaveClass(/status-checking/, { timeout: 120000 });
  await humanPause(page, 600);

  await humanType(page.locator('#DB_SERVER_NAME'), DB.host);
  await humanType(page.locator('#DB_SERVER_PORT'), String(DB.port));
  await humanType(page.locator('#DB_NAME'), DB.name);
  await humanType(page.locator('#DB_USER'), DB.user);
  await humanType(page.locator('#DB_PASSWORD'), DB.password);

  await humanPause(page, 1200);
  await humanClick(page.locator('#submit-setup'));

  await page.locator('#setup-success').waitFor({ state: 'visible', timeout: 120000 });
  await humanPause(page, 800);
  await humanClick(page.locator('#continue-to-login'));
  await expect(page.locator('input[name=User]')).toBeVisible({ timeout: 30000 });
  await settle(page, 1500);

  // First admin login — forced password change.
  await page.goto('/session/begin');
  await humanType(page.locator('input[name=User]'), ADMIN_USERNAME);
  await humanType(page.locator('input[name=Password]'), ADMIN_INITIAL_PASSWORD);
  await humanPause(page, 300);
  await page.locator('input[name=Password]').press('Enter');
  // waitForURL (not waitForNavigation, which only fires on the first
  // navigation event) correctly handles the /login -> /account/password
  // redirect regardless of how many hops it takes.
  await page.waitForURL(/\/account\/password/, { timeout: 30000 });

  await humanType(page.locator('#OldPassword'), ADMIN_INITIAL_PASSWORD);
  await humanType(page.locator('#NewPassword1'), ADMIN_WORKING_PASSWORD);
  await humanType(page.locator('#NewPassword2'), ADMIN_WORKING_PASSWORD);
  await humanPause(page, 400);
  await humanClick(page.locator('button[type=submit]'));

  // With no church name yet, the admin lands on the Setup Dashboard and picks
  // Set Up Your Church. The demo data import (next test) overwrites this with
  // its own fixture ("Main St. Cathedral") — expected, not a bug.
  await page.waitForURL(/\/admin\/setup$/, { timeout: 15000 });
  await dismissSystemNotifications(page);
  await humanPause(page, 800);
  await humanClick(page.locator('#startChurchSetup'));
  await page.waitForURL(/\/admin\/system\/church-info/, { timeout: 15000 });
  await humanPause(page, 500);

  await humanType(page.locator('#sChurchName'), CHURCH_NAME);
  await humanType(page.locator('#sChurchPhone'), '(555) 010-1234');
  await humanType(page.locator('#sChurchEmail'), 'info@gracecommunitychurch.demo.churchcrm.io');
  await humanType(page.locator('#sChurchAddress'), '100 Fellowship Way');
  await humanType(page.locator('#sChurchCity'), 'Springfield');

  const stateField = page.locator('#sChurchState');
  await stateField.waitFor({ state: 'attached', timeout: 10000 });
  await humanSelect(stateField, 'IL');

  await humanType(page.locator('#sChurchZip'), '62701');

  await humanPause(page, 500);
  await humanClick(page.locator('#church-info-form button[type=submit]'));
  await page.getByText(/Church information saved/).first().waitFor({ state: 'visible', timeout: 10000 });
  await settle(page, 800);

  await page.goto('/admin/setup');
  await page.waitForURL(/\/admin\/setup$/, { timeout: 30000 });
  await uploadDemoPhoto(page, 'person', ADMIN_PERSON_ID, ADMIN_PHOTO);
  await humanPause(page, 500);

  const importButton = page.locator('#importDemoDataSetup');
  await importButton.waitFor({ state: 'visible', timeout: 10000 });
  await humanClick(importButton);

  await page.locator('#demoImportModal').waitFor({ state: 'visible', timeout: 5000 });
  await humanPause(page, 500);
  await humanClick(page.locator('#demoImportConfirmBtn'));

  // The import ends on the People dashboard, populated with the demo data.
  await page.waitForURL(/\/people\/dashboard$/, { timeout: 120000 });
  // The unified dashboard renders several h2s (page title, Insights, ...);
  // a bare 'h2' locator is a strict-mode violation now. Wait on the title.
  await page.locator('h2.page-title').waitFor({ state: 'visible', timeout: 15000 });
  await humanPause(page, 2000);

  for (const dashboard of ['/groups/dashboard', '/groups/sundayschool/dashboard', '/event/dashboard', '/finance/']) {
    await page.goto(dashboard);
    await expect(page.locator('h2').first()).toBeVisible({ timeout: 15000 });
    await humanPause(page, 2000);
  }

  await captureScreen(page, testInfo, {
    name: 'setup-demo-dashboards',
    title: 'From Setup to Demo Dashboards',
    category: 'Setup',
    purpose: 'Install ChurchCRM, set the church, import the sample data, and show that data on the module dashboards',
    usedIn: ['website'],
  });

  // CRM #10048 — set the admin account's UI locale (no-op for the default
  // 'en' run) before saving storageState, so every downstream
  // screenshots/tablet/mobile project inherits it along with the session.
  await applyLocale(page);

  fs.mkdirSync(path.dirname(STORAGE_STATE_PATH), { recursive: true });
  await page.context().storageState({ path: STORAGE_STATE_PATH });
});
