/// <reference types="cypress" />

/**
 * Member Portal — the signed-in account's own language (MP8, #9869).
 *
 * Design `.agents/skills/churchcrm/member-portal-design.md` §3.4: the portal's Twig
 * environment exposes `gettext()`/`ngettext()` and the page loads the same i18next
 * catalogues the admin shell does, so an account holder who has chosen a language
 * sees the portal in it. `ui.locale` is a per-user setting layered over the
 * system-wide `sLanguage` by `Bootstrapper::getCurrentLocale()`. Members have no
 * account since the release-day cutover, so they see the church's language; the
 * persona is a staff account opening its own portal.
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
 * fr_FR's catalogue grows with every translation sync, so the fallback test reads
 * messages.po at run time: a nav label with a msgstr must show it, and one without
 * must show the English msgid. Which labels are which changes over time; the rule
 * does not.
 *
 * Seed persona: noperm.user (Alice Noperm, person 901, family 1), password changeme.
 */

const MEMBER_USER_ID = 901;

const LOCALE_URL = `/api/user/${MEMBER_USER_ID}/setting/ui.locale`;

/** Translated in fr_FR: msgid -> the msgstr the catalogue carries. */
const TRANSLATED = {
    Home: "Accueil",
    Calendar: "Calendrier",
    Profile: "Profil",
};

/** The member's nav labels, as msgids. */
const NAV_LABELS = ["Home", "Calendar", "My Family", "Profile"];

const FRENCH_CATALOGUE = "src/locale/textdomain/fr_FR/LC_MESSAGES/messages.po";

/** The catalogue's msgstr for a single-line msgid, or "" when it has none. */
const msgstrIn = (po, msgid) => {
    const quoted = msgid.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const match = new RegExp(`^msgid "${quoted}"\\nmsgstr "(.*)"$`, "m").exec(po);
    return match ? match[1] : "";
};

const adminKey = () => Cypress.testEnv("admin.api.key");

const setLocale = (value) =>
    cy.request({
        method: "POST",
        url: LOCALE_URL,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.request({ method: "POST", url: "/admin/", form: true, body: { User: "noperm.user", Password: "changeme" } });
    cy.visit("/portal/");
};

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Member Portal — the signed-in account's own language (#9869)", () => {
    let savedCalendars;

    before(() => {
        // The Calendar nav entry this spec reads is there while a calendar is shared.
        cy.getMemberPortalCalendars().then((calendars) => {
            savedCalendars = calendars;
        });
        cy.shareMemberPortalCalendars([{ type: "calendar", id: 1 }]);
    });

    after(() => {
        setLocale("");
        cy.then(() => cy.shareMemberPortalCalendars(savedCalendars));
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

    it("shows each nav label in French, or in English where fr_FR has no translation", () => {
        cy.readFile(FRENCH_CATALOGUE).then((po) => {
            setLocale("fr_FR");
            loginAsMember();

            for (const english of NAV_LABELS) {
                cy.get(".portal-nav-list").should("contain", msgstrIn(po, english) || english);
            }
        });
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
