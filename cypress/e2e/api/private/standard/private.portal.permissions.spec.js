/// <reference types="cypress" />

/**
 * Member sign-in, row 2 (#10463): the portal permissions model
 * (docs/member-sign-in-design.md §8, §4 "Teenagers", §17 "Portal permissions").
 *
 * Members sign in with their email (a portal-only session); the administrator
 * signs in at /admin and opens the portal (a STAFF principal). Grants are given
 * through the admin API and a person's role and birth year in the person editor.
 */

import { inOwnCookieJar } from "../../../../support/cookie-jar";
import { endPortalGrant } from "../../../../support/member-sign-in-fixtures";
import { savePersonEditor } from "../../../../support/person-editor";

const LEADER = { personId: 100 };
const NON_ADULT = { personId: 4, familyId: 1 };
const ADMIN = { username: "admin", password: "changeme" };
const AMANDA_EMAIL = "amanda.black@example.com";
const MATHEW_EMAIL = "mathew.campbell@example.com";
const PREFIX = "PERM10463";
const VERSION_URL = "/admin/api/system/config/sVolunteerVersion";
const touchedConfig = ["bPortalShowVolunteer"];
const NON_ADULT_SEED = { FamilyRole: 4, BirthYear: 1910, BirthMonth: 7, BirthDay: 23 };

let ministryId = 0;
let teamLed = 0;
let teamOther = 0;
let leaderScopeId = 0;
let originalVersion = "v1";

const givenGrants = [];

const signIn = ({ username, password, personId }) => {
    if (username === undefined) {
        cy.signInAsMember(personId);
    } else {
        cy.clearCookies();
        cy.request({ method: "POST", url: "/admin/", form: true, body: { User: username, Password: password } });
    }
    cy.request("/portal/").its("status").should("eq", 200);
};

const status = (url) => cy.request({ url, failOnStatusCode: false, followRedirect: false }).its("status");

const navIds = () =>
    cy.request("/portal/").then((response) => {
        const doc = new DOMParser().parseFromString(response.body, "text/html");
        return [...doc.querySelectorAll(".portal-nav-link")].map((link) => link.getAttribute("href"));
    });

const csrfToken = () =>
    cy.request("/portal/profile/edit").then((response) => {
        const match = response.body.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i);
        expect(match, "csrf_token").to.not.be.null;
        return match[1];
    });

const tomorrow = () => new Date(Date.now() + 86400000).toISOString().slice(0, 10);

/** Gives a portal permission as the administrator; yields the grant id. */
const giveGrant = (personId, capability, scopeId, endsOn = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10)) =>
    inOwnCookieJar(() =>
        cy
            .makePrivateAdminAPICall("POST", `/admin/api/person/${personId}/sign-in/grants`, { capability, scopeId, endsOn, reason: "Cypress" }, 201)
            .then(({ body }) => {
                givenGrants.push({ personId, grantId: body.grantId });
            }),
    ).then(() => givenGrants[givenGrants.length - 1].grantId);

const setConfig = (name, value) => inOwnCookieJar(() => cy.setSystemConfig(name, value));

