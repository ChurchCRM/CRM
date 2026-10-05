/// <reference types="cypress" />

/**
 * API credentials of deceased and inactive people are refused (#10193).
 * Seed users (cypress/data/seed.sql), password "changeme":
 *   - deceased.user (910), inactive.user (911)
 */
const JSON_HEADERS = { "content-type": "application/json" };

function publicPost(url, body) {
    return cy.apiRequest({ method: "POST", url, headers: JSON_HEADERS, body });
}

describe("API Public User - blocked sign-in for deceased and inactive people", () => {
    before(() => {
        cy.rememberTestEnv(["deceased.api.key", "inactive.api.key"]);
    });

    const blockedUsers = [
        { userName: "deceased.user", apiKeyEnv: "deceased.api.key" },
        { userName: "inactive.user", apiKeyEnv: "inactive.api.key" },
    ];

    blockedUsers.forEach(({ userName, apiKeyEnv }) => {
        describe(userName, () => {
            it("login with the correct password returns 401 and no apiKey", () => {
                publicPost("/api/public/user/login", { userName, password: "changeme" }).then((resp) => {
                    expect(resp.status).to.eq(401);
                    expect(JSON.stringify(resp.body)).not.to.contain(Cypress.testEnv(apiKeyEnv));
                });
            });

            it("login failure looks the same as a wrong password", () => {
                publicPost("/api/public/user/login", { userName, password: "changeme" }).then((blocked) => {
                    publicPost("/api/public/user/login", { userName: "admin", password: "wrong_password" }).then(
                        (wrong) => {
                            expect(blocked.status).to.eq(wrong.status);
                            expect(blocked.body).to.deep.eq(wrong.body);
                        },
                    );
                });
            });

            it("API key request returns 401", () => {
                cy.makePrivateAPICall(Cypress.testEnv(apiKeyEnv), "GET", "/api/person/2", null, 401);
            });

            it("password reset answers like it does for an unknown user", () => {
                publicPost("/api/public/user/password-reset", { userName }).then((blocked) => {
                    publicPost("/api/public/user/password-reset", { userName: "nonexistent_user_xyz" }).then(
                        (unknown) => {
                            expect(blocked.status).to.eq(200);
                            expect(blocked.status).to.eq(unknown.status);
                            expect(blocked.body).to.deep.eq(unknown.body);
                        },
                    );
                });
            });
        });
    });
});
