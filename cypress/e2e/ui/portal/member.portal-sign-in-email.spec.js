/// <reference types="cypress" />

import {
    ANSWER,
    askForEmail,
    auditSince,
    db,
    expectAnswerScreen,
    lastAuditId,
    mailTo,
    postCode,
    requestsFor,
    resetEmailSignIn,
    restoreSettings,
    setConfig,
    signInEmail,
    useMemberSignIn,
    waitForMail,
} from "../../../support/member-sign-in";

/**
 * Member sign-in row 5 (#10463): /portal/login, the emailed code and link
 * (docs/member-sign-in-design.md §6.1, §7, §14, §17 "Email sign-in").
 * Seed person 6, Constance Hart: head of household, Member, born 1966.
 */

const member = { personId: 6, firstName: "Constance", email: "constance.hart@example.com" };

const askInBrowser = (email = member.email) => {
    cy.visit("/portal/login");
    cy.get("#portalSignInEmail").type(email);
    cy.get("#portalSignInSubmit").click();
    cy.url().should("include", "/portal/login/code");
    cy.get("#portalSignInAnswer").should("contain.text", ANSWER);
};

const typeCode = (code) => {
    cy.get("#portalSignInCode").clear().type(code);
    cy.get("#portalSignInCodeSubmit").click();
};

const signInWithLink = () => {
    askForEmail(member.email);
    signInEmail(member.email).then(({ link }) => {
        cy.visit(link);
        cy.get("#portalSignInConfirmSubmit").click();
        cy.location("pathname").should("match", /\/portal\/$/);
    });
};

const wrongCode = (code) => String((Number(code) + 1) % 1000000).padStart(6, "0");

const auditOnceDetailed = (startId, waited = 0) =>
    auditSince(startId).then((rows) => {
        if (rows[0]?.psa_Detail || waited >= 10000) {
            return rows;
        }
        return cy.wait(200, { log: false }).then(() => auditOnceDetailed(startId, waited + 200));
    });

