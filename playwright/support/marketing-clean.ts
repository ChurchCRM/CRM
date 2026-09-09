import type { Page } from '@playwright/test';

/**
 * Dismisses system chrome that has no place in marketing material — the
 * "System Update Available" banner (src/Include/Header.php renders it on
 * every logged-in page whenever a newer release exists). The dismissal is
 * persisted server-side per user (see src/skin/js/Footer.js's
 * .js-dismiss-notification handler and src/api/routes/users/user-settings.php),
 * so calling this once right after the very first login is enough for the
 * rest of the pipeline run — every later page load for this same admin
 * session will already have it suppressed.
 *
 * The other known source of marketing-unfriendly noise, the "Browser time
 * zone differs" warning, is fixed at the source instead (see
 * playwright.config.ts's `timezoneId`) rather than dismissed here.
 */
export async function dismissSystemNotifications(page: Page): Promise<void> {
  const userId = await page.evaluate(() => window.CRM?.userId);
  if (!userId) {
    return;
  }

  await page
    .request.post(`/api/user/${userId}/setting/${encodeURIComponent('notification.dismissed.system-update-available')}`, {
      data: { value: 'true' },
    })
    .catch(() => undefined);
}
