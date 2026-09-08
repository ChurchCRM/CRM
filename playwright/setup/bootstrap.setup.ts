import { expect, test as setup } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

import { captureScreen } from '../support/capture';
import { ADMIN_INITIAL_PASSWORD, ADMIN_USERNAME, ADMIN_WORKING_PASSWORD, CHURCH_NAME, DB } from '../support/env';
import { humanClick, humanPause, humanSelect, humanType } from '../support/human';
import { dismissSystemNotifications } from '../support/marketing-clean';

const STORAGE_STATE_PATH = path.join(__dirname, '..', '.auth', 'admin.json');

/**
 * These two tests are real, recorded workflows — not plumbing — because
 * they're the videos this pipeline exists to produce first: how setup and
 * church info work, and how to load the sample data. They run once (the
 * "setup" project in playwright.config.ts), before the desktop/tablet/mobile
 * projects, which declare a dependency on this project and reuse the
 * storage state saved at the end of the second test.
 */
setup('setup-church-info', async ({ page }, testInfo) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/setup/);
  await expect(page.locator('.setup-logo')).toBeVisible();

  const prereqNext = page.locator('#prerequisites-next-btn');
  await prereqNext.waitFor({ state: 'visible', timeout: 30000 });
  await humanPause(page, 600);
  await humanClick(prereqNext);

  await expect(page.locator('#step-database')).toHaveClass(/active/);
  await humanPause(page, 400);

  await humanType(page.locator('#DB_SERVER_NAME'), DB.host);
  await humanType(page.locator('#DB_SERVER_PORT'), String(DB.port));
  await humanType(page.locator('#DB_NAME'), DB.name);
  await humanType(page.locator('#DB_USER'), DB.user);
  await humanType(page.locator('#DB_PASSWORD'), DB.password);
  await humanType(page.locator('#DB_PASSWORD_CONFIRM'), DB.password);

  await humanPause(page, 500);
  await humanClick(page.locator('#submit-setup'));

  await page.locator('#setup-success').waitFor({ state: 'visible', timeout: 120000 });
  await humanPause(page, 800);
  await humanClick(page.locator('#continue-to-login'));
  await page.waitForURL(/\/session\/begin/, { timeout: 10000 });

  // First admin login — forced password change.
  await page.goto('/login');
  await humanType(page.locator('input[name=User]'), ADMIN_USERNAME);
  await humanType(page.locator('input[name=Password]'), ADMIN_INITIAL_PASSWORD);
  await humanPause(page, 300);
  await page.locator('input[name=Password]').press('Enter');
  await page.waitForURL(/\/changepassword/, { timeout: 15000 });

  await humanType(page.locator('#OldPassword'), ADMIN_INITIAL_PASSWORD);
  await humanType(page.locator('#NewPassword1'), ADMIN_WORKING_PASSWORD);
  await humanType(page.locator('#NewPassword2'), ADMIN_WORKING_PASSWORD);
  await humanPause(page, 400);
  await humanClick(page.locator('button[type=submit]'));

  // ChurchInfoRequiredMiddleware redirects here while sChurchName is empty.
  // The demo data import (next test) overwrites this with its own fixture
  // ("Main St. Cathedral") — expected, not a bug; this save only needs to
  // satisfy the required-fields gate so we can move on.
  await page.waitForURL(/\/admin\/system\/church-info/, { timeout: 15000 });
  await dismissSystemNotifications(page);
  await page.reload();
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
  await page.getByText('Church information saved successfully').waitFor({ state: 'visible', timeout: 10000 });
  await humanPause(page, 800);

  await captureScreen(page, testInfo, {
    name: 'setup-church-info',
    purpose: 'Show first-run setup: prerequisites, database configuration, and church info',
  });
});

setup('demo-data-import', async ({ page }, testInfo) => {
  await page.goto('/login');
  await humanType(page.locator('input[name=User]'), ADMIN_USERNAME);
  await humanType(page.locator('input[name=Password]'), ADMIN_WORKING_PASSWORD);
  await humanPause(page, 300);
  await page.locator('input[name=Password]').press('Enter');
  await page.waitForURL((url) => !url.pathname.includes('/session/begin'), { timeout: 15000 });

  await page.goto('/admin/get-started');
  await humanPause(page, 500);

  const importCard = page.locator('#importDemoDataV2');
  await importCard.waitFor({ state: 'visible', timeout: 10000 });
  await humanClick(importCard);

  await page.locator('#demoImportConfirmOverlay').waitFor({ state: 'visible', timeout: 5000 });
  await humanPause(page, 500);
  await humanClick(page.locator('#demoImportConfirmBtn'));

  // The spinner overlay gains the "show" class while the import runs and
  // loses it when done. It may never visibly appear if the import is fast.
  await page
    .locator('#demoImportSpinnerOverlay')
    .waitFor({ state: 'visible', timeout: 5000 })
    .catch(() => undefined);
  await page.locator('#demoImportSpinnerOverlay:not(.show)').waitFor({ state: 'attached', timeout: 120000 });
  await humanPause(page, 800);

  // Show the payoff, not just the click — the imported data itself.
  await page.goto('/people/family');
  await page.locator('#families tbody tr').first().waitFor({ state: 'visible', timeout: 15000 });
  await humanPause(page, 600);

  await captureScreen(page, testInfo, {
    name: 'demo-data-import',
    purpose: 'Show importing the sample data set and the resulting seeded families',
  });

  fs.mkdirSync(path.dirname(STORAGE_STATE_PATH), { recursive: true });
  await page.context().storageState({ path: STORAGE_STATE_PATH });
});
