/// <reference types="cypress" />

describe("API Private Admin Person Initial Setting", () => {
    it("Delete Person Image / Generate Initial Image", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/person/2/photo",
            null,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/person/2/photo",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
        });
    });

    it("Change Person Initial Style / Delete Person Image / Generate Initial Image", () => {
        const json = { value: "1" };
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/config/iPersonInitialStyle",
            json,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/system/config/iPersonInitialStyle",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(result.value).to.eq(json.value);
        });

        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/person/2/photo",
            null,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/person/2/photo",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
        });
    });
});
