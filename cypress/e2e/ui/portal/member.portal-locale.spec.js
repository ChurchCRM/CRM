/// <reference types="cypress" />

import { clearLanguages, storeLanguage } from "../../../support/member-sign-in-fixtures";

/**
 * Member Portal — the page's language (MP8 #9869, member sign-in #10463).
 *
 * A member picks a language on Profile → Update your details (`psi_Language`);
 * "Church default" follows `sLanguage`. A staff session opening the portal keeps
 * the staff account's own `ui.locale` setting. `Bootstrapper::getCurrentLocale()`
 * decides it once per request, so Twig's `gettext()` and the i18next catalogue
 * the page loads agree.
 *
 * What this proves that the API specs cannot: the rendered page. A raw msgid
 * leaking through as a key, an empty label where a translation is missing, and a
 * catalogue that never loads are none of them a 500.
 *
 * fr_FR's catalogue grows with every translation sync, so the fallback test reads
 * messages.po at run time: a nav label with a msgstr must show it, and one without
 * must show the English msgid.
 *
 * Personas: Lena Black (person 100), a member; Alice Noperm (person 901,
 * noperm.user / changeme), a staff account whose person also signs in as a member.
 */

const LENA = 100;
const ALICE = 901;

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

// Each x-api-key request replaces the browser's session cookie, so these run
// before a sign-in, never between a sign-in and the page it opens.
const adminPost = (url, value) =>
    cy.request({
        method: "POST",
        url,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
    });
const setConfig = (name, value) => adminPost(`/admin/api/system/config/${name}`, value);
const setStaffLanguage = (value) => adminPost(`/api/user/${ALICE}/setting/ui.locale`, value);

const signInAsStaff = () => {
    cy.clearCookies();
    cy.request({ method: "POST", url: "/admin/", form: true, body: { User: "noperm.user", Password: "changeme" } });
};

const csrfToken = () =>
    cy.request("/portal/profile/edit").then(({ body }) => body.match(/name="csrf_token" value="([^"]+)"/)[1]);

/** `POST /api/portal/me` in the browser's current portal session. */
const saveProfile = (body) =>
    csrfToken().then((token) =>
        cy.request({ method: "POST", url: "/api/portal/me", headers: { "X-CSRF-Token": token }, body, failOnStatusCode: false }),
    );

const navLabels = () =>
    cy
        .get(".portal-nav-list .portal-nav-link > span:not(.portal-nav-badge)")
        .then(($labels) => [...$labels].map((el) => el.textContent.trim()));

const expectFrenchNav = () => {
    cy.get("html").should("have.attr", "lang", "fr-FR");
    navLabels().then((labels) => {
        for (const [english, french] of Object.entries(TRANSLATED)) {
            expect(labels).to.include(french);
            expect(labels, `"${english}" was replaced`).to.not.include(english);
        }
    });
};

