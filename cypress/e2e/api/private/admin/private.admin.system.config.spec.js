/// <reference types="cypress" />

describe("API Private Admin System Config", () => {
    it("GET password-type config never returns the stored value", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/config/sTwoFASecretKey",
            null,
            200,
        ).then((resp) => {
            expect(resp.body.value).to.eq("");
        });
    });

    it("POST password-type config with empty value is a no-op and returns empty", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/sTwoFASecretKey",
            { value: "" },
            200,
        ).then((resp) => {
            expect(resp.body.value).to.eq("");
        });

        // Verify the existing value was not overwritten
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/config/sTwoFASecretKey",
            null,
            200,
        ).then((resp) => {
            expect(resp.body.value).to.eq("");
        });
    });

    it("POST non-password config returns the saved value", () => {
        const json = { value: "1" };
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/iPersonInitialStyle",
            json,
            200,
        ).then((resp) => {
            expect(resp.body.value).to.eq(json.value);
        });
    });

    it("GET unknown config name returns 404", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/system/config/nonExistentConfigKey",
            null,
            404,
        );
    });
});
