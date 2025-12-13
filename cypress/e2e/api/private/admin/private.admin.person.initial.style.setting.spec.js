/// <reference types="cypress" />

describe("API Private Admin Person Initial Setting", () => {
    it("Delete Person Image / Avatar info available for client-side rendering", () => {
        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/person/2/photo",
            null,
            200,
        );

        // After deleting photo, /photo endpoint returns 404 (no uploaded photo)
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/person/2/photo",
            null,
            404,
        );

        // Avatar info endpoint returns data for client-side rendering
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/person/2/avatar",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("initials");
            expect(resp.body).to.have.property("hasPhoto");
            expect(resp.body.hasPhoto).to.eq(false);
        });
    });

    it("Change Person Initial Style / Delete Person Image / Avatar info available", () => {
        const json = { value: "1" };
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/iPersonInitialStyle",
            json,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/config/iPersonInitialStyle",
            null,
            200,
        ).then((resp) => {
            expect(resp.body.value).to.eq(json.value);
        });

        cy.makePrivateAdminAPICall(
            "DELETE",
            "/api/person/2/photo",
            null,
            200,
        );

        // After deleting photo, /photo endpoint returns 404
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/person/2/photo",
            null,
            404,
        );

        // Avatar info endpoint returns data for client-side rendering
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/person/2/avatar",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("initials");
        });
    });
});
