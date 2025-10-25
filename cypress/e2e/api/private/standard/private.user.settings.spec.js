/// <reference types="cypress" />

describe("API Private Current User", () => {
    it("Set / GET Current User Settings", () => {
        const json = { value: "skin-green" };
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.style",
            json,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/api/user/3/setting/ui.style",
            null,
            200,
        ).then((resp) => {
            expect(resp.value).to.eq(json.value);
        });
    });

    it("Admin Set / GET Other User Settings", () => {
        const json = { value: "skin-yellow-light" };
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.style",
            json,
            200,
        );

        cy.makePrivateAdminAPICall(
            "GET",
            "/api/user/3/setting/ui.style",
            null,
            200,
        ).then((resp) => {
            expect(resp.value).to.eq(json.value);
        });
    });

    it("Unauth get user settings ", () => {
        cy.makePrivateUserAPICall(
            "GET",
            "/api/user/1/setting/ui.style",
            null,
            401,
        );
    });
});
