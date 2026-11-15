/// <reference types="cypress" />

/**
 * #8672: a login's whole lifecycle is on the person's timeline as Account
 * entries (note types `user` and `login`), and the timeline's Account chip
 * filters to them.
 *
 * Creates a throwaway login for a seeded person, signs in with it, and has an
 * administrator regenerate its API key, reset its failed logins, disable its
 * 2FA, disable and enable its sign-in and delete it. Each step must leave one
 * Account entry credited to whoever did it.
 */
const PERSON = 53; // George Newman: no seeded login, used by no other spec
const PASSWORD = "account-timeline-password";
const tag = `acct${Date.now()}`;

const admin = (method, url, body = null, expectedStatus = 200) => cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
const timeline = () => admin("GET", `/api/timeline/person/${PERSON}`).its("body.timeline");
const accountEntries = () => timeline().then((items) => items.filter((item) => item.category === "account"));

describe("Account entries on the person timeline (#8672)", () => {
    let personName;
    let adminName;
    let existingIds;

    before(() => {
        admin("DELETE", `/admin/api/user/${PERSON}/`, null, [200, 404]);
        admin("GET", `/api/person/${PERSON}`).then(({ body }) => {
            personName = body.FullName;
        });
        admin("GET", "/api/person/1").then(({ body }) => {
            adminName = body.FullName;
        });
        accountEntries().then((items) => {
            existingIds = new Set(items.map((item) => item.id));
        });

        // Create the login, set a known password, sign in with it.
        cy.setupAdminSession({ forceLogin: true });
        cy.visit(`admin/system/users/new?personId=${PERSON}`);
        cy.get("input[name=csrf_token]")
            .invoke("val")
            .then((csrfToken) =>
                cy.request({
                    method: "POST",
                    url: "/admin/system/users/new",
                    form: true,
                    body: { csrf_token: csrfToken, PersonID: PERSON, UserName: tag, accessMode: "custom", Notes: "1" },
                }),
            );
        cy.visit(`admin/system/user/${PERSON}/changePassword`);
        cy.get("#NewPassword1").type(PASSWORD);
        cy.get("#NewPassword2").type(PASSWORD);
        cy.get("#NewPassword1").closest("form").submit();
        cy.contains("Password Change Successful");
        cy.then(() => cy.setupLoginSession(`account-timeline-${tag}`, tag, PASSWORD, { forceLogin: true }));

        // What an administrator does to the account.
        admin("POST", `/api/user/${PERSON}/apikey/regen`);
        admin("POST", `/admin/api/user/${PERSON}/login/reset`);
        admin("POST", `/admin/api/user/${PERSON}/disableTwoFactor`);
        admin("POST", `/admin/api/person/${PERSON}/sign-in/disable`, { reason: tag });
        admin("POST", `/admin/api/person/${PERSON}/sign-in/enable`);
        admin("DELETE", `/admin/api/user/${PERSON}/`);
    });

    after(() => {
        admin("POST", `/admin/api/person/${PERSON}/sign-in/enable`, null, [200, 409]);
        admin("DELETE", `/admin/api/user/${PERSON}/`, null, [200, 404]);
    });

    const newEntries = () => accountEntries().then((items) => items.filter((item) => !existingIds.has(item.id)));

    it("writes one Account entry per step, credited to whoever did it", () => {
        newEntries().then((items) => {
            const byText = (text) => items.filter((item) => item.text === text);
            const expected = [
                ["system user created", "user", adminName],
                ["system user password changed by admin", "user", adminName],
                ["Signed in", "login", personName],
                ["system user API key regenerated", "user", adminName],
                ["system user login reset", "user", adminName],
                ["system user two-factor authentication disabled by admin", "user", adminName],
                ["Sign-in disabled", "user", adminName],
                ["Sign-in enabled", "user", adminName],
                ["system user deleted", "user", adminName],
            ];
            for (const [text, type, author] of expected) {
                const matches = byText(text);
                expect(matches, text).to.have.length(1);
                expect(matches[0].type, `${text} type`).to.eq(type);
                expect(matches[0].header, `${text} author`).to.eq(`by ${author}`);
            }
            expect(items, "no other new Account entries").to.have.length(expected.length);
        });
    });

    it("filters the person timeline to Account entries with the Account chip", () => {
        cy.setupAdminSession();
        cy.visit(`/people/view/${PERSON}`);
        cy.get('#timeline .timeline-filter-chip[data-filter="account"]').should("exist").and("have.class", "btn-outline-secondary");
        cy.get('#timeline .timeline-event[data-timeline-category="account"]').should("not.be.visible");

        cy.get('#timeline .timeline-filter-chip[data-filter="account"]').click();
        cy.get('#timeline .timeline-filter-chip[data-filter="notes"]').click();
        cy.get('#timeline .timeline-filter-chip[data-filter="account"]').should("have.class", "btn-primary");
        cy.get('#timeline .timeline-event[data-timeline-category="account"]').should("have.length.at.least", 9).and("be.visible");
        cy.get("#timeline .timeline-event:visible").each(($event) => {
            expect($event.attr("data-timeline-category")).to.eq("account");
        });
        cy.contains('#timeline .timeline-event[data-timeline-category="account"]:visible', "Signed in");
    });
});
