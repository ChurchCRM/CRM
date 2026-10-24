/// <reference types="cypress" />

import { inOwnCookieJar } from "../../support/cookie-jar";
import { savePersonEditor } from "../../support/person-editor";

// Login as Person eligibility (#10463 row 12, docs/member-sign-in-design.md §6.5, §17):
// only deceased, Disable sign-in, a staff account and member sign-in turned off refuse
// it, at the start and on every request after it. Everything else that stops a member
// signing in does not.
const CHILD = 5; // Albert Campbell, a child of the Campbell family
const TARGET = 9; // Jean Hart
const INACTIVE = 10; // Tom Hart
const TURNER = 115; // Michael Turner, head of the Turner family
const TURNER_FAMILY = 23;
const SHARED_EMAIL = 114; // Boby Hall, whose email ten other people have too
const ADMIN = 1;
const SETTINGS = [
    "bAllowLoginAsPerson",
    "bLoginAsPersonCanWrite",
    "bMemberSignIn",
    "sMemberSignInFamilyRoles",
    "sMemberSignInClassifications",
    "iMemberSignInMinAge",
];

const admin = (method, url, body = null, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);

const setSetting = (name, value) => admin("POST", `admin/api/system/config/${name}`, { value });

const signInStatus = (personId) => admin("GET", `api/person/${personId}/sign-in`).its("body");

// Posts the staff sign-in form: a staff page left open in the browser could
// otherwise reload the form while Cypress typed into it.
const signInAsAdmin = () =>
    cy.env(["admin.username", "admin.password"]).then((env) => {
        cy.clearCookies();
        cy.request({
            method: "POST",
            url: "/admin/",
            form: true,
            followRedirect: false,
            body: { User: env["admin.username"], Password: env["admin.password"] },
        })
            .its("status")
            .should("eq", 302);
    });

const startLoginAsPerson = (personId) =>
    cy.request(`/people/view/${personId}`).then(({ body }) =>
        cy.request({
            method: "POST",
            url: `/admin/api/person/${personId}/login-as-person`,
            headers: { "X-CSRF-Token": body.match(/data-csrf-token="([^"]+)"/)[1] },
            body: {},
            failOnStatusCode: false,
        }),
    );

const expectPortalPages = (name) => {
    ["/portal/", "/portal/profile"].forEach((url) => {
        cy.request({ url, followRedirect: false }).then((response) => {
            expect(response.status, url).to.eq(200);
            expect(response.body, url).to.include(`logged in as ${name} in the Member Portal (read-only).`);
        });
    });
};

const stopLoginAsPerson = () =>
    cy.request("/portal/").then(({ body }) =>
        cy
            .request({
                method: "POST",
                url: "/portal/login-as-person/stop",
                form: true,
                body: { csrf_token: body.match(/name="csrf_token" value="([^"]+)"/)[1] },
                followRedirect: false,
            })
            .its("status")
            .should("eq", 303),
    );

const opensAndStops = (personId, name) => {
    startLoginAsPerson(personId).its("status").should("eq", 200);
    expectPortalPages(name);
    stopLoginAsPerson();
};

const newestAuditId = (personId) =>
    admin("GET", `admin/api/member-portal/audit?personId=${personId}&length=1`).then(({ body }) => body.rows[0]?.id ?? 0);

const auditSince = (personId, afterId) =>
    admin("GET", `admin/api/member-portal/audit?personId=${personId}&length=100`).then(({ body }) =>
        body.rows
            .filter((row) => row.id > afterId)
            .reverse()
            .map((row) => ({ event: row.event, reason: row.reason, actorId: row.actorId })),
    );