describe("Member Portal sign-in by email", () => {
    const saved = {};

    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        useMemberSignIn(saved);
    });

    beforeEach(() => {
        cy.clearCookies();
        resetEmailSignIn([member.personId]);
    });

    afterEach(() => {
        cy.task("mail:refuseRecipients", { refuse: false });
    });

    after(() => {
        restoreSettings(saved);
    });

    it("emails one code and one link to a person who may sign in, and stores neither", () => {
        lastAuditId().then((startId) => {
            askInBrowser();

            waitForMail(member.email).should("have.length", 1);
            signInEmail(member.email).then(({ code, link }) => {
                const token = link.split("/").pop();
                requestsFor(member.personId).then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(rows[0].psr_UsedAt).to.be.null;
                    expect(rows[0].psr_CancelledAt).to.be.null;
                    expect(rows[0].psr_Email).to.eq(member.email);
                    expect(JSON.stringify(rows)).to.not.include(token).and.not.include(code);
                });
                auditSince(startId).then((rows) => {
                    expect(rows.map((row) => row.psa_Event)).to.deep.equal(["email_requested"]);
                    expect(rows[0].psa_per_ID).to.eq(member.personId);
                    expect(rows[0].psa_Email).to.eq(member.email);
                    expect(JSON.stringify(rows)).to.not.include(token).and.not.include(code);
                });
            });
        });
    });

    it("answers the same when the email can't be sent, and marks the request's audit row", () => {
        lastAuditId().then((startId) => {
            cy.task("mail:refuseRecipients", { refuse: true }).its("ok").should("eq", true);
            askForEmail(member.email).then(expectAnswerScreen);

            auditOnceDetailed(startId).then((rows) => {
                expect(rows.map((row) => row.psa_Event)).to.deep.equal(["email_requested"]);
                expect(rows[0].psa_Detail).to.eq("send_failed");
            });
            requestsFor(member.personId).should("have.length", 1);
            mailTo(member.email).should("have.length", 0);
        });
    });

    it("opens a confirm page from the link and changes nothing until Sign in is pressed", () => {
        askForEmail(member.email);
        signInEmail(member.email).then(({ link }) => {
            requestsFor(member.personId).then((before) => {
                cy.clearCookies();
                cy.visit(link);
                cy.visit(link);
                cy.get("#portalSignInConfirmTitle").should("have.text", `Sign in as ${member.firstName}?`);
                cy.get("#portalSignInChurch").should("have.text", "Main St. Cathedral");
                cy.get("#portalRememberDevice").should("not.be.checked");
                cy.get("#portalRememberDeviceHint").should("contain.text", "never on a shared or public one");

                requestsFor(member.personId).should("deep.equal", before);
                cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            });
        });
    });

    it("signs in from the confirm page once, and the link then reads as used", () => {
        lastAuditId().then((startId) => {
            askForEmail(member.email);
            signInEmail(member.email).then(({ link }) => {
                cy.clearCookies();
                cy.visit(link);
                cy.get("#portalSignInConfirmSubmit").click();
                cy.location("pathname").should("match", /\/portal\/$/);
                cy.get("#portal-account-toggle").should("contain.text", `Hello ${member.firstName}`);

                auditSince(startId).then((rows) => {
                    const signedIn = rows.filter((row) => row.psa_Event === "signed_in");
                    expect(signedIn).to.have.length(1);
                    expect(signedIn[0].psa_Method).to.eq("email_link");
                    expect(signedIn[0].psa_Email).to.eq(member.email);
                });
                db("SELECT psi_ProvenEmail, psi_LastMethod FROM person_signin_psi WHERE psi_per_ID = ?", [member.personId]).then(
                    (rows) => {
                        expect(rows[0].psi_ProvenEmail).to.eq(member.email);
                        expect(rows[0].psi_LastMethod).to.eq("email_link");
                    },
                );

                cy.clearCookies();
                cy.visit(link);
                cy.get("#portalSignInLinkExpired").should("contain.text", "This link has expired or was already used");
                cy.get("#portalSignInLinkExpired a").should("have.attr", "href").and("match", /\/portal\/login$/);
            });
        });
    });

    it("accepts the code only in the browser that asked for it", () => {
        lastAuditId().then((startId) => {
            askInBrowser();
            cy.get("#portalRememberDevice").should("not.be.checked");
            signInEmail(member.email).then(({ code }) => {
                cy.getCookies().then((askingBrowser) => {
                    cy.clearCookies();
                    postCode(code).its("body").should("include", "That code did not work");
                    requestsFor(member.personId).its("0.psr_UsedAt").should("be.null");

                    cy.clearCookies();
                    askingBrowser.forEach((cookie) => cy.setCookie(cookie.name, cookie.value, { path: cookie.path }));
                    cy.visit("/portal/login/code");
                    typeCode(code);
                    cy.location("pathname").should("match", /\/portal\/$/);
                    auditSince(startId).then((rows) => {
                        const signedIn = rows.filter((row) => row.psa_Event === "signed_in");
                        expect(signedIn).to.have.length(1);
                        expect(signedIn[0].psa_Method).to.eq("email_code");
                    });
                });
            });
        });
    });

    it("cancels the request after five wrong codes", () => {
        lastAuditId().then((startId) => {
            askInBrowser();
            signInEmail(member.email).then(({ code }) => {
                Cypress._.times(5, () => {
                    typeCode(wrongCode(code));
                    cy.get("#portalSignInCodeError").should("contain.text", "That code did not work");
                });
                requestsFor(member.personId).then((rows) => {
                    expect(rows[0].psr_Attempts).to.eq(5);
                    expect(rows[0].psr_CancelledAt).to.not.be.null;
                });

                typeCode(code);
                cy.get("#portalSignInCodeError").should("contain.text", "That code did not work");
                cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
                auditSince(startId).then((rows) => {
                    const throttled = rows.filter((row) => row.psa_Event === "throttled");
                    expect(throttled).to.have.length(1);
                    expect(throttled[0].psa_Reason).to.eq("too_many_codes");
                    expect(throttled[0].psa_per_ID).to.eq(member.personId);
                });
            });
        });
    });

    it("cancels the earlier request when a new one is made", () => {
        askForEmail(member.email);
        signInEmail(member.email).then((first) => {
            cy.task("mail:clear");
            askForEmail(member.email);
            signInEmail(member.email).then((second) => {
                expect(second.link).to.not.eq(first.link);
                requestsFor(member.personId).then((rows) => {
                    expect(rows).to.have.length(2);
                    expect(rows[0].psr_CancelledAt).to.not.be.null;
                    expect(rows[1].psr_CancelledAt).to.be.null;
                });

                cy.visit(first.link);
                cy.get("#portalSignInLinkExpired").should("exist");
                cy.visit(second.link);
                cy.get("#portalSignInConfirmTitle").should("exist");
            });
        });
    });

    it("refuses an expired link and code", () => {
        askInBrowser();
        signInEmail(member.email).then(({ code, link }) => {
            db(
                "UPDATE person_signin_request_psr SET psr_CreatedAt = DATE_SUB(psr_CreatedAt, INTERVAL 3 HOUR), psr_ExpiresAt = DATE_SUB(psr_ExpiresAt, INTERVAL 3 HOUR) WHERE psr_per_ID = ?",
                [member.personId],
            );
            typeCode(code);
            cy.get("#portalSignInCodeError").should("contain.text", "That code did not work");
            cy.visit(link);
            cy.get("#portalSignInLinkExpired").should("exist");
            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
        });
    });

    it("lands on the portal page a signed-out visitor first asked for", () => {
        cy.visit("/portal/profile");
        cy.location("pathname").should("match", /\/portal\/login$/);
        cy.get("#portalSignInEmail").type(member.email);
        cy.get("#portalSignInSubmit").click();
        signInEmail(member.email).then(({ code }) => {
            typeCode(code);
            cy.location("pathname").should("match", /\/portal\/profile$/);
        });
    });

    it("reaches the portal but no staff route with an email session", () => {
        signInWithLink();
        cy.request("/api/portal/me").its("body.profile.id").should("eq", member.personId);
        cy.request({ url: `/api/person/${member.personId}`, failOnStatusCode: false }).its("status").should("eq", 401);
        cy.request({ url: "/v2/dashboard", followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.redirectedToUrl).to.match(/\/admin\/$/);
        });
        cy.request({ url: "/PropertyList.php", followRedirect: false }).its("status").should("eq", 302);
    });

    it("signs out a portal-only session completely and lands on /portal/login", () => {
        lastAuditId().then((startId) => {
            signInWithLink();
            cy.get("#portal-account-toggle").click();
            cy.get("#portalAccountSignOut").click();
            cy.location("pathname").should("match", /\/portal\/login$/);
            cy.get("#portalSignInEmail").should("be.visible");

            cy.request({ url: "/api/portal/me", failOnStatusCode: false }).its("status").should("eq", 401);
            cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.redirectedToUrl).to.include("/portal/login");
            });
            auditSince(startId).then((rows) => {
                const ended = rows.filter((row) => row.psa_Event === "session_ended");
                expect(ended).to.have.length(1);
                expect(ended[0].psa_Reason).to.eq("signed_out");
                expect(ended[0].psa_Method).to.eq("email_link");
                expect(ended[0].psa_per_ID).to.eq(member.personId);
            });
        });
    });

    it("says member sign-in isn't available while it is off, with the staff link", () => {
        setConfig("bMemberSignIn", "0");
        cy.visit("/portal/login");
        cy.get("#portalSignInUnavailable").should(
            "contain.text",
            "Member sign-in isn't available yet. Please contact the church office.",
        );
        cy.get("#portalSignInEmail").should("not.exist");
        cy.get("#portalStaffSignIn").should("have.attr", "href").and("match", /\/admin\/$/);
        cy.get("#portal-account-toggle").should("not.exist");
        setConfig("bMemberSignIn", "1");
    });

    it("shows the email form with a Staff sign-in link and no portal menu", () => {
        cy.visit("/portal/login");
        cy.get("#portalSignInEmail").should("be.visible");
        cy.get("#portalStaffSignIn").should("have.attr", "href").and("match", /\/admin\/$/);
        cy.get("#portal-account-toggle").should("not.exist");
        cy.get("#portal-nav").should("not.exist");

        cy.get("#portalSignInSubmit").click();
        cy.get("#portalSignInEmailError").should("contain.text", "Enter your email address.");
        mailTo(member.email).should("have.length", 0);
    });
});
