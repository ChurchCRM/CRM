/// <reference types="cypress" />

import {
    askForEmail,
    auditSince,
    expectAnswerScreen,
    lastAuditId,
    mailTo,
    requestsFor,
    resetEmailSignIn,
    restoreSettings,
    setConfig,
    useMemberSignIn,
    waitForMail,
} from "../../../support/member-sign-in";

/**
 * Member sign-in row 5 (#10463): throttling of email requests
 * (docs/member-sign-in-design.md §6.1 "Throttling", §17): 5 an hour per typed
 * address, iMemberSignInRequestsPerIP an hour per IP address (20; the seed
 * sets 1000 because every spec runs from one address), 5 emails a day per
 * person. Over a limit: the same answer screen, no email, a "throttled" audit row.
 * Seed person 14, Nathan Lewis: head of household, Member, born 1947.
 */

const member = { personId: 14, email: "nathan.lewis@example.com" };

const throttledRows = (rows) => rows.filter((row) => row.psa_Event === "throttled");

describe("Member Portal sign-in throttling", () => {
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
        setConfig("iMemberSignInRequestsPerIP", "1000");
        resetEmailSignIn([member.personId]);
    });

    after(() => {
        restoreSettings(saved);
    });

    it("answers a sixth request for one address within the hour without acting on it", () => {
        const email = "throttle.address.10463@example.com";
        lastAuditId().then((startId) => {
            Cypress._.times(6, () => askForEmail(email).then(expectAnswerScreen));
            askForEmail(email.toUpperCase()).then(expectAnswerScreen);

            auditSince(startId).then((rows) => {
                expect(rows.filter((row) => row.psa_Event === "refused")).to.have.length(5);
                const throttled = throttledRows(rows);
                expect(throttled, "one audit row per limit and hour").to.have.length(1);
                expect(throttled[0].psa_Reason).to.eq("throttled_address");
                expect(throttled[0].psa_Email).to.eq(email);
            });
        });
    });

    it("answers requests from one IP address over its hourly limit without acting on them", () => {
        setConfig("iMemberSignInRequestsPerIP", "3");
        lastAuditId().then((startId) => {
            ["ip.one", "ip.two", "ip.three"].forEach((name) =>
                askForEmail(`${name}.10463@example.com`).then(expectAnswerScreen),
            );
            askForEmail(member.email).then(expectAnswerScreen);

            mailTo(member.email).should("have.length", 0);
            requestsFor(member.personId).should("have.length", 0);
            auditSince(startId).then((rows) => {
                expect(rows.filter((row) => row.psa_Event === "refused")).to.have.length(3);
                const throttled = throttledRows(rows);
                expect(throttled).to.have.length(1);
                expect(throttled[0].psa_Reason).to.eq("throttled_ip");
                expect(throttled[0].psa_Email).to.eq(member.email);
                expect(throttled[0].psa_per_ID).to.be.null;
            });
        });
    });

    it("sends one person at most five sign-in emails a day", () => {
        lastAuditId().then((startId) => {
            Cypress._.times(5, () => askForEmail(member.email).then(expectAnswerScreen));
            waitForMail(member.email, 5).should("have.length", 5);

            cy.expireSignInThrottle("portal-signin.address");
            askForEmail(member.email).then(expectAnswerScreen);

            mailTo(member.email).should("have.length", 5);
            requestsFor(member.personId).then((rows) => {
                expect(rows).to.have.length(5);
                expect(rows.filter((row) => row.psr_CancelledAt === null)).to.have.length(1);
            });
            auditSince(startId).then((rows) => {
                expect(rows.filter((row) => row.psa_Event === "email_requested")).to.have.length(5);
                const throttled = throttledRows(rows);
                expect(throttled).to.have.length(1);
                expect(throttled[0].psa_Reason).to.eq("throttled_person");
                expect(throttled[0].psa_per_ID).to.eq(member.personId);
            });
        });
    });
});
