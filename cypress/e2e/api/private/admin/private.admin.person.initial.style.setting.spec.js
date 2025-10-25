/// <reference types="cypress" />

describe("API Private Admin Person Initial Setting", () => {
    it("Delete Person Image / Generate Initial Image", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/person/2/photo",
            null,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/api/person/2/photo",
            null,
            200,
        );
    });

    it("Change Person Initial Style / Delete Person Image / Generate Initial Image", () => {
        const json = { value: "1" };
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/system/config/iPersonInitialStyle",
            json,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/api/system/config/iPersonInitialStyle",
            null,
            200,
        ).then((resp) => {
            expect(resp.value).to.eq(json.value);
        });

        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/person/2/photo",
            null,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/api/person/2/photo",
            null,
            200,
        );
    });
});
