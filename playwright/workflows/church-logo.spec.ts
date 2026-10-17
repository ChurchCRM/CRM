import { type APIRequestContext, expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { adminRequest, removeChurchLogo, uploadDemoLogo, waitForImages } from '../support/church-logo';
import { settle } from '../support/human';

let api: APIRequestContext;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async () => {
  api = await adminRequest();
  await uploadDemoLogo(api);
});

test.afterAll(async () => {
  try {
    await removeChurchLogo(api);
  } finally {
    await api?.dispose();
  }
});

/** Scrolls an element's bottom into frame, again after each resize captureScreen() makes. */
async function keepInView(page: Page, selector: string): Promise<void> {
  await page.evaluate((sel) => {
    const align = () => {
      const element = document.querySelector(sel);
      const box = element?.getBoundingClientRect();
      if (element && box && (box.top < 0 || box.bottom > window.innerHeight)) {
        element.scrollIntoView({ block: 'end', behavior: 'instant' });
      }
    };
    window.addEventListener('resize', align);
    align();
  }, selector);
}

test('admin-church-logo', async ({ page }, testInfo) => {
  await page.goto('/admin/system/church-info');
  await expect(page.locator('#church-logo-remove-btn')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#church-logo-preview')).toHaveAttribute('src', /church-logo\.png/);
  await keepInView(page, '#church-logo-card');
  await waitForImages(page);
  await settle(page, 600);

  await captureScreen(page, testInfo, {
    name: 'admin-church-logo',
    title: 'Upload Your Church Logo',
    category: 'Admin & Settings',
    purpose: 'Upload the church logo once and it replaces the ChurchCRM branding in the app.',
  });
});

test.describe('signed out', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('church-logo-sign-in', async ({ page }, testInfo) => {
    await page.goto('/login');
    await expect(page.locator('input[name=User]')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('img[src*="church-logo.png"]').first()).toBeVisible();
    await waitForImages(page);
    await settle(page, 600);

    await captureScreen(page, testInfo, {
      name: 'church-logo-sign-in',
      title: 'Sign-In Page with Church Logo',
      category: 'Admin & Settings',
      purpose: "Members and staff sign in under the church's own logo instead of ChurchCRM's.",
    });
  });
});
