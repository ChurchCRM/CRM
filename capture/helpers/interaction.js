/**
 * helpers/interaction.js — human-paced interaction primitives for visual capture
 *
 * These helpers exist to make recordings look natural.  They are NOT test
 * assertions; they deliberately slow things down for a viewer's eye.
 */

'use strict';

/**
 * Linearly interpolate a single axis.
 * @param {number} start
 * @param {number} end
 * @param {number} t  – 0..1
 * @returns {number}
 */
function lerp(start, end, t) {
  return start + (end - start) * t;
}

/**
 * Move the mouse smoothly from `from` to `to` over `durationMs` milliseconds,
 * taking `steps` intermediate positions.  Never teleports.
 *
 * @param {import('playwright').Page} page
 * @param {{ x: number, y: number }} from
 * @param {{ x: number, y: number }} to
 * @param {number} steps     – number of intermediate mouse positions (default 30)
 * @param {number} durationMs – total movement time in ms (default 600)
 */
async function smoothMouseMove(page, from, to, steps = 30, durationMs = 600) {
  const delayPerStep = durationMs / steps;
  for (let i = 0; i <= steps; i++) {
    const t = i / steps;
    const x = Math.round(lerp(from.x, to.x, t));
    const y = Math.round(lerp(from.y, to.y, t));
    await page.mouse.move(x, y);
    if (delayPerStep > 0) {
      await new Promise((resolve) => setTimeout(resolve, delayPerStep));
    }
  }
}

/**
 * Pause for a deliberate readability beat — lets viewers absorb what they see.
 *
 * @param {number} ms – milliseconds to pause (default 800)
 */
async function pause(ms = 800) {
  await new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Type text into a selector at a human-paced delay between each character.
 * Uses page.locator().type() with per-character delay so keystrokes appear
 * natural in the recording.
 *
 * @param {import('playwright').Page} page
 * @param {string} selector  – CSS / Playwright selector
 * @param {string} text      – text to type
 * @param {number} delayMs   – delay between characters in ms (default 80)
 */
async function typeSlowly(page, selector, text, delayMs = 80) {
  const locator = page.locator(selector);
  await locator.click();
  for (const char of text) {
    await page.keyboard.type(char);
    await new Promise((resolve) => setTimeout(resolve, delayMs));
  }
}

module.exports = { smoothMouseMove, pause, typeSlowly };
