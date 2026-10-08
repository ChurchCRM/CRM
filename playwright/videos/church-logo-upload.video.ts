import { type APIRequestContext, expect, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { adminRequest, DEMO_LOGO_PATH, removeChurchLogo, waitForImages } from '../support/church-logo';
import { humanClick, humanPause, settle } from '../support/human';

let api: APIRequestContext;

test.beforeAll(async () => {
  api = await adminRequest();
  await removeChurchLogo(api);
});

test.afterAll(async () => {
  try {
    await removeChurchLogo(api);
  } finally {
    await api?.dispose();
  }
});

test('church-logo-upload', async ({ page }, testInfo) => {
  await page.goto('/admin/system/church-info');
  await expect(page.locator('#church-logo-default-note')).toBeVisible({ timeout: 15000 });
  await page.waitForFunction(() =>
    Boolean((window as unknown as { CRM?: { photoUploader?: unknown } }).CRM?.photoUploader),
  );
  await page.locator('#church-logo-card').scrollIntoViewIfNeeded();
  await humanPause(page, 1500);

  await humanClick(page.locator('#church-logo-upload-btn'));
  await expect(page.locator('.uppy-Dashboard--modal .uppy-Dashboard-inner')).toBeVisible({ timeout: 10000 });
  await humanPause(page, 800);
  await page.locator('.uppy-Dashboard-input').first().setInputFiles(DEMO_LOGO_PATH);
  const accept = page.locator('.uppy-DashboardContent-save');
  await expect(accept).toBeEnabled({ timeout: 10000 });
  await humanPause(page, 1500);
  await humanClick(accept);
  const upload = page.locator('.uppy-StatusBar-actionBtn--upload');
  await expect(upload).toBeVisible();
  await humanPause(page, 1200);
  await humanClick(upload);

  await expect(page.locator('#church-logo-remove-btn')).toBeVisible({ timeout: 30000 });
  await waitForImages(page);
  await settle(page, 3000);

  await captureScreen(page, testInfo, {
    name: 'church-logo-upload',
    title: 'Upload a Church Logo',
    category: 'Recordings',
    purpose: 'Upload the church logo and watch it replace the ChurchCRM logo in the sidebar.',
  });
  await page.close();
});
