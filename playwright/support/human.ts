import type { Locator, Page } from '@playwright/test';

/**
 * Human-paced interaction helpers, used everywhere instead of raw
 * `.click()` / `.fill()` / `.selectOption()` so the recorded videos show a
 * person working through the app rather than an instant scripted bot —
 * this matters most for the setup/church-info and sample-data-import
 * recordings, which exist specifically to show someone how to do this.
 */

function jitter(baseMs: number, spreadMs: number): number {
  return baseMs + Math.random() * spreadMs;
}

// Pacing only matters on camera: screenshot-only runs (no video) skip it.
function isRecording(page: Page): boolean {
  return page.video() !== null;
}

async function pace(page: Page, ms: number): Promise<void> {
  if (isRecording(page)) {
    await page.waitForTimeout(ms);
  }
}

export async function humanPause(page: Page, ms = 600): Promise<void> {
  await pace(page, ms);
}

/** Always waits — lets charts, maps and fades finish before a capture. */
export async function settle(page: Page, ms = 600): Promise<void> {
  await page.waitForTimeout(ms);
}

/** Hover first, brief pause, then click — instead of an instant click. */
export async function humanClick(locator: Locator): Promise<void> {
  await locator.scrollIntoViewIfNeeded();
  await locator.hover();
  await pace(locator.page(), jitter(350, 250));
  await locator.click();
}

/**
 * Types one keystroke at a time instead of setting the value instantly.
 * Clears the field first — some fields (e.g. the setup wizard's DB Server /
 * Port) come pre-filled with auto-detected defaults, and pressSequentially
 * types at the current cursor position rather than replacing content.
 */
export async function humanType(locator: Locator, text: string): Promise<void> {
  await locator.click();
  await locator.clear();
  await locator.pressSequentially(text, { delay: isRecording(locator.page()) ? jitter(90, 70) : 0 });
}

/**
 * Brief pause, then choose — instead of an instant selectOption. No hover
 * step here (unlike humanClick): several <select> fields in this app (e.g.
 * #sChurchState) are TomSelect-enhanced, which hides the native <select>
 * behind its own widget, so hovering the underlying element fails even
 * though selectOption() itself works fine on it. A pause after selecting
 * too, so the widget's visual update is actually visible in the recording
 * before the next action starts.
 */
export async function humanSelect(
  locator: Locator,
  value: string | { label: string } | { index: number }
): Promise<void> {
  await pace(locator.page(), jitter(350, 150));
  await locator.selectOption(value);
  await pace(locator.page(), jitter(350, 150));
}
