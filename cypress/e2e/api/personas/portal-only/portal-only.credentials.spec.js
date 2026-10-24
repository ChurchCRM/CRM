/// <reference types="cypress" />

import { PERSONAS } from "../../../../support/personas-data";

const apiLogin = (userName, password) =>
    cy.request({
        method: "POST",
        url: "/api/public/user/login",
        body: { userName, password },
        failOnStatusCode: false,
    });

describe("portal-only: a member never gets an API key", () => {
    it("the member's email and the seed password do not sign in through the API", () => {
        cy.makePrivateAdminAPICall("GET", `/api/person/${PERSONAS["portal-only"].member}`, null, 200).then(({ body }) =>
            apiLogin(body.Email, "changeme").then((response) => {
                expect(response.status).to.eq(401);
                expect(response.body).to.not.have.property("apiKey");
            }),
        );
    });

    it("a staff account still receives its key", () => {
        cy.readEnv("standard.username").then((username) =>
            cy.readEnv("standard.password").then((password) =>
                apiLogin(username, password).then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.body.apiKey).to.be.a("string").and.not.be.empty;
                }),
            ),
        );
    });
});
