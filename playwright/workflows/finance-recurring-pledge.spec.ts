import { expect, test } from '@playwright/test';
import { captureScreen, defineCapture } from '../support/capture';
import { humanSelect, humanType } from '../support/human';

const capture = defineCapture({
  name: 'finance-recurring-pledge',
  title: 'Recurring pledge commitment',
  category: 'Finance & Giving',
  purpose: 'Show finance staff entering a monthly commitment and checking the full-period total before saving.',
});

test('finance-recurring-pledge', capture, async ({ page }, testInfo) => {
  await page.goto('/finance/pledge/new?type=Pledge&familyId=1');
  await humanSelect(page.locator('#AmountBasis'), 'installment');
  await humanSelect(page.locator('#Schedule'), 'Monthly');
  const fy = await page.locator('#FYID').inputValue();
  const year = Number(fy) + 1996;
  await page.locator('#FirstInstallment').fill(year + '-01-01');
  await page.locator('#LastInstallment').fill(year + '-12-31');
  await humanSelect(page.locator('#fundRows .fund-select').first(), '1');
  await humanType(page.locator('#fundRows .fund-amount').first(), '100');
  await expect(page.locator('#commitmentTotal')).not.toHaveText('—');
  await captureScreen(page, testInfo, capture);
});