describe("Login as Person eligibility", () => {
    const saved = {};

    before(() => {
        SETTINGS.forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                saved[name] = value;
            });
        });
    });

    beforeEach(() => {
        setSetting("bAllowLoginAsPerson", "1");
        setSetting("bLoginAsPersonCanWrite", "0");
        setSetting("bMemberSignIn", "1");
        signInAsAdmin();
    });

    afterEach(() => {
        Object.entries(saved).forEach(([name, value]) => cy.restoreSystemConfig(name, value));
        admin("POST", `api/person/${INACTIVE}/activate/true`);
        admin("POST", `api/family/${TURNER_FAMILY}/activate/true`);
        admin("POST", `admin/api/person/${TARGET}/sign-in/enable`, null, [200, 409]);
        admin("DELETE", `admin/api/user/${TARGET}/`, null, [200, 404]);
    });

    describe("still opens, page after page, where a member could not sign in", () => {
        it("an inactive person", () => {
            admin("POST", `api/person/${INACTIVE}/activate/false`);
            signInStatus(INACTIVE).its("reason").should("eq", "inactive");
            opensAndStops(INACTIVE, "Tom Hart");
        });

        it("a person in a deactivated family", () => {
            admin("POST", `api/family/${TURNER_FAMILY}/activate/false`);
            signInStatus(TURNER).its("reason").should("eq", "family_inactive");
            opensAndStops(TURNER, "Michael Turner");
        });

        it("a person awaiting review", () => {
            cy.request("POST", "/api/public/register/person", { firstName: "Rowtwelve", lastName: "Review" }).then(
                ({ body }) => {
                    const personId = Number(body.Id);
                    signInStatus(personId).its("reason").should("eq", "needs_review");
                    opensAndStops(personId, "Rowtwelve Review");
                    cy.cleanupPeople([personId]);
                },
            );
        });

        it("a person outside the member rule", () => {
            setSetting("bMemberSignIn", "1");
            setSetting("sMemberSignInFamilyRoles", "1,2");
            signInStatus(CHILD).its("reason").should("eq", "family_role");
            opensAndStops(CHILD, "Albert Campbell");
        });

        it("a person with no usable sign-in email", () => {
            setSetting("sMemberSignInFamilyRoles", "0,1,2");
            setSetting("sMemberSignInClassifications", "1");
            signInStatus(SHARED_EMAIL).its("reason").should("eq", "shared_email");
            opensAndStops(SHARED_EMAIL, "Boby Hall");
        });
    });

    describe("refuses at the start, and ends an open session on the next request", () => {
        const endsOnNextRequest = (block, stored, message, ended = "Ended by a change to their sign-in") => {
            newestAuditId(TARGET).then((afterId) => {
                startLoginAsPerson(TARGET).its("status").should("eq", 200);
                expectPortalPages("Jean Hart");
                block();
                cy.request({ url: "/portal/profile", followRedirect: false }).then((response) => {
                    expect(response.status).to.eq(302);
                    expect(response.headers.location).to.match(new RegExp(`/people/view/${TARGET}$`));
                });
                auditSince(TARGET, afterId)
                    .then((rows) => rows.filter((row) => ["login_as_started", "session_ended"].includes(row.event)))
                    .should("deep.equal", [
                        { event: "login_as_started", reason: null, actorId: ADMIN },
                        { event: "session_ended", reason: stored, actorId: ADMIN },
                    ]);

                cy.visit(`/people/view/${TARGET}`);
                cy.contains(".notyf__toast", `Login as Person ended: ${message}`).should("be.visible");
                cy.get("#login-as-person-history [data-cy=login-as-person-session]")
                    .first()
                    .should("contain", ended);
                startLoginAsPerson(TARGET).then((response) => {
                    expect(response.status).to.eq(403);
                    expect(response.body.message).to.include(message);
                });
            });
        };

        describe("deceased", () => {
            afterEach(() => {
                savePersonEditor(TARGET, { IsDeceased: false }).its("status").should("eq", 302);
                cy.clearCookies();
            });

            it("deceased", () => {
                endsOnNextRequest(
                    () => inOwnCookieJar(() => savePersonEditor(TARGET, { IsDeceased: true }).its("status").should("eq", 302)),
                    "deceased",
                    "This person is deceased.",
                );
            });
        });

        it("Disable sign-in", () => {
            endsOnNextRequest(
                () => admin("POST", `admin/api/person/${TARGET}/sign-in/disable`, { reason: "Login as Person eligibility spec" }),
                "sign_in_disabled",
                "Sign-in is disabled for this person.",
            );
        });

        it("member sign-in turned off", () => {
            endsOnNextRequest(
                () => setSetting("bMemberSignIn", "0"),
                "member_sign_in_off",
                "Member sign-in is off.",
                "Ended when member sign-in was turned off",
            );
        });

        it("a staff account", () => {
            endsOnNextRequest(
                () =>
                    cy.readEnv("admin.api.key").then((key) =>
                        cy
                            .request({
                                method: "POST",
                                url: "/admin/system/users/new",
                                headers: { "x-api-key": key },
                                form: true,
                                body: { PersonID: TARGET, UserName: "row12.eligibility" },
                                followRedirect: false,
                            })
                            .its("status")
                            .should("eq", 302),
                    ),
                "staff_account",
                "This person has a staff account.",
            );
        });
    });
});
