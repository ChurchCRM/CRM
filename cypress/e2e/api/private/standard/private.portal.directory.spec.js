/// <reference types="cypress" />

/**
 * GET /api/portal/directory and the directory photos, plus the admin settings behind them.
 *
 * Seed facts: Mathew Campbell (2) is a Member in the Campbell family with Albert (5), born
 * 15 June 1970, with a photo. Lena Black (100) is a Regular Attender with a photo. Salvador
 * Berry (68) is a deceased Member; Kathryn Ray (18) is a Member of the deactivated Ray family;
 * Mark Smith (104) is Unassigned and awaiting review. The Riley family (18) married on
 * 22 December 2010.
 */
const MEMBER = 2;
const ALBERT_CAMPBELL = 5;
const REGULAR_ATTENDER = 100;
const DECEASED_MEMBER = 68;
const MEMBER_OF_DEACTIVATED_FAMILY = 18;
const AWAITING_REVIEW = 104;
const CAMPBELL_FAMILY = 1;
const RILEY_FAMILY = 18;

const MEMBER_CLASSIFICATION = 1;
const REGULAR_ATTENDER_CLASSIFICATION = 2;
const UNASSIGNED = 0;

const DIRECTORY_URL = "/api/portal/directory";
const SETTINGS_URL = "/admin/api/member-portal/directory";

const DEFAULTS = {
    listed: [MEMBER_CLASSIFICATION],
    viewers: [MEMBER_CLASSIFICATION],
    fields: {
        address: true,
        mailingAddress: false,
        homePhone: true,
        personalPhone: true,
        workPhone: true,
        cellPhone: true,
        email: true,
        workEmail: true,
        photo: true,
    },
    birthday: "withoutYear",
    anniversary: "withoutYear",
    browse: true,
};
const EVERY_CLASSIFICATION = [0, 1, 2, 3, 4, 5];

const adminKey = () => Cypress.testEnv("admin.api.key");

const asAdmin = (method, url, body) =>
    cy.request({
        method,
        url,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body,
        failOnStatusCode: false,
    });

const saveSettings = (settings) => asAdmin("POST", SETTINGS_URL, settings);
const setSwitch = (on) => asAdmin("POST", "/admin/api/system/config/bPortalShowDirectory", { value: on ? "1" : "0" });

const getAs = (who, url) => {
    cy.useMemberSession(who);
    return cy.request({ url, failOnStatusCode: false });
};

const entriesOf = (body) => body.groups.flatMap((group) => group.entries);
const peopleOf = (body) => entriesOf(body).flatMap((entry) => entry.members);
const personOf = (body, id) => peopleOf(body).find((person) => person.id === id);

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key"]);
});

