import { expect, type Page, test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { humanClick, humanPause, settle } from '../support/human';

// The default headless shell has no PDF viewer. The full Chromium build
// renders the generated directory in Chrome's own viewer.
test.use({ channel: process.env.BROWSER_CHANNEL || 'chromium' });

// captureScreen() resizes the viewport for each device, so scroll the section
// between `top` and `bottom` into view again after every resize, below the
// sticky top bar. A section taller than the viewport keeps its bottom in view.
async function keepInFrame(page: Page, top: string, bottom: string): Promise<void> {
  await page.evaluate(
    ([topSelector, bottomSelector]) => {
      const frame = () => {
        const first = document.querySelector(topSelector);
        const last = document.querySelector(bottomSelector);
        if (!first || !last) {
          return;
        }
        const bar = document.querySelector('header.navbar.sticky-top')?.getBoundingClientRect().height ?? 0;
        const start = first.getBoundingClientRect().top + window.scrollY - bar - 16;
        const end = last.getBoundingClientRect().bottom + window.scrollY + 16;
        const target = end - start <= window.innerHeight - bar ? start : end - window.innerHeight;
        window.scrollTo({ top: target, behavior: 'instant' });
      };
      frame();
      window.addEventListener('resize', frame);
    },
    [top, bottom]
  );
}

async function chooseBookletLayout(page: Page): Promise<void> {
  await page.goto('/DirectoryReports.php');
  const booklet = page.locator('#sDirLayoutBooklet');
  await expect(booklet).toBeVisible({ timeout: 15000 });
  await humanClick(booklet);
  await expect(booklet).toBeChecked();
  // Columns count per half page; one column keeps email addresses on one line.
  await humanClick(page.locator('#NumCols1'));
  await expect(page.locator('#NumCols1')).toBeChecked();
  await humanPause(page, 400);
}

test.describe('Directory booklet', () => {
  test('directory-booklet-options', async ({ page }, testInfo) => {
    await chooseBookletLayout(page);
    // The Add Person / Add Family buttons (Footer.js initializeFAB) show for
    // the first five seconds after a page load.
    await expect(page.locator('#fab-container .fab-button:visible')).toHaveCount(0, { timeout: 15000 });
    await keepInFrame(page, '.mb-3:has(#bDirAddress)', '.row:has(#NumCols2)');
    await settle(page, 800);

    await captureScreen(page, testInfo, {
      name: 'directory-booklet-options',
      title: 'Folded Booklet Directory',
      category: 'People & Families',
      purpose: 'Show the directory report options with the Folded Booklet page layout selected',
    });
  });

  test('directory-booklet-pdf', async ({ page }, testInfo) => {
    await chooseBookletLayout(page);
    const download = page.waitForEvent('download', { timeout: 120000 });
    await humanClick(page.locator('input[name="Submit"]'));
    const pdf = await (await download).path();

    // The report downloads (iPDFOutputType 1); serve the same file back so the
    // viewer can open it on the third sheet, where both half pages are full.
    await page.route('**/directory-booklet.pdf', (route) => route.fulfill({ path: pdf, contentType: 'application/pdf' }));
    await page.goto('/directory-booklet.pdf#page=3&toolbar=0&view=Fit');
    await settle(page, 3000);

    await captureScreen(page, testInfo, {
      name: 'directory-booklet-pdf',
      title: 'Folded Booklet Directory PDF',
      category: 'People & Families',
      purpose: 'Show a printed booklet sheet: two half-size directory pages side by side in fold order, with family photos',
    });
  });
});
