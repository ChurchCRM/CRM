/// <reference types="cypress" />

/**
 * Sign-in is refused for people who are deceased or inactive (#10193).
 * Seed users (cypress/data/seed.sql), all with password "changeme":
 *   - deceased.user     (910) person has a date of death
 *   - inactive.user     (911) person is deactivated
 *   - deactivate.target (912) active; deactivated and reactivated by the tests below
 */
const PASSWORD = "changeme";
const GENERIC_MESSAGE = "Invalid login or password";

function login(userName, password = PASSWORD) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(userName);
    cy.get("input[name=Password]").type(`${password}{enter}`);
}

function postLogin(userName) {
    return cy.request({
        method: "POST",
        url: "/session/begin",
        form: true,
        body: { User: userName, Password: PASSWORD },
    });
}

describe("Blocked sign-in for deceased and inactive people", () => {
    ["deceased.user", "inactive.user"].forEach((userName) => {
        describe(userName, () => {
            it("correct password stays on the login page with the generic message", () => {
                login(userName);
                cy.url({ timeout: 10000 }).should("include", "/session/begin");
                cy.contains(".alert-danger", GENERIC_MESSAGE);
                cy.get("input[name=User]").should("exist");
            });

            it("repeated refusals never lock the account", () => {
                cy.clearCookies();
                Cypress._.times(7, () => postLogin(userName));
                postLogin(userName).then((resp) => {
                    expect(resp.body).to.contain(GENERIC_MESSAGE);
                    expect(resp.body).not.to.contain("Too many failed logins");
                });
            });

            it("wrong password gets the same message", () => {
                login(userName, "not-the-password");
                cy.contains(".alert-danger", GENERIC_MESSAGE);
            });
        });
    });

    describe("an open session ends once the person is deactivated (deactivate.target)", () => {
        const personId = 912;
        const setActive = (active) =>
            cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/activate/${active}`, null, 200);

        beforeEach(() => setActive(true));
        afterEach(() => setActive(true));

        it("browser session is sent back to the login page", () => {
            login("deactivate.target");
            cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
            cy.visit("/v2/dashboard");
            cy.url().should("include", "/v2/dashboard");

            setActive(false);

            cy.visit("/v2/dashboard");
            cy.url().should("include", "/session/begin");
        });

        it("session-authenticated API call answers 401", () => {
            login("deactivate.target");
            cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
            cy.request("/api/person/2").its("status").should("eq", 200);

            setActive(false);

            cy.request({ url: "/api/person/2", failOnStatusCode: false }).its("status").should("eq", 401);
        });

        it("reactivating the person restores sign-in", () => {
            setActive(false);
            login("deactivate.target");
            cy.url({ timeout: 10000 }).should("include", "/session/begin");

            setActive(true);
            login("deactivate.target");
            cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
        });
    });
});
