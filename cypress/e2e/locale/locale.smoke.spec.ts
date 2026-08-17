/**
 * Locale smoke tests — verify every supported locale renders pages without
 * HTTP 500 errors, uncaught JavaScript exceptions, or missing shell structure.
 *
 * Two tiers:
 *   smoke (default) — 15 high-risk locales run on every PR and push.
 *   full            — all 49 locales run on release branches, nightly, and
 *                     manual workflow_dispatch.
 *
 * Select tier via Cypress env:
 *   --env LOCALE_TIER=full   (or set in locale.config.ts)
 *
 * Adding a page to the suite: add one entry to cypress/fixtures/locale-pages.ts.
 * The spec and CI workflow never need to change.
 */

// @ts-ignore — JSON import resolved by Cypress's esbuild bundler
import localesJson from '../../../src/locale/locales.json';
import { LOCALE_TEST_PAGES } from '../../fixtures/locale-pages';

// ---------------------------------------------------------------------------
// Locale list construction
// ---------------------------------------------------------------------------

/**
 * The 15 smoke-tier poEditor codes — locales most likely to expose rendering,
 * RTL, CJK, complex-script, or Cyrillic bugs.
 */
const SMOKE_POEDITOR_CODES: readonly string[] = [
  'ar',     // RTL — Arabic
  'he',     // RTL — Hebrew
  'zh-CN',  // CJK — Simplified Chinese
  'zh-TW',  // CJK — Traditional Chinese
  'ja',     // CJK — Japanese
  'ko',     // CJK — Korean
  'th',     // Complex script — Thai
  'hi',     // Complex script — Hindi (Devanagari)
  'ta',     // Complex script — Tamil
  'vi',     // Heavy diacritics — Vietnamese
  'de',     // Heavy diacritics — German
  'fr',     // Heavy diacritics — French
  'ru',     // Cyrillic — Russian
  'pl',     // Cyrillic-adjacent / heavy diacritics — Polish
  'sw',     // African — Swahili
];

interface LocaleEntry {
  /** Display name (English) — used as describe label */
  name: string;
  /** locale field from locales.json (e.g. "ar_EG", "zh_CN") — value for ui.locale setting */
  locale: string;
  /** poEditor code (e.g. "ar", "zh-CN") — used for smoke-tier filtering */
  poEditor: string;
  /** Native script name — included in describe label */
  nativeName: string;
}

// Build the full list from locales.json at spec-load time so newly added
// locales are automatically included in the full tier without any code change.
const allLocales: LocaleEntry[] = Object.entries(
  localesJson as Record<string, { locale: string; poEditor: string; nativeName?: string }>,
).map(([name, v]) => ({
  name,
  locale: v.locale,
  poEditor: v.poEditor,
  // Fall back to the English key name for locales that omit nativeName (e.g. Slovak).
  nativeName: v.nativeName ?? name,
}));

const tier: string = (Cypress.env('LOCALE_TIER') as string) ?? 'smoke';

const localesUnderTest: LocaleEntry[] =
  tier === 'full'
    ? allLocales
    : allLocales.filter((l) => SMOKE_POEDITOR_CODES.includes(l.poEditor));

// ---------------------------------------------------------------------------
// Per-locale describe blocks
// ---------------------------------------------------------------------------

localesUnderTest.forEach(({ name, locale, nativeName }) => {
  describe(`Locale smoke: ${nativeName} / ${name} (${locale})`, () => {
    // Single entry point for locale + session setup. The command posts
    // ui.locale and calls cy.setupLoginSession; cy.session caches the
    // login so the browser-side overhead per test is minimal.
    beforeEach(() => {
      cy.setupLocaleAdminSession(locale);
    });

    // Iterate every page in the fixture array — adding a page is one line there.
    LOCALE_TEST_PAGES.forEach(({ name: pageName, url }) => {
      it(`${pageName} loads without HTTP 500 or uncaught JS errors`, () => {
        // cy.visit with default failOnStatusCode:true already fails on 500.
        // The global uncaught:exception handler in e2e.js propagates real JS
        // errors as test failures — no extra cy.on() needed here.
        cy.visit(url);

        // Confirm a rendered shell element is present (not a blank or error stub).
        cy.get('.navbar', { timeout: 15000 }).should('exist');
      });
    });
  });
});
