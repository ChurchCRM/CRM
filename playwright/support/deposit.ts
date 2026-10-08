import type { Browser, Page } from '@playwright/test';
import path from 'node:path';

import { BASE_URL } from './env';

/**
 * A deposit slip of the capture's own, made and removed through the deposits
 * API. Deleting a deposit deletes its payments with it (Deposit::preDelete), so
 * the finance captures that run later see the demo deposits unchanged.
 */

const STORAGE_STATE_PATH = path.join(__dirname, '..', '.auth', 'admin.json');

export const DEMO_DEPOSIT_COMMENT = 'Sunday Offering';

async function withAdminPage<T>(browser: Browser, work: (page: Page) => Promise<T>): Promise<T> {
  const context = await browser.newContext({ baseURL: BASE_URL, storageState: STORAGE_STATE_PATH });
  try {
    return await work(await context.newPage());
  } finally {
    await context.close();
  }
}

export function createDemoDeposit(browser: Browser): Promise<number> {
  return withAdminPage(browser, async (page) => {
    const response = await page.request.post('/api/deposits', {
      data: { depositType: 'Bank', depositComment: DEMO_DEPOSIT_COMMENT },
    });
    if (!response.ok()) {
      throw new Error(`Creating the demo deposit failed: ${response.status()} ${await response.text()}`);
    }
    return Number((await response.json()).Id);
  });
}

export function deleteDemoDeposit(browser: Browser, depositId: number): Promise<void> {
  return withAdminPage(browser, async (page) => {
    const response = await page.request.delete(`/api/deposits/${depositId}`);
    if (!response.ok()) {
      throw new Error(`Deleting deposit ${depositId} failed: ${response.status()} ${await response.text()}`);
    }
  });
}

export function addPaymentUrl(depositId: number): string {
  const linkBack = `/DepositSlipEditor.php?DepositSlipID=${depositId}`;
  return `/finance/pledge/new?type=Payment&depositId=${depositId}&linkBack=${encodeURIComponent(linkBack)}`;
}