function cleanupFixtures() {
    cy.makePrivateAdminAPICall("GET", "/api/ministries/ministries", null, 200).then(({ body }) => {
        body.ministries
            .filter((ministry) => ministry.name.startsWith(PREFIX))
            .forEach((ministry) => {
                cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministry.id}`, { active: false }, [200, 404]);
                cy.makePrivateAdminAPICall("DELETE", `/api/ministries/ministries/${ministry.id}`, null, [200, 204, 404]);
            });
    });
}

describe("Member Portal permissions (portal permissions model)", () => {
    const savedConfig = {};
    let savedCalendars;

    before(() => {
        cy.getMemberPortalCalendars().then((calendars) => {
            savedCalendars = calendars;
        });
        touchedConfig.forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                savedConfig[name] = value;
            });
        });
        cy.makePrivateAdminAPICall("GET", VERSION_URL, null, 200).then((response) => {
            originalVersion = response.body.value ?? response.body.Value ?? "v1";
        });
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: "v2" }, 200);
        cleanupFixtures();

        cy.makePrivateAdminAPICall("POST", "/api/ministries/ministries", { name: `${PREFIX} Hospitality`, description: "" }, 201).then(
            (response) => {
                ministryId = response.body.ministry.id;
            },
        );
        cy.then(() => {
            cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministryId}/teams`, { name: `${PREFIX} Greeters` }, 201).then(
                (response) => {
                    teamLed = response.body.team.id;
                },
            );
            cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministryId}/teams`, { name: `${PREFIX} Ushers` }, 201).then(
                (response) => {
                    teamOther = response.body.team.id;
                },
            );
        });
    });

    beforeEach(() => {
        cy.then(() => {
            if (leaderScopeId === 0) {
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/ministries/scopes",
                    { personId: LEADER.personId, scopeType: "team", scopeId: teamLed },
                    [200, 201],
                ).then((response) => {
                    leaderScopeId = response.body.scope.id;
                });
            }
        });
    });

    afterEach(() => {
        cy.clearCookies();
        touchedConfig.forEach((name) => cy.restoreSystemConfig(name, savedConfig[name]));
        givenGrants.splice(0).forEach(({ personId, grantId }) => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${personId}/sign-in/grants/${grantId}/remove`, null, [200, 404]);
        });
        cy.clearCookies();
    });

    after(() => {
        cleanupFixtures();
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: originalVersion }, 200);
        cy.then(() => cy.shareMemberPortalCalendars(savedCalendars));
    });

    describe("Team leaders", () => {
        it("manages only their own team in a portal-only session, and gets 404 for another", () => {
            signIn(LEADER);

            cy.request("/portal/teams").then((response) => {
                expect(response.body).to.contain(`${PREFIX} Greeters`);
                expect(response.body).to.not.contain(`${PREFIX} Ushers`);
            });
            navIds().should("include", "/portal/teams");
            status(`/portal/teams/${teamLed}`).should("eq", 200);
            status(`/portal/teams/${teamOther}`).should("eq", 404);
            status("/portal/teams/987654").should("eq", 404);

            status(`/api/ministries/teams/${teamLed}`).should("eq", 200);
            status(`/api/ministries/teams/${teamOther}`).should("eq", 403);
            status("/api/ministries/me/assignments").should("eq", 200);
            status(`/api/person/${LEADER.personId}`).should("eq", 401);
        });

        it("loses the capability on the next request when removed from the team", () => {
            signIn(LEADER);
            status(`/portal/teams/${teamLed}`).should("eq", 200);

            cy.then(() => cy.makePrivateAdminAPICall("DELETE", `/api/ministries/scopes/${leaderScopeId}`, null, [200, 204]));
            cy.then(() => {
                leaderScopeId = 0;
            });

            status(`/portal/teams/${teamLed}`).should("eq", 404);
            status("/portal/teams").should("eq", 403);
            status(`/api/ministries/teams/${teamLed}`).should("eq", 403);
            navIds().should("not.include", "/portal/teams");
        });

        it("gives a STAFF principal no team it does not lead", () => {
            signIn(ADMIN);
            status("/portal/teams").should("eq", 403);
            status(`/portal/teams/${teamLed}`).should("eq", 404);
            navIds().should("not.include", "/portal/teams");
        });
    });

    describe("Teenagers and other non-adults", () => {
        it("lets an adult of the family see the members' contact details and confirm", () => {
            signIn(LEADER);
            cy.request("/api/portal/family").then(({ body }) => {
                expect(body.canSeeContacts).to.eq(true);
                expect(body.canConfirm).to.eq(true);
                expect(body.members.map((member) => member.email)).to.include(AMANDA_EMAIL);
            });
            cy.request("/portal/family").its("body").should("contain", "data-member-contact");
        });

        it("hides other members' email and phone from a non-adult, who cannot confirm the family", () => {
            signIn(NON_ADULT);
            cy.request("/api/portal/family").then(({ body }) => {
                expect(body.canSeeContacts).to.eq(false);
                expect(body.canConfirm).to.eq(false);
                expect(body.canEdit).to.eq(false);
                body.members
                    .filter((member) => !member.isSelf)
                    .forEach((member) => {
                        expect(member.email, member.fullName).to.eq("");
                        expect(member.cellPhone, member.fullName).to.eq("");
                    });
                expect(body.members.find((member) => member.isSelf).email).to.not.eq("");
            });
            cy.request("/portal/family").then(({ body }) => {
                expect(body).to.not.contain("data-member-contact");
                expect(body).to.not.contain("portal-family-confirm-card");
                expect(body).to.not.contain(MATHEW_EMAIL);
            });
            status("/portal/family/confirm").should("eq", 403);
            csrfToken().then((token) => {
                cy.request({
                    method: "POST",
                    url: "/api/portal/family/confirm",
                    body: { result: "no-change" },
                    headers: { "X-CSRF-Token": token },
                    failOnStatusCode: false,
                })
                    .its("status")
                    .should("eq", 403);
            });
        });

        describe("as a spouse", () => {
            afterEach(() => {
                savePersonEditor(NON_ADULT.personId, NON_ADULT_SEED).its("status").should("eq", 302);
                cy.clearCookies();
            });

            it("counts a head of household or spouse as an adult from 18, or when the birth year is unknown", () => {
                const year = new Date().getFullYear();
                const contactsVisible = () => cy.request("/api/portal/family").its("body.canSeeContacts");
                const saveBirthYear = (birthYear) =>
                    inOwnCookieJar(() => savePersonEditor(NON_ADULT.personId, { BirthYear: birthYear }).its("status").should("eq", 302));

                savePersonEditor(NON_ADULT.personId, { FamilyRole: 2, BirthYear: year - 16, BirthMonth: 1, BirthDay: 1 })
                    .its("status")
                    .should("eq", 302);
                signIn(NON_ADULT);
                contactsVisible().should("eq", false);

                saveBirthYear(year - 30);
                contactsVisible().should("eq", true);

                saveBirthYear("");
                contactsVisible().should("eq", true);
            });
        });
    });

    describe("Given grants", () => {
        it("works until its end date", () => {
            giveGrant(NON_ADULT.personId, "portal.family.contacts.view", NON_ADULT.familyId, tomorrow()).then((grantId) => {
                signIn(NON_ADULT);
                cy.request("/api/portal/family").then(({ body }) => {
                    expect(body.canSeeContacts).to.eq(true);
                    expect(body.canConfirm).to.eq(false);
                    expect(body.members.map((member) => member.email)).to.include(MATHEW_EMAIL);
                });

                endPortalGrant(grantId);
                cy.request("/api/portal/family").its("body.canSeeContacts").should("eq", false);
            });
        });

        it("lets a member run a team they were given, through the volunteer API too, until it ends", () => {
            giveGrant(LEADER.personId, "volunteer.team.manage", teamOther).then((grantId) => {
                signIn(LEADER);
                status(`/portal/teams/${teamOther}`).should("eq", 200);
                status(`/api/ministries/teams/${teamOther}`).should("eq", 200);

                endPortalGrant(grantId);
                status(`/portal/teams/${teamOther}`).should("eq", 404);
                status(`/api/ministries/teams/${teamOther}`).should("eq", 403);
            });
        });

        it("is ignored once its team is deleted", () => {
            let teamId = 0;
            inOwnCookieJar(() =>
                cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministryId}/teams`, { name: `${PREFIX} Parking` }, 201).then(({ body }) => {
                    teamId = body.team.id;
                }),
            );
            cy.then(() => giveGrant(NON_ADULT.personId, "volunteer.team.manage", teamId));
            signIn(NON_ADULT);
            navIds().should("include", "/portal/teams");

            cy.then(() => inOwnCookieJar(() => cy.makePrivateAdminAPICall("DELETE", `/api/ministries/teams/${teamId}`, null, [200, 204])));
            navIds().should("not.include", "/portal/teams");
        });
    });

    describe("Feature switches", () => {
        it("denies the calendar to everyone while no calendar is shared", () => {
            cy.shareMemberPortalCalendars([{ type: "calendar", id: 1 }]);
            signIn(LEADER);
            status("/portal/calendar").should("eq", 200);
            status("/api/portal/calendar/events").should("eq", 200);
            navIds().should("include", "/portal/calendar");

            cy.shareMemberPortalCalendars([]);
            signIn(LEADER);
            status("/portal/calendar").should("eq", 404);
            status("/api/portal/calendar/events").should("eq", 404);
            status("/api/portal/calendar/subscription").should("eq", 404);
            navIds().should("not.include", "/portal/calendar");
        });

        it("denies volunteering and team management while volunteering is switched off", () => {
            signIn(LEADER);
            status(`/portal/teams/${teamLed}`).should("eq", 200);

            setConfig("bPortalShowVolunteer", "0");
            status("/portal/volunteer/schedule").should("eq", 404);
            status("/portal/teams").should("eq", 404);
            status(`/portal/teams/${teamLed}`).should("eq", 404);
            status("/api/ministries/me/assignments").should("eq", 403);
            status(`/api/ministries/teams/${teamLed}`).should("eq", 403);
        });
    });
});
