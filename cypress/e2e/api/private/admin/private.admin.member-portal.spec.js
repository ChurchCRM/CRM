/// <reference types="cypress" />

/**
 * Admin → Member Portal APIs (MP3, #9864).
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §4.
 *
 *   POST /admin/api/member-portal/theme  {name}
 *     400 — the name is missing, malformed, or names no folder on disk
 *     409 — the validator found errors; nothing is written
 *     200 — activated; warnings come back with it
 *   GET  /admin/api/member-portal/theme/{name}/validation — the "Check" button
 *   GET  /admin/api/member-portal/stats — the statistics tab's numbers
 *
 * The spec writes throwaway theme folders on disk (the webserver container
 * bind-mounts ../src) and removes them again in after().
 */
const GOOD_THEME = "cypressapigood";
const WARNING_THEME = "cypressapiwarn";
const BROKEN_THEME = "cypressapibroken";
const THEME_DIRS = [GOOD_THEME, WARNING_THEME, BROKEN_THEME].map((id) => `src/Include/themes/${id}`);

// usr_UserName is VARCHAR(50) since #9831, so the seeded address is stored whole.
const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const MEMBER_PERSON_ID = 100;

describe("API Private Admin Member Portal", () => {
    before(() => {
        // Valid: colour only, no templates at all.
        cy.writeFile(`src/Include/themes/${GOOD_THEME}/theme.css`, ":root { --portal-primary: #123456; }\n");
        // Warns: an override at a path this version of ChurchCRM does not render.
        cy.writeFile(
            `src/Include/themes/${WARNING_THEME}/templates/not-a-real-page.html.twig`,
            "<p>{{ gettext('Nothing includes this') }}</p>\n"
        );
        // Errors: an unterminated block.
        cy.writeFile(
            `src/Include/themes/${BROKEN_THEME}/templates/home.html.twig`,
            '{% extends "@default/layout.html.twig" %}\n{% block content %}\n'
        );
    });

    after(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: "default" }, 200);
        cy.exec(`rm -rf ${THEME_DIRS.join(" ")}`, { failOnNonZeroExit: false });
    });

    describe("POST /admin/api/member-portal/theme", () => {
        it("Activates a valid theme and reports no findings", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: GOOD_THEME }, 200).then(
                (resp) => {
                    expect(resp.body.name).to.eq(GOOD_THEME);
                    expect(resp.body.activated).to.eq(true);
                    expect(resp.body.status).to.eq("ok");
                    expect(resp.body.findings).to.deep.eq([]);
                }
            );

            cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/sMemberPortalTheme", null, 200).then((resp) => {
                expect(resp.body.value).to.eq(GOOD_THEME);
            });
        });

        it("Activates a theme that only warns, and returns the warnings", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: WARNING_THEME }, 200).then(
                (resp) => {
                    expect(resp.body.activated).to.eq(true);
                    expect(resp.body.status).to.eq("warning");
                    expect(resp.body.findings).to.have.length.greaterThan(0);
                    expect(resp.body.findings[0].level).to.eq("warning");
                    expect(resp.body.findings[0].file).to.eq("not-a-real-page.html.twig");
                }
            );
        });

        it("Refuses a theme with template errors with 409 and never writes the value", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: "default" }, 200);

            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: BROKEN_THEME }, 409).then(
                (resp) => {
                    expect(resp.body.activated).to.eq(false);
                    expect(resp.body.status).to.eq("error");
                    expect(resp.body.findings).to.have.length.greaterThan(0);
                    expect(resp.body.findings[0].level).to.eq("error");
                    expect(resp.body.findings[0].file).to.eq("home.html.twig");
                }
            );

            cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/sMemberPortalTheme", null, 200).then((resp) => {
                expect(resp.body.value).to.eq("default");
            });
        });

        it("Rejects a name that is not a theme folder with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: "no-such-theme" }, 400);
        });

        it("Rejects a traversal attempt with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: "../../Include" }, 400);
        });

        it("Rejects an empty name with 400", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: "" }, 400);
        });

        it("Is not reachable by a non-admin API key", () => {
            cy.makePrivateUserAPICall("POST", "/admin/api/member-portal/theme", { name: "default" }, 403);
        });
    });

    describe("GET /admin/api/member-portal/theme/{name}/validation", () => {
        it("Reports a valid theme without changing the active one", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/theme", { name: "default" }, 200);

            cy.makePrivateAdminAPICall(
                "GET",
                `/admin/api/member-portal/theme/${GOOD_THEME}/validation`,
                null,
                200
            ).then((resp) => {
                expect(resp.body.name).to.eq(GOOD_THEME);
                expect(resp.body.status).to.eq("ok");
                expect(resp.body.findings).to.deep.eq([]);
            });

            cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/sMemberPortalTheme", null, 200).then((resp) => {
                expect(resp.body.value).to.eq("default");
            });
        });

        it("Reports the findings of a broken theme", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/admin/api/member-portal/theme/${BROKEN_THEME}/validation`,
                null,
                200
            ).then((resp) => {
                expect(resp.body.status).to.eq("error");
                expect(resp.body.findings[0].file).to.eq("home.html.twig");
                expect(resp.body.findings[0].message).to.be.a("string").and.not.be.empty;
            });
        });

        it("Returns 404 for a folder that does not exist", () => {
            cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/theme/no-such-theme/validation", null, 404);
        });
    });

    describe("GET /admin/api/member-portal/stats", () => {
        it("Returns the documented shape", () => {
            cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/stats", null, 200).then((resp) => {
                for (const key of [
                    "totalAccounts",
                    "activeNow",
                    "signedIn24Hours",
                    "signedIn7Days",
                    "signedIn30Days",
                    "neverSignedIn",
                    "activeWindowMinutes",
                ]) {
                    expect(resp.body[key], key).to.be.a("number");
                }
                expect(resp.body.activeWindowMinutes).to.eq(15);
                expect(resp.body.recentSignIns).to.be.an("array");
                // The seed carries three self-service accounts: lena.black,
                // amanda.black and limited.user.
                expect(resp.body.totalAccounts).to.be.at.least(3);
                expect(resp.body.signedIn30Days).to.be.at.least(resp.body.signedIn7Days);
                expect(resp.body.signedIn7Days).to.be.at.least(resp.body.signedIn24Hours);
            });
        });

        it("Counts a member sign-in and lists it first", () => {
            cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/stats", null, 200).then((before) => {
                cy.request({
                    method: "POST",
                    url: "/session/begin",
                    form: true,
                    body: { User: MEMBER_USER, Password: MEMBER_PASSWORD },
                    followRedirect: false,
                });

                cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/stats", null, 200).then((after) => {
                    expect(after.body.signedIn24Hours).to.be.at.least(1);
                    expect(after.body.totalAccounts).to.eq(before.body.totalAccounts);
                    expect(after.body.neverSignedIn).to.be.at.most(before.body.neverSignedIn);
                    expect(after.body.recentSignIns[0].personId).to.eq(MEMBER_PERSON_ID);
                    expect(after.body.recentSignIns[0].userName).to.eq(MEMBER_USER);
                    expect(after.body.recentSignIns[0].lastLogin).to.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
                });
            });
        });

        it("Stamps usr_LastPortalActivity on a portal request and leaves it alone for five minutes", () => {
            // A real member session, then a portal page: the middleware stamps.
            cy.request({
                method: "POST",
                url: "/session/begin",
                form: true,
                body: { User: MEMBER_USER, Password: MEMBER_PASSWORD },
                followRedirect: false,
            });
            cy.request("/portal/");

            cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/stats", null, 200).then((first) => {
                const member = first.body.recentSignIns.find((row) => row.personId === MEMBER_PERSON_ID);
                expect(member, "the member appears in the recent sign-ins").to.exist;
                expect(member.lastPortalActivity).to.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
                expect(first.body.activeNow).to.be.at.least(1);

                // The API-key call above replaced the browser session, so sign
                // in again — the throttle must hold across that, because it
                // compares the stored value and not a session flag.
                cy.request({
                    method: "POST",
                    url: "/session/begin",
                    form: true,
                    body: { User: MEMBER_USER, Password: MEMBER_PASSWORD },
                    followRedirect: false,
                });
                cy.request("/portal/");
                cy.request("/portal/");

                cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/stats", null, 200).then((second) => {
                    const again = second.body.recentSignIns.find((row) => row.personId === MEMBER_PERSON_ID);
                    expect(again.lastPortalActivity).to.eq(member.lastPortalActivity);
                });
            });
        });

        it("Is not reachable by a non-admin API key", () => {
            cy.makePrivateUserAPICall("GET", "/admin/api/member-portal/stats", null, 403);
        });
    });
});
