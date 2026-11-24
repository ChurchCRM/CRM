import type { Page } from '@playwright/test';

/**
 * Hides the system-update banner for the rest of this staff session.
 * Dismissal is session-only (POST /v2/releases/dismiss-update-notice).
 */
export async function dismissSystemNotifications(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const button = document.querySelector(
      '[data-notification-id="system-update-available"] .js-dismiss-notification',
    );
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const root = window.CRM?.root ?? '';
    await fetch(`${root}/v2/releases/dismiss-update-notice`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': button.dataset.csrfToken ?? '' },
    });
  }).catch(() => undefined);
}
