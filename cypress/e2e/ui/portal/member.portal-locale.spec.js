/// <reference types="cypress" />

/**
 * Member Portal — the member's own language (MP8, #9869).
 *
 * Design `.agents/skills/churchcrm/member-portal-design.md` §3.4: the portal's Twig
 * environment exposes `gettext()`/`ngettext()` and the page loads the same i18next
 * catalogues the admin shell does, so a member who has chosen a language sees the
 * portal in it. `ui.locale` is a per-user setting layered over the system-wide
 * `sLanguage` by `Bootstrapper::getCurrentLocale()`.
 *
 * What this proves that the API specs cannot: the rendered page. Three failure modes
 * are possible once strings go through a catalogue and none of them is a 500, so none
 * would be caught anywhere else —
 *
 *   1. a raw msgid leaking through as a key (`portal.nav.home`-shaped text);
 *   2. an EMPTY string where a translation is missing, which is what happens when a
 *      template prints a lookup result rather than falling back to the msgid;
 *   3. a translation that never arrives because the catalogue was not loaded at all,
 *      leaving every string English on a French login.
 *
 * fr_FR is chosen because its catalogue covers some of the portal's nav and not all
 * of it, which is the realistic state of every language: "Home", "Calendar" and
 * "Profile" are translated; "My Family", "Volunteering" and "My Teams" are the epic's
 * own new strings and are not. Both halves have to render.
 *
 * Seed persona: user 100, Lena Black. usr_EditSelf=1 and no admin flag, so she is
 * confined to the portal. The username column is VARCHAR(32), so the seeded address is
 * stored truncated — log in with the 32-char form.
 *
 * NOTE: every x-api-key request replaces the browser session cookie with an API-token
 * session, so each locale change is followed by a fresh login.
 */

const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const MEMBER_USER_ID = 100;

const LOCALE_URL = `/api/user/${MEMBER_USER_ID}/setting/ui.locale`;

/** Translated in fr_FR: msgid -> the msgstr the catalogue carries. */
const TRANSLATED = {
    Home: "Accueil",
    Calendar: "Calendrier",
    Profile: "Profil",
};

/**
 * Not translated in fr_FR — the epic's own new strings. These must fall back to the
 * English msgid, never to a key and never to nothing.
 */
const UNTRANSLATED = ["My Family"];

const adminKey = () => Cypress.env("admin.api.key");

const setLocale = (value) =>
    cy.request({
        method: "POST",
        url: LOCALE_URL,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const setConfig = (name, value) =>
    cy.request({
        method: "POST",
        url: `/admin/api/system/config/${name}`,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

describe("Member Portal — the member's own language (#9869)", () => {
    before(() => {
        // The nav entries this spec reads are only there when their sections are on.
        setConfig("bPortalShowCalendar", "1");
    });

    after(() => {
        setLocale("");
    });

    it("renders the portal navigation in the member's language", () => {
        setLocale("fr_FR");
        loginAsMember();

        cy.get(".portal-nav-list", { timeout: 10000 }).should("be.visible");

        for (const [english, french] of Object.entries(TRANSLATED)) {
            cy.get(".portal-nav-list").should("contain", french);
            // The English msgid must be gone, or the catalogue was not consulted.
            // `contain` is substring-based, so compare on the trimmed entry text.
            cy.get(".portal-nav-list .portal-nav-link > span:not(.portal-nav-badge)").then(($labels) => {
                const labels = [...$labels].map((el) => el.textContent.trim());
                expect(labels, `"${english}" was replaced`).to.not.include(
                    english,
                );
            });
        }
    });

    it("falls back to English where fr_FR has no translation", () => {
        setLocale("fr_FR");
        loginAsMember();

        for (const english of UNTRANSLATED) {
            cy.get(".portal-nav-list").should("contain", english);
        }
    });

    it("shows no raw keys and no empty labels anywhere in the nav", () => {
        setLocale("fr_FR");
        loginAsMember();

        cy.get(".portal-nav-list .portal-nav-link > span:not(.portal-nav-badge)").then(($labels) => {
            const labels = [...$labels].map((el) => el.textContent.trim());

            expect(labels.length, "the nav rendered entries at all").to.be.greaterThan(
                2,
            );

            for (const label of labels) {
                expect(label, "no empty nav label").to.not.eq("");
                // A leaked i18next key looks like `some.dotted.path` with no spaces.
                expect(label, `"${label}" is not a raw key`).to.not.match(
                    /^[a-z0-9]+(\.[a-z0-9_]+)+$/,
                );
                // An unsubstituted placeholder is the other way a catalogue miss shows.
                expect(label, `"${label}" has no placeholder`).to.not.match(
                    /\{\{|%\d?\$?s/,
                );
            }
        });
    });

    it("puts the page back in English when the setting is cleared", () => {
        setLocale("");
        loginAsMember();

        cy.get(".portal-nav-list", { timeout: 10000 }).should("contain", "Home");
        cy.get(".portal-nav-list").should("not.contain", TRANSLATED.Home);
    });
});