describe("Member Portal directory API", () => {
    beforeEach(() => {
        saveSettings(DEFAULTS).its("status").should("eq", 200);
        setSwitch(true).its("status").should("eq", 200);
    });

    after(() => {
        saveSettings(DEFAULTS);
        setSwitch(false);
    });

    it("refuses an API key", () => {
        cy.request({ url: DIRECTORY_URL, headers: { "x-api-key": adminKey() }, failOnStatusCode: false })
            .its("status")
            .should("eq", 403);
    });

    it("answers 404 while the directory is switched off", () => {
        setSwitch(false);
        getAs(MEMBER, DIRECTORY_URL).its("status").should("eq", 404);
    });

    it("answers 403 to a member whose classification may not see it, from the next request on", () => {
        getAs(REGULAR_ATTENDER, DIRECTORY_URL).its("status").should("eq", 403);

        saveSettings({ viewers: [MEMBER_CLASSIFICATION, REGULAR_ATTENDER_CLASSIFICATION] });
        getAs(REGULAR_ATTENDER, DIRECTORY_URL).its("status").should("eq", 200);

        saveSettings({ viewers: [MEMBER_CLASSIFICATION] });
        getAs(REGULAR_ATTENDER, DIRECTORY_URL).its("status").should("eq", 403);
    });

    it("lists living, active, reviewed people of the listed classifications only", () => {
        getAs(MEMBER, DIRECTORY_URL).then(({ status, body }) => {
            expect(status).to.eq(200);
            const ids = peopleOf(body).map((person) => person.id);
            expect(ids).to.include(MEMBER);
            expect(ids).to.not.include.members([REGULAR_ATTENDER, DECEASED_MEMBER, MEMBER_OF_DEACTIVATED_FAMILY]);
        });

        saveSettings({ listed: [UNASSIGNED] });
        getAs(MEMBER, DIRECTORY_URL).then(({ body }) => {
            const ids = peopleOf(body).map((person) => person.id);
            expect(ids).to.not.include(MEMBER);
            expect(ids).to.not.include(AWAITING_REVIEW);
        });
    });

    it("groups a family with two or more listed members, head first", () => {
        getAs(MEMBER, DIRECTORY_URL).then(({ body }) => {
            const campbell = entriesOf(body).find((entry) => entry.kind === "family" && entry.id === CAMPBELL_FAMILY);
            expect(campbell.name).to.eq("Campbell, Mathew");
            expect(campbell.members.map((person) => person.id)).to.deep.eq([MEMBER, ALBERT_CAMPBELL]);
        });
    });

    it("sends only the fields the administrator switches on", () => {
        saveSettings({ fields: { address: false, homePhone: false, cellPhone: false, email: false, photo: false } });
        getAs(MEMBER, DIRECTORY_URL).then(({ body }) => {
            entriesOf(body).forEach((entry) => {
                expect(entry.address, entry.name).to.deep.eq([]);
                expect(entry.homePhone, entry.name).to.eq("");
                expect(entry.photoUrl, entry.name).to.eq("");
            });
            peopleOf(body).forEach((person) => {
                expect(person.cellPhone, person.name).to.eq("");
                expect(person.email, person.name).to.eq("");
                expect(person.photoUrl, person.name).to.eq("");
            });
        });
    });

    it("shows birthdays and anniversaries with the year, without it, or not at all", () => {
        saveSettings({ birthday: "withYear", anniversary: "withYear" });
        getAs(MEMBER, DIRECTORY_URL).then(({ body }) => {
            expect(personOf(body, MEMBER).birthday).to.contain("1970");
            expect(entriesOf(body).find((entry) => entry.id === RILEY_FAMILY && entry.kind === "family").anniversary).to.contain("2010");
        });

        saveSettings({ birthday: "withoutYear", anniversary: "withoutYear" });
        getAs(MEMBER, DIRECTORY_URL).then(({ body }) => {
            expect(personOf(body, MEMBER).birthday).to.match(/15/).and.not.contain("1970");
            const riley = entriesOf(body).find((entry) => entry.id === RILEY_FAMILY && entry.kind === "family");
            expect(riley.anniversary).to.match(/22/).and.not.contain("2010");
        });

        saveSettings({ birthday: "hidden", anniversary: "hidden" });
        getAs(MEMBER, DIRECTORY_URL).then(({ body }) => {
            peopleOf(body).forEach((person) => expect(person.birthday, person.name).to.eq(""));
            entriesOf(body).forEach((entry) => expect(entry.anniversary, entry.name).to.eq(""));
        });
    });

    it("answers search only with nothing until two characters are typed", () => {
        saveSettings({ browse: false });
        getAs(MEMBER, DIRECTORY_URL).then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.browse).to.eq(false);
            expect(body.groups).to.deep.eq([]);
        });
        getAs(MEMBER, `${DIRECTORY_URL}?q=c`).its("body.groups").should("deep.eq", []);
        getAs(MEMBER, `${DIRECTORY_URL}?q=campbell`).then(({ body }) => {
            const entries = entriesOf(body);
            expect(entries).to.not.be.empty;
            entries.forEach((entry) => expect(entry.searchText).to.contain("campbell"));
        });
    });

    it("answers search only with the first 20 matches, and says there are more", () => {
        saveSettings({ listed: EVERY_CLASSIFICATION });
        getAs(MEMBER, `${DIRECTORY_URL}?q=on`).then(({ body }) => {
            const everyMatch = entriesOf(body).map((entry) => `${entry.kind}-${entry.id}`);
            expect(everyMatch.length, "matches while browsing").to.be.greaterThan(20);

            saveSettings({ browse: false });
            getAs(MEMBER, `${DIRECTORY_URL}?q=on`).then(({ body: searched }) => {
                expect(searched.truncated).to.eq(true);
                expect(entriesOf(searched).map((entry) => `${entry.kind}-${entry.id}`)).to.deep.eq(everyMatch.slice(0, 20));
            });
        });
    });

    it("serves the photo of a listed person and nobody else's", () => {
        getAs(MEMBER, `${DIRECTORY_URL}/person/${MEMBER}/photo`).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["cache-control"]).to.contain("no-store");
        });
        getAs(MEMBER, `${DIRECTORY_URL}/person/${REGULAR_ATTENDER}/photo`).its("status").should("eq", 404);
        getAs(MEMBER, `${DIRECTORY_URL}/family/${RILEY_FAMILY}/photo`).its("status").should("eq", 404);

        saveSettings({ fields: { photo: false } });
        getAs(MEMBER, `${DIRECTORY_URL}/person/${MEMBER}/photo`).its("status").should("eq", 404);
    });
});

describe("Member Portal directory settings API", () => {
    after(() => {
        saveSettings(DEFAULTS);
    });

    it("keeps the parts a request leaves out", () => {
        saveSettings(DEFAULTS);
        saveSettings({ birthday: "hidden" }).then(({ status, body }) => {
            expect(status).to.eq(200);
            expect(body.settings).to.deep.eq({ ...DEFAULTS, birthday: "hidden" });
        });
        asAdmin("GET", SETTINGS_URL).its("body.settings.birthday").should("eq", "hidden");
    });

    [
        ["an unknown classification", { listed: [99] }],
        ["classifications that are not a list", { viewers: "1,2" }],
        ["an unknown field", { fields: { shoeSize: true } }],
        ["a field that is not true or false", { fields: { email: "yes" } }],
        ["an unknown date choice", { anniversary: "sometimes" }],
        ["browse that is not true or false", { browse: "no" }],
        ["an unknown setting", { everyone: true }],
    ].forEach(([what, body]) => {
        it(`refuses ${what} and saves nothing`, () => {
            saveSettings(DEFAULTS);
            saveSettings(body).its("status").should("eq", 400);
            asAdmin("GET", SETTINGS_URL).its("body.settings").should("deep.eq", DEFAULTS);
        });
    });

    it("is for administrators only", () => {
        cy.request({ url: SETTINGS_URL, headers: { "x-api-key": Cypress.testEnv("user.api.key") }, failOnStatusCode: false })
            .its("status")
            .should("be.oneOf", [401, 403]);
    });
});
