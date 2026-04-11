/// <reference types="cypress" />

describe("API Private User Settings", () => {
    it("Set / GET Current User ui.style", () => {
        const json = { value: "dark" };
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.style",
            json,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/user/3/setting/ui.style",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body.value).to.eq("dark");
        });

        // Reset to default
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.style",
            { value: "default" },
            200,
        );
    });

    it("Set / GET ui.theme.primary", () => {
        const json = { value: "purple" };
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.theme.primary",
            json,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/user/3/setting/ui.theme.primary",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body.value).to.eq("purple");
        });

        // Reset
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.theme.primary",
            { value: "" },
            200,
        );
    });

    it("Set / GET ui.table.size", () => {
        const json = { value: "50" };
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.table.size",
            json,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/user/3/setting/ui.table.size",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body.value).to.eq("50");
        });

        // Reset
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.table.size",
            { value: "10" },
            200,
        );
    });

    it("Admin Set / GET Other User Settings", () => {
        const json = { value: "dark" };
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.style",
            json,
            200,
        );

        cy.request({
            method: "GET",
            url: "/api/user/3/setting/ui.style",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body.value).to.eq("dark");
        });

        // Reset
        cy.makePrivateUserAPICall(
            "POST",
            "/api/user/3/setting/ui.style",
            { value: "default" },
            200,
        );
    });

    it("Unauth get user settings returns 401", () => {
        cy.makePrivateUserAPICall(
            "GET",
            "/api/user/1/setting/ui.style",
            null,
            401,
        );
    });
});