const expectEnglishNav = () => {
    cy.get("html").should("have.attr", "lang", "en-US");
    navLabels().should("include", "Home").and("not.include", TRANSLATED.Home);
};

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Member Portal — the page's language", () => {
    let savedLanguage;
    let savedCalendars;

    before(() => {
        // The Calendar nav entry this spec reads is there while a calendar is shared.
        cy.getMemberPortalCalendars().then((calendars) => {
            savedCalendars = calendars;
        });
        cy.shareMemberPortalCalendars([{ type: "calendar", id: 1 }]);
        cy.getSystemConfig("sLanguage").then((value) => {
            savedLanguage = value;
        });
    });

    beforeEach(() => {
        clearLanguages([LENA, ALICE]);
    });

    after(() => {
        clearLanguages([LENA, ALICE]);
        setStaffLanguage("");
        cy.restoreSystemConfig("sLanguage", savedLanguage);
        cy.then(() => cy.shareMemberPortalCalendars(savedCalendars));
    });

    it("renders the portal and the next page in the language the member picks", () => {
        cy.signInAsMember(LENA);
        cy.visit("/portal/profile/edit");
        cy.get("#portal-language option").first().should("have.value", "").and("have.text", "Church default: English (United States)");
        cy.get("#portal-language option[value=fr_FR]").should("have.text", "Français");

        cy.get("#portal-language").select("fr_FR");
        cy.get("#portal-profile-save").click();

        cy.get(".portal-flash-success").should("contain", "Vos informations ont été enregistrées.");
        expectFrenchNav();
        cy.get("#portal-language").should("have.value", "fr_FR");

        // The page bundle's own toast comes from the i18next catalogue for the same language.
        cy.get("#portal-profile-save").click();
        cy.get(".portal-flash-success").should("contain", "Rien n'avait changé, donc rien n'a été enregistré.");

        cy.visit("/portal/");
        expectFrenchNav();
        cy.window().its("CRM.locale").should("eq", "fr_FR");
    });

    it("shows each nav label in French, or in English where fr_FR has no translation", () => {
        cy.readFile(FRENCH_CATALOGUE).then((po) => {
            cy.signInAsMember(LENA);
            saveProfile({ language: "fr_FR" }).its("status").should("eq", 200);
            cy.visit("/portal/");

            for (const english of NAV_LABELS) {
                cy.get(".portal-nav-list").should("contain", msgstrIn(po, english) || english);
            }
        });
    });

    it("shows no raw keys and no empty labels anywhere in the nav", () => {
        cy.signInAsMember(LENA);
        saveProfile({ language: "fr_FR" }).its("status").should("eq", 200);
        cy.visit("/portal/");

        navLabels().then((labels) => {
            expect(labels.length, "the nav rendered entries at all").to.be.greaterThan(2);

            for (const label of labels) {
                expect(label, "no empty nav label").to.not.eq("");
                // A leaked i18next key looks like `some.dotted.path` with no spaces.
                expect(label, `"${label}" is not a raw key`).to.not.match(/^[a-z0-9]+(\.[a-z0-9_]+)+$/);
                // An unsubstituted placeholder is the other way a catalogue miss shows.
                expect(label, `"${label}" has no placeholder`).to.not.match(/\{\{|%\d?\$?s/);
            }
        });
    });

    it("follows the church's language under Church default", () => {
        setConfig("sLanguage", "fr_FR");
        cy.signInAsMember(LENA);
        cy.visit("/portal/profile/edit");
        cy.get("#portal-language").should("have.value", "");
        cy.get("#portal-language option").first().should("have.text", "Church default: Français");
        expectFrenchNav();

        setConfig("sLanguage", "en_US");
        cy.signInAsMember(LENA);
        cy.visit("/portal/");
        expectEnglishNav();
    });

    it("falls back to the church's language when the stored one is no longer shipped", () => {
        storeLanguage(LENA, "xx_XX");
        cy.signInAsMember(LENA);
        cy.visit("/portal/profile/edit");
        cy.get("#portal-language").should("have.value", "");
        expectEnglishNav();
    });

    it("keeps a staff session opening the portal in the staff account's own language", () => {
        cy.signInAsMember(ALICE);
        saveProfile({ language: "fr_FR" }).its("status").should("eq", 200);
        cy.visit("/portal/");
        expectFrenchNav();

        signInAsStaff();
        cy.visit("/portal/profile/edit");
        cy.get("#portal-profile-form").should("exist");
        cy.get("#portal-language").should("not.exist");
        expectEnglishNav();

        saveProfile({ language: "de_DE" }).then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.updated).to.not.include("language");
            expect(body.profile.language, "the member's own choice is untouched").to.eq("fr_FR");
        });

        setStaffLanguage("de_DE");
        signInAsStaff();
        cy.visit("/portal/");
        cy.get("html").should("have.attr", "lang", "de-DE");
        cy.get(".portal-nav-list").should("contain", "Kalender");
    });
});
