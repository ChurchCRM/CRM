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

    describe("Config Sanitization (display configs)", () => {
        it("POST text config strips HTML/script tags and saves sanitized value", () => {
            const maliciousValue = '<script>alert("xss")</script>Legitimate Name';
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/config/sChurchName",
                { value: maliciousValue },
                200,
            ).then((resp) => {
                // Should be sanitized (tags removed, text preserved)
                expect(resp.body.value).to.contain("Legitimate Name");
                expect(resp.body.value).to.not.contain("<script>");
            });

            // Verify sanitized value persists
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/system/config/sChurchName",
                null,
                200,
            ).then((resp) => {
                expect(resp.body.value).to.not.contain("<script>");
                expect(resp.body.value).to.contain("Legitimate Name");
            });
        });

        it("POST text config with entity injection is sanitized", () => {
            const injectionValue = 'Church & <img src=x onerror="alert(1)">';
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/config/sChurchAddress",
                { value: injectionValue },
                200,
            ).then((resp) => {
                // Should not contain script vectors
                expect(resp.body.value).to.not.contain("onerror");
                expect(resp.body.value).to.not.contain("<img");
            });
        });

        it("POST text config with onclick handler is stripped", () => {
            const onclickValue = 'Contact <a onclick="alert(1)">Us</a>';
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/config/sChurchPhone",
                { value: onclickValue },
                200,
            ).then((resp) => {
                // onclick attribute should be removed
                expect(resp.body.value).to.not.contain("onclick");
                expect(resp.body.value).to.not.contain("<a");
            });
        });

        it("POST whitespace-padded value is trimmed", () => {
            const paddedValue = '   Example Church Name   ';
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/config/sChurchName",
                { value: paddedValue },
                200,
            ).then((resp) => {
                // Should be trimmed
                expect(resp.body.value).to.eq("Example Church Name");
            });
        });
    });

    describe("Config Sanitization (content configs - markup preserved)", () => {
        it("POST content config preserves PDF markup (sTaxReport1)", () => {
            const pdfMarkup = '<table border="1"><tr><td>Tax Report</td></tr></table>';
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/config/sTaxReport1",
                { value: pdfMarkup },
                200,
            ).then((resp) => {
                // Content configs should NOT strip markup
                expect(resp.body.value).to.contain("<table");
                expect(resp.body.value).to.contain("<td>");
            });
        });

        it("POST confirmation message preserves safe HTML (sConfirm1)", () => {
            const confirmHTML = '<b>Important:</b> Please confirm your action.';
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/system/config/sConfirm1",
                { value: confirmHTML },
                200,
            ).then((resp) => {
                // Should preserve the markup as-is
                expect(resp.body.value).to.contain("<b>");
                expect(resp.body.value).to.contain("</b>");
            });
        });
    });
});
